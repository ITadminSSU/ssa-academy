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
        return DB::transaction(function () use ($data, $id) {
            $user = User::find($id);

            $user->name = $data['name'];

            if (array_key_exists('social_links', $data)) {
                $user->social_links = $data['social_links'];
            }

            if (array_key_exists('photo', $data) && $data['photo']) {
                $publicUrl = $this->addNewDeletePrev($user, $data['photo'], 'profile');
                $path = parse_url($publicUrl, PHP_URL_PATH) ?: $publicUrl;
                $user->attributes['photo'] = $path;
                $user->unsetRelation('media');
            }

            $user->save();

            return $user->fresh(['media']) ?? $user;
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
            $newEmail = $data['new_email'];

            $emailChange = EmailChangeToken::create([
                'user_id' => $user->id,
                'new_email' => $newEmail,
                'token' => $token,
                'created_at' => now(),
            ]);

            if ($emailChange->created_at === null) {
                $emailChange->forceFill(['created_at' => now()])->save();
            }

            $mail = app(AccountMailService::class);
            $mail->sendChangeEmailVerification($user, $newEmail, $url);
            $mail->sendChangeEmailAlert($user, $newEmail);
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

            if ($emailChange->created_at === null) {
                $emailChange->delete();

                return false;
            }

            $withinWindow = $emailChange->created_at->diffInMinutes(Carbon::now(), true) <= $expiryMinutes;

            if (! $withinWindow) {
                $emailChange->delete();

                return false;
            }

            $user->email = $emailChange->new_email;
            $user->email_verified_at = now();
            $user->save();

            $emailChange->delete();

            return true;
        }, 5);
    }

    /**
     * Drop all persisted sessions for the user so every device must sign in again.
     */
    public function invalidateUserSessions(int $userId): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        DB::table(config('session.table', 'sessions'))
            ->where('user_id', $userId)
            ->delete();
    }
}
