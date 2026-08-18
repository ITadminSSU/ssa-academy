<?php

namespace App\Services\Course;

use App\Enums\EnrollmentAccessStatus;
use App\Mail\CourseEnrollmentWelcomeMail;
use App\Models\Course\CourseEnrollment;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class CourseEnrollmentWelcomeMailService
{
    public function sendForEnrollment(CourseEnrollment $enrollment): bool
    {
        $enrollment->loadMissing(['user', 'course.instructor.user']);

        if ($enrollment->welcome_email_sent_at || ! $enrollment->user || ! $enrollment->course) {
            return false;
        }

        if (! $this->shouldSend($enrollment)) {
            return false;
        }

        $user = $enrollment->user;
        $course = $enrollment->course;
        $instructor = $course->instructor;
        $bio = $this->shortBio($instructor?->biography);

        $sent = $this->send($user, new CourseEnrollmentWelcomeMail(
            emailSubject: 'Welcome to '.$course->title,
            greeting: 'Hi '.$this->firstName($user).',',
            courseTitle: (string) $course->title,
            instructorName: $instructor?->user?->name ?: $instructor?->designation,
            instructorBio: $bio !== '' ? $bio : null,
            facebookUrl: $this->facebookGroupUrl(),
            ctaUrl: route('course.details', [
                'slug' => $course->slug,
                'id' => $course->id,
            ]),
        ));

        if ($sent) {
            $enrollment->forceFill(['welcome_email_sent_at' => now()])->save();
        }

        return $sent;
    }

    private function shouldSend(CourseEnrollment $enrollment): bool
    {
        $status = $enrollment->access_status;

        if ($status === EnrollmentAccessStatus::RESERVED
            || $status === EnrollmentAccessStatus::CANCELED
            || $status === EnrollmentAccessStatus::EXPIRED
            || $status === EnrollmentAccessStatus::SUSPENDED
        ) {
            return false;
        }

        return $status === EnrollmentAccessStatus::ACTIVE || $status === null;
    }

    private function shortBio(?string $biography): string
    {
        $text = trim(html_entity_decode(strip_tags((string) $biography), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;

        if ($text === '') {
            return '';
        }

        return Str::limit($text, 600, '…');
    }

    private function facebookGroupUrl(): string
    {
        $url = trim((string) config('branding.facebook_group_url', ''));

        return $url !== '' ? $url : 'https://www.facebook.com/share/g/14ttXqLttek/';
    }

    private function firstName(User $user): string
    {
        $name = trim((string) ($user->name ?? ''));

        if ($name === '') {
            return 'there';
        }

        return explode(' ', $name)[0];
    }

    private function send(User $user, CourseEnrollmentWelcomeMail $mailable): bool
    {
        if (! filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        try {
            Mail::to($user->email)->send($mailable);

            return true;
        } catch (\Throwable $exception) {
            Log::warning('Course enrollment welcome email failed', [
                'user_id' => $user->id,
                'email' => $user->email,
                'subject' => $mailable->emailSubject,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }
}
