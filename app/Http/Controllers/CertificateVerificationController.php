<?php

namespace App\Http\Controllers;

use App\Models\Course\CourseCertificate;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\Exam\Models\ExamAttempt;

class CertificateVerificationController extends Controller
{
    /**
     * Public certificate verification page.
     *
     * Anyone (e.g. an employer) can look up a certificate by its verification
     * reference (SSU-VRN-... for courses, SSU-EXR-... for exams). The page
     * returns the official record straight from the database.
     */
    public function show(Request $request, ?string $reference = null)
    {
        $reference = $reference ?? $request->query('reference');
        $reference = is_string($reference) ? trim($reference) : null;

        $result = null;

        if (!empty($reference)) {
            $result = $this->lookupCourseCertificate($reference)
                ?? $this->lookupExamCertificate($reference)
                ?? [
                    'valid' => false,
                    'reference' => $reference,
                ];
        }

        return Inertia::render('certificate-verify/index', [
            'reference' => $reference,
            'result' => $result,
        ]);
    }

    private function lookupCourseCertificate(string $reference): ?array
    {
        $certificate = CourseCertificate::query()
            ->with(['user', 'course'])
            ->where(function ($query) use ($reference) {
                $query->where('verification_reference', $reference)
                    ->orWhere('identifier', $reference);
            })
            ->first();

        if (!$certificate) {
            return null;
        }

        return [
            'valid' => true,
            'type' => 'course',
            'reference' => $certificate->verification_reference ?: $certificate->identifier,
            'recipient_name' => $certificate->user->name,
            'title' => $certificate->course->title ?? null,
            'issued_at' => optional($certificate->created_at)->format('F j, Y'),
        ];
    }

    private function lookupExamCertificate(string $reference): ?array
    {
        $attempt = ExamAttempt::query()
            ->with(['user', 'exam'])
            ->where('tracking_reference', $reference)
            ->where('status', 'completed')
            ->where('is_passed', true)
            ->first();

        if (!$attempt) {
            return null;
        }

        return [
            'valid' => true,
            'type' => 'exam',
            'reference' => $attempt->tracking_reference,
            'recipient_name' => $attempt->user->name,
            'title' => $attempt->exam->title ?? null,
            'issued_at' => optional($attempt->end_time ?? $attempt->updated_at)->format('F j, Y'),
            'score' => round((float) $attempt->percentage, 2),
        ];
    }
}
