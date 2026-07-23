<?php

namespace App\Services;

use App\Models\User;
use App\Support\MasterAdmin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

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
            ->orderBy('created_at', 'desc');

        $transform = function (User $user): User {
            $cvMedia = $user->getFirstMedia('cv_resume');
            $user->cv_resume_url = $cvMedia ? $cvMedia->getFullUrl() : null;
            $user->cv_resume_name = $cvMedia ? $cvMedia->name : null;

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

            if ($user->role === 'student') {
                $payload['user_type'] = $data['user_type'];
            }

            $user->update($payload);

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
}
