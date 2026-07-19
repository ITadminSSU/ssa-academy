<?php

namespace App\Services\Course;

use App\Models\Course\Course;
use App\Models\Course\CourseCertificate;
use App\Models\User;
use App\Support\ReferenceNumberService;

class CourseCertificateIssuanceService
{
    public function __construct(private ReferenceNumberService $referenceNumbers) {}

    public function issueForCourse(Course $course, User $user): ?CourseCertificate
    {
        $existing = CourseCertificate::query()
            ->where('course_id', $course->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing) {
            return $this->ensureVerificationReference($existing);
        }

        $verificationReference = $this->referenceNumbers->generateCertificateVerificationReference();
        $certificateId = $this->referenceNumbers->generateCertificateId();

        return CourseCertificate::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'identifier' => $verificationReference,
            'certificate_id' => $certificateId,
            'verification_reference' => $verificationReference,
        ]);
    }

    public function getForStudent(Course $course, User $user): ?CourseCertificate
    {
        $certificate = CourseCertificate::query()
            ->where('course_id', $course->id)
            ->where('user_id', $user->id)
            ->first();

        return $certificate ? $this->ensureVerificationReference($certificate) : null;
    }

    private function ensureVerificationReference(CourseCertificate $certificate): CourseCertificate
    {
        $updates = [];

        if (empty($certificate->verification_reference)) {
            $reference = !empty($certificate->identifier)
                ? $certificate->identifier
                : $this->referenceNumbers->generateCertificateVerificationReference();
            $updates['verification_reference'] = $reference;
            $updates['identifier'] = $certificate->identifier ?: $reference;
        }

        if (empty($certificate->certificate_id)) {
            $updates['certificate_id'] = $this->referenceNumbers->generateCertificateId();
        }

        if (!empty($updates)) {
            $certificate->update($updates);
        }

        return $certificate->fresh();
    }
}
