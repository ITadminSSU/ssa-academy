<?php

namespace Database\Seeders;

use App\Enums\LearnerUserType;
use App\Models\Instructor;
use App\Models\ProfessionalType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestUsersSeeder extends Seeder
{
    private const PASSWORD = 'password123';

    public function run(): void
    {
        $legalVersion = config('legal.agreement_version', '2026-06-09');
        $now = now();
        $password = Hash::make(self::PASSWORD);

        $professionalTypeId = ProfessionalType::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->value('id');

        $this->upsertUser([
            'email' => 'admin-test@smartsourcingusa.com',
            'name' => 'SSU Test Admin',
            'role' => 'admin',
            'user_type' => LearnerUserType::EMPLOYEE,
            'password' => $password,
            'email_verified_at' => $now,
            'legal_agreement_accepted_at' => $now,
            'legal_agreement_version' => $legalVersion,
            'legal_agreement_ip' => '127.0.0.1',
            'professional_type_id' => $professionalTypeId,
        ]);

        $trainer = $this->upsertUser([
            'email' => 'trainer-test@smartsourcingusa.com',
            'name' => 'SSU Test Trainer',
            'role' => 'instructor',
            'user_type' => LearnerUserType::EMPLOYEE,
            'password' => $password,
            'email_verified_at' => $now,
            'legal_agreement_accepted_at' => $now,
            'legal_agreement_version' => $legalVersion,
            'legal_agreement_ip' => '127.0.0.1',
            'professional_type_id' => $professionalTypeId,
        ]);

        $instructor = Instructor::updateOrCreate(
            ['user_id' => $trainer->id],
            [
                'skills' => json_encode(['Training', 'Course delivery']),
                'biography' => 'SSU Academy test trainer account for course authoring, assignment review, and learner progress tracking.',
                'resume' => '',
                'designation' => 'Lead Trainer',
                'status' => 'approved',
                'payout_methods' => json_encode([]),
            ]
        );

        $trainer->update(['instructor_id' => $instructor->id, 'role' => 'instructor']);

        $this->upsertUser([
            'email' => 'employee-test@smartsourcingusa.com',
            'name' => 'SSU Test Employee',
            'role' => 'student',
            'user_type' => LearnerUserType::EMPLOYEE,
            'password' => $password,
            'email_verified_at' => $now,
            'legal_agreement_accepted_at' => $now,
            'legal_agreement_version' => $legalVersion,
            'legal_agreement_ip' => '127.0.0.1',
            'professional_type_id' => $professionalTypeId,
        ]);

        $this->command?->info('SSU test users ready (password: ' . self::PASSWORD . ')');
        $this->command?->table(
            ['Role', 'Email', 'Learner type'],
            [
                ['Admin', 'admin-test@smartsourcingusa.com', 'employee'],
                ['Trainer (instructor)', 'trainer-test@smartsourcingusa.com', 'employee'],
                ['Internal employee (student)', 'employee-test@smartsourcingusa.com', 'employee'],
                ['External learner', '(self-register at /register)', 'external — non-@smartsourcingusa.com email'],
            ]
        );
    }

    private function upsertUser(array $attributes): User
    {
        $user = User::updateOrCreate(
            ['email' => $attributes['email']],
            $attributes
        );

        return $user->fresh();
    }
}
