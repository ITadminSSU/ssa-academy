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

        // CV and government ID are stored once on the account. Hold a new certificate until both are present.
        if (! $this->hasReleaseDocuments($user)) {
            return null;
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

    /**
     * @return array{needs_cv: bool, needs_id: bool}
     */
    public function missingReleaseDocuments(User $user): array
    {
        return [
            'needs_cv' => ! $user->hasMedia('cv_resume'),
            'needs_id' => ! $user->hasMedia('id_document'),
        ];
    }

    public function hasReleaseDocuments(User $user): bool
    {
        $missing = $this->missingReleaseDocuments($user);

        return ! $missing['needs_cv'] && ! $missing['needs_id'];
    }

    public function finishCourseNotice(User $user, bool $issued): string
    {
        if ($issued) {
            return 'Course completed successfully. Your certificate has been issued.';
        }

        $missing = $this->missingReleaseDocuments($user);
        $files = array_values(array_filter([
            $missing['needs_cv'] ? 'CV/resume' : null,
            $missing['needs_id'] ? 'government ID' : null,
        ]));

        if ($files === []) {
            return 'Course completed successfully. Your certificate has been issued.';
        }

        $list = count($files) === 2 ? $files[0].' and '.$files[1] : $files[0];

        return "Course completed successfully. Upload your {$list} on your profile to release your certificate.";
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
