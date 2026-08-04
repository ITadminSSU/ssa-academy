<?php

namespace App\Services;

use App\Models\PasswordResetToken;
use App\Models\User;
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

            $token = Str::random(60);
            $url = route('account.save-email', ['token' => $token]);

            $reset = PasswordResetToken::where('email', $data['new_email'])->first();
            if ($reset) {
                $reset->delete();
            }

            PasswordResetToken::create([
                'email' => $data['new_email'],
                'token' => $token,
            ]);

            app(AccountMailService::class)->sendChangeEmailVerification($user, $data['new_email'], $url);
        }, 5);
    }

    public function saveChangedEmail(string $token, string $id): bool
    {
        return DB::transaction(function () use ($token, $id) {
            $user = User::find($id); // Retrieve the authenticated user

            $reset = PasswordResetToken::where('token', $token)->first();

            // Validate if the reset token exists
            if (!$reset) {
                return false;
            }

            // Verify the token securely
            if (!hash_equals($reset->token, $token)) {
                return false;
            }

            $expiryMinutes = (int) config('account.email_change_token_expiry_minutes', 60);
            $withinWindow = $reset->created_at !== null
                && $reset->created_at->diffInMinutes(Carbon::now()) <= $expiryMinutes;

            if ($withinWindow) {
                $user->email = $reset->email;
                $user->save();
            }

            $reset->delete();

            return $withinWindow;
        }, 5);
    }
}
