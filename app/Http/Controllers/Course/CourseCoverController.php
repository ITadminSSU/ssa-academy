<?php

namespace App\Http\Controllers\Course;

use App\Http\Controllers\Controller;
use App\Models\Course\Course;
use Symfony\Component\HttpFoundation\Response;

/**
 * Same-origin course cover for Blade/checkout pages.
 * Avoids embedding private R2 signed URLs in HTML (often fail / expire in img tags).
 */
class CourseCoverController extends Controller
{
    public function __invoke(Course $course): Response
    {
        // Accessors re-sign private R2/S3 objects for the browser.
        $url = trim((string) ($course->thumbnail ?: $course->banner ?: ''));

        if ($url === '') {
            abort(404);
        }

        return redirect()->away($url, 302)
            ->header('Cache-Control', 'private, max-age=300');
    }
}
