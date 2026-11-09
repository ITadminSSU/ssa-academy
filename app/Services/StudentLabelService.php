<?php

namespace App\Services;

use App\Models\StudentLabel;
use App\Models\User;

class StudentLabelService
{
    /**
     * Colored labels are display-only. They never enroll, unenroll, or change PEF.
     *
     * @param  list<int|string>  $ids
     */
    public function syncForUser(User $user, array $ids): void
    {
        if ($user->role !== 'student') {
            $user->studentLabels()->detach();

            return;
        }

        $valid = StudentLabel::query()
            ->whereIn('id', $ids)
            ->pluck('id')
            ->all();

        $user->studentLabels()->sync($valid);
    }
}
