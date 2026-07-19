<?php

namespace App\Support;

use App\Models\Course\CourseCertificate;
use Illuminate\Support\Str;
use Modules\Exam\Models\ExamAttempt;

class ReferenceNumberService
{
    public function generateCertificateVerificationReference(): string
    {
        return $this->generateUnique(
            'SSU-VRN-',
            CourseCertificate::class,
            'verification_reference'
        );
    }

    public function generateCertificateId(): string
    {
        $year = now()->format('Y');
        $prefix = "SSU-{$year}-";

        do {
            $next = $this->maxCertificateSequence($prefix) + 1;
            $certificateId = $prefix . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
        } while (
            CourseCertificate::query()->where('certificate_id', $certificateId)->exists()
            || ExamAttempt::query()->where('certificate_id', $certificateId)->exists()
        );

        return $certificateId;
    }

    /**
     * Highest numeric sequence already used for the given prefix across both
     * course and exam certificates, so the SSU-YYYY-XXXXX namespace stays
     * globally unique and sequential.
     */
    private function maxCertificateSequence(string $prefix): int
    {
        $max = 0;

        foreach ([CourseCertificate::class, ExamAttempt::class] as $model) {
            $latest = $model::query()
                ->where('certificate_id', 'like', $prefix . '%')
                ->orderByDesc('certificate_id')
                ->value('certificate_id');

            if ($latest && preg_match('/(\d+)$/', $latest, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        return $max;
    }

    public function generateExamTrackingReference(): string
    {
        return $this->generateUnique(
            'SSU-EXR-',
            ExamAttempt::class,
            'tracking_reference'
        );
    }

    private function generateUnique(string $prefix, string $modelClass, string $column): string
    {
        do {
            $reference = $prefix . strtoupper(Str::random(12));
        } while ($modelClass::query()->where($column, $reference)->exists());

        return $reference;
    }
}
