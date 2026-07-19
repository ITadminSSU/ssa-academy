<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\TestUsersSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ResetTestUserPasswords extends Command
{
    protected $signature = 'ssu:reset-test-users';

    protected $description = 'Reset SSU local test account passwords to password123';

    public function handle(): int
    {
        $this->call('db:seed', [
            '--class' => TestUsersSeeder::class,
            '--force' => true,
        ]);

        $emails = [
            'admin-test@smartsourcingusa.com',
            'trainer-test@smartsourcingusa.com',
            'employee-test@smartsourcingusa.com',
            'it-admin@smartsourcingusa.com',
        ];

        $hash = Hash::make('password123');

        foreach ($emails as $email) {
            $user = User::where('email', $email)->first();

            if (!$user) {
                $this->warn("Skipped missing account: {$email}");

                continue;
            }

            DB::table('users')->where('id', $user->id)->update([
                'password' => $hash,
                'updated_at' => now(),
            ]);
            $this->line("Reset {$email}");
        }

        $this->info('All test passwords set to: password123');

        return self::SUCCESS;
    }
}
