<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class UserService
{
    public function getUsers(array $data): LengthAwarePaginator|Collection
    {
        $page = array_key_exists('per_page', $data) ? intval($data['per_page']) : 10;

        $users = User::with(['professionalType'])
            ->where('role', '!=', 'admin')
            ->where('role', '!=', 'instructor')
            ->when(array_key_exists('search', $data), function ($query) use ($data) {
                return $query->where('name', 'LIKE', '%' . $data['search'] . '%')
                    ->orWhere('email', 'LIKE', '%' . $data['search'] . '%');
            })
            ->orderBy('created_at', 'desc');

        if (array_key_exists('paginate', $data) && $data['paginate']) {
            $paginated = $users->paginate($page);
            // Load CV media for each user
            $paginated->getCollection()->transform(function ($user) {
                $cvMedia = $user->getFirstMedia('cv_resume');
                $user->cv_resume_url = $cvMedia ? $cvMedia->getFullUrl() : null;
                $user->cv_resume_name = $cvMedia ? $cvMedia->name : null;
                return $user;
            });
            return $paginated;
        }

        $users = $users->get();
        // Load CV media for each user
        $users->transform(function ($user) {
            $cvMedia = $user->getFirstMedia('cv_resume');
            $user->cv_resume_url = $cvMedia ? $cvMedia->getFullUrl() : null;
            $user->cv_resume_name = $cvMedia ? $cvMedia->name : null;
            return $user;
        });

        return $users;
    }

    public function updateUser(int | string $id, array $data): void
    {
        DB::transaction(function () use ($data, $id) {
            User::find($id)->update($data);
        }, 5);
    }

    public function deleteUser(int | string $id): void
    {
        DB::transaction(function () use ($id) {
            $user = User::findOrFail($id);

            if ($user->role === 'admin') {
                throw new \InvalidArgumentException('Admin accounts cannot be deleted.');
            }

            $user->delete();
        }, 5);
    }
}
