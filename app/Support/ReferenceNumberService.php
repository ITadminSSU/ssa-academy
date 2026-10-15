<?php

namespace App\Support;

use App\Models\Course\CourseCertificate;
use App\Models\Course\UsExperienceCertificate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Exam\Models\ExamAttempt;

class ReferenceNumberService
{
    public function generateCertificateVerificationReference(): string
    {
        do {
            $reference = 'SSU-VRN-'.strtoupper(Str::random(12));
        } while ($this->verificationReferenceTaken($reference));

        return $reference;
    }

    public function generateCertificateId(): string
    {
        $year = now()->format('Y');
        $prefix = "SSU-{$year}-";

        do {
            $next = $this->maxCertificateSequence($prefix) + 1;
            $certificateId = $prefix.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
        } while ($this->certificateIdTaken($certificateId));

        return $certificateId;
    }

    /**
     * Highest numeric sequence already used for the given prefix across
     * course, exam, and US Experience certificates, so the SSU-YYYY-XXXXX
     * namespace stays globally unique and sequential.
     */
    private function maxCertificateSequence(string $prefix): int
    {
        $max = 0;

        foreach ($this->certificateIdModels() as $model) {
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

    /**
     * @return list<class-string>
     */
    private function certificateIdModels(): array
    {
        $models = [CourseCertificate::class, ExamAttempt::class];

        if (Schema::hasTable('us_experience_certificates')) {
            $models[] = UsExperienceCertificate::class;
        }

        return $models;
    }

    private function certificateIdTaken(string $certificateId): bool
    {
        foreach ($this->certificateIdModels() as $model) {
            if ($model::query()->where('certificate_id', $certificateId)->exists()) {
                return true;
            }
        }

        return false;
    }

    private function verificationReferenceTaken(string $reference): bool
    {
        $taken = CourseCertificate::query()
            ->where('verification_reference', $reference)
            ->orWhere('identifier', $reference)
            ->exists();

        if ($taken || ! Schema::hasTable('us_experience_certificates')) {
            return $taken;
        }

        return UsExperienceCertificate::query()
            ->where('verification_reference', $reference)
            ->orWhere('identifier', $reference)
            ->exists();
    }

    private function generateUnique(string $prefix, string $modelClass, string $column): string
    {
        do {
            $reference = $prefix . strtoupper(Str::random(12));
        } while ($modelClass::query()->where($column, $reference)->exists());

        return $reference;
    }
}
