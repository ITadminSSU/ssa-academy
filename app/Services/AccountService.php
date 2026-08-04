<?php

namespace App\Services;

use App\Models\EmailChangeToken;
use App\Models\User;
use App\Support\EmailChangeUrl;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AccountService extends MediaService
{
    public function updateProfile(array $data, string $id)
    {
        DB::transaction(function () use ($data, $id) {
            $user = User::find($id);

            $user->name = $data['name'];
            $user->social_links = $data['social_links'];

            if (array_key_exists('photo', $data) && $data['photo']) {
                $fullUrl = $this->addNewDeletePrev($user, $data['photo'], 'profile');

                $user->photo = $fullUrl;
            }

            $user->save();
        }, 5);
    }

    public function changeEmail(array $data, string $id): void
    {
        DB::transaction(function () use ($data, $id) {
            $user = User::find($id);

            EmailChangeToken::query()
                ->where('user_id', $user->id)
                ->delete();

            $token = Str::random(60);
            $url = EmailChangeUrl::verificationLink($user, $token);

            EmailChangeToken::create([
                'user_id' => $user->id,
                'new_email' => $data['new_email'],
                'token' => $token,
                'created_at' => now(),
            ]);

            app(AccountMailService::class)->sendChangeEmailVerification($user, $data['new_email'], $url);
        }, 5);
    }

    public function saveChangedEmail(string $token, string $id): bool
    {
        return DB::transaction(function () use ($token, $id) {
            $user = User::find($id);

            if (! $user) {
                return false;
            }

            $emailChange = EmailChangeToken::query()
                ->where('user_id', $user->id)
                ->where('token', $token)
                ->first();

            if (! $emailChange) {
                return false;
            }

            if (! hash_equals($emailChange->token, $token)) {
                return false;
            }

            $expiryMinutes = (int) config('account.email_change_token_expiry_minutes', 60);
            $withinWindow = $emailChange->created_at !== null
                && $emailChange->created_at->diffInMinutes(Carbon::now()) <= $expiryMinutes;

            if ($withinWindow) {
                $user->email = $emailChange->new_email;
                $user->email_verified_at = now();
                $user->save();
            }

            $emailChange->delete();

            return $withinWindow;
        }, 5);
    }
}
