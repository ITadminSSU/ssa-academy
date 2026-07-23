<?php

namespace App\Services\Admin;

use App\Enums\LearnerUserType;
use App\Models\Instructor;
use App\Models\ProfessionalType;
use App\Models\User;
use App\Services\LegalAgreementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminUserProvisioningService
{
    public function __construct(
        private LegalAgreementService $legalAgreement,
    ) {}

    /**
     * @param  array{name: string, email: string, password: string, account_type: 'admin'|'employee'|'trainer', designation?: string|null}  $data
     */
    public function provision(array $data, Request $request): User
    {
        return DB::transaction(function () use ($data, $request) {
            $professionalTypeId = ProfessionalType::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->value('id');

            $role = match ($data['account_type']) {
                'admin' => 'admin',
                'trainer' => 'instructor',
                default => 'student',
            };

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role' => $role,
                'user_type' => LearnerUserType::EMPLOYEE,
                'status' => 1,
                'email_verified_at' => now(),
                'professional_type_id' => $professionalTypeId,
            ]);

            if ($data['account_type'] === 'trainer') {
                $instructor = Instructor::create([
                    'user_id' => $user->id,
                    'skills' => ['Training', 'Course delivery'],
                    'biography' => $data['name'] . ' is a Smart Sourcing Academy trainer delivering courses and supporting learners.',
                    'resume' => '',
                    'designation' => $data['designation'],
                    'status' => 'approved',
                    'payout_methods' => [],
                ]);

                $user->update(['instructor_id' => $instructor->id]);
            }

            if ($data['account_type'] !== 'admin') {
                $this->legalAgreement->recordAcceptance($user->fresh(), $request, sendEmail: false);
            }

            return $user->fresh();
        }, 5);
    }
}
