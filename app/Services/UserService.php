<?php

namespace App\Services;

use App\Enums\LearnerUserType;
use App\Models\Instructor;
use App\Models\User;
use App\Services\Auth\SingleSessionService;
use App\Support\MasterAdmin;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserService
{
    public function getUsers(array $data): LengthAwarePaginator|Collection
    {
        $page = array_key_exists('per_page', $data) ? intval($data['per_page']) : 10;
        $includeAllRoles = (bool) ($data['include_all_roles'] ?? false);

        $users = User::with(['professionalType', 'instructor'])
            ->when(!$includeAllRoles, function ($query) {
                return $query
                    ->where('role', '!=', 'admin')
                    ->where('role', '!=', 'instructor');
            })
            ->when($includeAllRoles && !empty($data['role_filter']) && $data['role_filter'] !== 'all', function ($query) use ($data) {
                return match ($data['role_filter']) {
                    'admin' => $query->where('role', 'admin'),
                    'trainer' => $query->where('role', 'instructor'),
                    'internal_employee' => $query->where('role', 'student')->where('user_type', 'employee'),
                    'external' => $query->where('role', 'student')->where('user_type', 'external'),
                    default => $query,
                };
            })
            ->when(array_key_exists('search', $data) && $data['search'], function ($query) use ($data) {
                $search = $data['search'];

                return $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'LIKE', '%' . $search . '%')
                        ->orWhere('email', 'LIKE', '%' . $search . '%');
                });
            })
            ->when(!empty($data['registered_from']), function ($query) use ($data) {
                return $query->whereDate('created_at', '>=', $data['registered_from']);
            })
            ->when(!empty($data['registered_to']), function ($query) use ($data) {
                return $query->whereDate('created_at', '<=', $data['registered_to']);
            })
            ->orderBy('created_at', 'desc');

        $transform = function (User $user): User {
            $cvMedia = $user->getFirstMedia('cv_resume');
            $user->cv_resume_url = $cvMedia ? media_public_url($cvMedia) : null;
            $user->cv_resume_name = $cvMedia ? $cvMedia->name : null;
            $user->has_cv = (bool) $cvMedia;

            $idMedia = $user->getFirstMedia('id_document');
            $user->id_document_url = $idMedia ? media_public_url($idMedia) : null;
            $user->id_document_name = $idMedia ? $idMedia->name : null;
            $user->has_id_document = (bool) $idMedia;

            return $user;
        };

        if (array_key_exists('paginate', $data) && $data['paginate']) {
            $paginated = $users->paginate($page);
            $paginated->getCollection()->transform($transform);

            return $paginated;
        }

        return $users->get()->transform($transform);
    }

    public function updateUser(int | string $id, array $data): void
    {
        DB::transaction(function () use ($data, $id) {
            $user = User::with('instructor')->findOrFail($id);

            if (MasterAdmin::isProtected($user)) {
                $user->update([
                    'name' => $data['name'],
                    'email' => strtolower($data['email']),
                ]);

                return;
            }

            $payload = [
                'name' => $data['name'],
                'email' => strtolower($data['email']),
                'status' => (int) $data['status'],
            ];

            if (!empty($data['password'])) {
                $payload['password'] = Hash::make($data['password']);
            }

            if (canManagePlatformSettings() && array_key_exists('account_type', $data)) {
                $payload = array_merge($payload, $this->accountTypePayload($user, $data));
            } else {
                if ($user->role === 'student') {
                    $payload['user_type'] = $data['user_type'];
                }

                if (
                    $user->role === 'admin'
                    && array_key_exists('can_manage_platform_settings', $data)
                    && canManagePlatformSettings()
                ) {
                    $payload['can_manage_platform_settings'] = (bool) $data['can_manage_platform_settings'];
                }
            }

            $wasActive = $user->isAccountActive();

            $user->update($payload);
            $user = $user->fresh('instructor');

            if ($wasActive && (int) $payload['status'] === 0) {
                app(SingleSessionService::class)->revokeAll($user);
            }

            if ($user->role === 'instructor' && $user->instructor && array_key_exists('designation', $data)) {
                $user->instructor->update([
                    'designation' => $data['designation'],
                ]);
            }
        }, 5);
    }

    /**
     * @return array{all: int, admin: int, internal_employee: int, external: int, trainer: int}
     */
    public function getRoleCounts(): array
    {
        return [
            'all' => User::count(),
            'admin' => User::where('role', 'admin')->count(),
            'internal_employee' => User::where('role', 'student')->where('user_type', 'employee')->count(),
            'external' => User::where('role', 'student')->where('user_type', 'external')->count(),
            'trainer' => User::where('role', 'instructor')->count(),
        ];
    }

    public function deleteUser(int | string $id): void
    {
        DB::transaction(function () use ($id) {
            $user = User::findOrFail($id);

            if (MasterAdmin::isProtected($user)) {
                throw new \InvalidArgumentException('The primary admin account cannot be deleted.');
            }

            if ($user->role === 'instructor') {
                throw new \InvalidArgumentException('Trainer accounts must be removed from the Instructors section.');
            }

            $user->delete();
        }, 5);
    }

    public function roleFilterOptions(): array
    {
        return [
            ['value' => 'all', 'label' => __('dashboard.role_filter_all')],
            ['value' => 'admin', 'label' => __('dashboard.role_filter_admin')],
            ['value' => 'internal_employee', 'label' => __('dashboard.role_filter_internal_employee')],
            ['value' => 'external', 'label' => __('dashboard.role_filter_external')],
            ['value' => 'trainer', 'label' => __('dashboard.role_filter_trainer')],
        ];
    }

    public function exportUsersCsv(array $data): StreamedResponse
    {
        $roleFilter = $data['role_filter'] ?? '';
        $users = $this->getUsers([
            ...$data,
            'paginate' => false,
            'include_all_roles' => true,
        ]);

        $label = match ($roleFilter) {
            'internal_employee' => 'internal-employees',
            default => 'external-learners',
        };

        $filename = sprintf('%s-%s.csv', $label, now()->format('Y-m-d'));

        return response()->streamDownload(function () use ($users) {
            $handle = fopen('php://output', 'w');

            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($handle, [
                'Name',
                'Email',
                'Status',
                'Role',
                'Learner Type',
                'Professional Type',
                'Registered At',
                'CV On File',
                'ID On File',
            ]);

            foreach ($users as $user) {
                $professionalType = $user->professionalType?->name ?? '';
                if ($professionalType === 'Other' && $user->professional_type_other) {
                    $professionalType = 'Other (' . $user->professional_type_other . ')';
                }

                fputcsv($handle, [
                    $user->name,
                    $user->email,
                    (int) $user->status === 1 ? 'Active' : 'Inactive',
                    $this->exportRoleLabel($user->role),
                    $this->exportLearnerTypeLabel($user),
                    $professionalType,
                    $user->created_at?->format('Y-m-d H:i:s') ?? '',
                    $user->getFirstMedia('cv_resume') ? 'Yes' : 'No',
                    $user->getFirstMedia('id_document') ? 'Yes' : 'No',
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function exportRoleLabel(string $role): string
    {
        return match ($role) {
            'admin' => 'Admin',
            'instructor' => 'Trainer',
            'student' => 'Student',
            default => $role,
        };
    }

    private function exportLearnerTypeLabel(User $user): string
    {
        if ($user->role !== 'student') {
            return '';
        }

        return $user->user_type === LearnerUserType::EMPLOYEE ? 'Internal employee' : 'External learner';
    }

    /**
     * @param  array{account_type: string, designation?: string|null}  $data
     * @return array<string, mixed>
     */
    private function accountTypePayload(User $user, array $data): array
    {
        $type = $data['account_type'];

        $payload = match ($type) {
            'admin' => [
                'role' => 'admin',
                'can_manage_platform_settings' => true,
            ],
            'operations' => [
                'role' => 'admin',
                'can_manage_platform_settings' => false,
            ],
            'employee' => [
                'role' => 'student',
                'user_type' => LearnerUserType::EMPLOYEE,
                'can_manage_platform_settings' => false,
                'instructor_id' => null,
            ],
            'external' => [
                'role' => 'student',
                'user_type' => LearnerUserType::EXTERNAL,
                'can_manage_platform_settings' => false,
                'instructor_id' => null,
            ],
            'trainer' => [
                'role' => 'instructor',
                'can_manage_platform_settings' => false,
                'instructor_id' => $this->ensureInstructorProfile($user, $data['designation'] ?? null)->id,
            ],
            default => [],
        };

        if (in_array($type, ['admin', 'operations'], true)) {
            $payload['instructor_id'] = null;
        }

        return $payload;
    }

    private function ensureInstructorProfile(User $user, ?string $designation): Instructor
    {
        $instructor = Instructor::query()->where('user_id', $user->id)->first();
        $title = is_string($designation) && trim($designation) !== ''
            ? trim($designation)
            : ($user->instructor?->designation ?: 'Trainer');

        if ($instructor) {
            $instructor->update([
                'status' => 'approved',
                'designation' => $title,
            ]);

            return $instructor;
        }

        return Instructor::create([
            'user_id' => $user->id,
            'skills' => ['Training', 'Course delivery'],
            'biography' => $user->name.' is a SMARTSOURCING USA ACADEMY trainer delivering courses and supporting learners.',
            'resume' => '',
            'designation' => $title,
            'status' => 'approved',
            'payout_methods' => [],
        ]);
    }
}
