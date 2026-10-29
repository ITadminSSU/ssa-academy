<?php

namespace App\Services\Admin;

use App\Enums\LearnerUserType;
use App\Models\Instructor;
use App\Models\ProfessionalType;
use App\Models\User;
use App\Services\LegalAgreementService;
use App\Support\PersonName;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminUserProvisioningService
{
    public function __construct(
        private LegalAgreementService $legalAgreement,
    ) {}

    /**
     * @param  array{given_name: string, last_name?: string|null, email: string, password: string, account_type: 'admin'|'operations'|'employee'|'trainer'|'social_media', designation?: string|null}  $data
     */
    public function provision(array $data, Request $request): User
    {
        return DB::transaction(function () use ($data, $request) {
            $professionalTypeId = ProfessionalType::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->value('id');

            $role = match ($data['account_type']) {
                'admin', 'operations' => 'admin',
                'trainer' => 'instructor',
                'social_media' => 'social_media',
                default => 'student',
            };

            $names = PersonName::attributes((string) $data['given_name'], $data['last_name'] ?? null);

            $user = User::create([
                ...$names,
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role' => $role,
                'can_manage_platform_settings' => $data['account_type'] === 'admin',
                'user_type' => LearnerUserType::EMPLOYEE,
                'status' => 1,
                'email_verified_at' => now(),
                'professional_type_id' => $professionalTypeId,
            ]);

            if ($data['account_type'] === 'trainer') {
                $instructor = Instructor::create([
                    'user_id' => $user->id,
                    'skills' => ['Training', 'Course delivery'],
                    'biography' => $names['name'].' is a SMARTSOURCING USA ACADEMY trainer delivering courses and supporting learners.',
                    'resume' => '',
                    'designation' => $data['designation'],
                    'status' => 'approved',
                    'payout_methods' => [],
                ]);

                $user->update(['instructor_id' => $instructor->id]);
            }

            if (! in_array($data['account_type'], ['admin', 'operations', 'social_media'], true)) {
                $this->legalAgreement->recordAcceptance($user->fresh(), $request, sendEmail: false);
            }

            return $user->fresh();
        }, 5);
    }
}
