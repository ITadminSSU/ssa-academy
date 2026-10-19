<?php

namespace App\Support;

use App\Models\Course\Course;

class CourseInstructorCard
{
    /**
     * @return array{heading: string, biography: string, photo: string}|null
     */
    public static function present(Course $course): ?array
    {
        $instructor = $course->instructor;
        $user = $instructor?->user;

        if (! $instructor || ! $user) {
            return null;
        }

        $designation = trim((string) $instructor->designation);
        $accountHeading = trim($user->name.', '.($designation !== '' ? $designation : 'Trainer'));
        $heading = CourseHero::blankToNull($course->instructor_card_name) ?? $accountHeading;
        $biography = CourseHero::blankToNull($course->instructor_card_biography)
            ?? CourseHero::blankToNull($instructor->biography)
            ?? '';
        $photo = CourseHero::blankToNull($course->instructor_card_photo)
            ?? CourseHero::blankToNull($user->photo)
            ?? '';

        return [
            'heading' => mb_strtoupper($heading),
            'biography' => $biography,
            'photo' => $photo,
        ];
    }
}
