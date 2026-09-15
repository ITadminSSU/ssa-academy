<?php

use App\Models\ChunkedUpload;
use App\Models\Course\Course;
use App\Models\Course\CourseCategory;
use App\Models\Course\CourseSection;
use App\Models\Course\LessonResource;
use App\Models\Course\SectionLesson;
use App\Models\Instructor;
use App\Models\User;
use App\Services\Course\LessonResourceService;
use App\Services\Course\ProtectedMediaService;
use Illuminate\Support\Facades\File;

function lessonResourceAdmin(): User
{
    return User::factory()->create([
        'role' => 'admin',
        'legal_agreement_accepted_at' => now(),
        'legal_agreement_version' => config('legal.agreement_version', '2026-07-16'),
    ]);
}

function lessonResourceFixture(string $resourceUrl, bool $downloadable = true): LessonResource
{
    $user = User::factory()->create(['role' => 'instructor']);

    $instructor = Instructor::create([
        'user_id' => $user->id,
        'skills' => ['estimating'],
        'biography' => 'Test instructor',
        'resume' => 'resume.pdf',
        'designation' => 'Instructor',
        'status' => 'approved',
        'payout_methods' => [],
    ]);

    $category = CourseCategory::create([
        'title' => 'Estimating',
        'slug' => 'estimating-'.uniqid(),
    ]);

    $course = Course::create([
        'title' => 'Resource Course',
        'slug' => 'resource-course-'.uniqid(),
        'course_type' => 'general',
        'status' => 'approved',
        'level' => 'beginner',
        'short_description' => 'Short',
        'instructor_id' => $instructor->id,
        'course_category_id' => $category->id,
    ]);

    $section = CourseSection::create([
        'title' => 'Module 1',
        'sort' => 1,
        'course_id' => $course->id,
    ]);

    $lesson = SectionLesson::create([
        'title' => 'Lesson 1',
        'sort' => 1,
        'status' => true,
        'lesson_type' => 'document',
        'course_id' => $course->id,
        'course_section_id' => $section->id,
    ]);

    return LessonResource::create([
        'title' => 'Sample PDF',
        'type' => 'document',
        'resource' => $resourceUrl,
        'section_lesson_id' => $lesson->id,
        'is_downloadable' => $downloadable,
    ]);
}

it('streams a local lesson resource instead of returning 404', function () {
    $relative = 'lesson-resources/test-view.pdf';
    $path = storage_path('app/public/'.$relative);
    File::ensureDirectoryExists(dirname($path));
    file_put_contents($path, '%PDF-1.4 test resource');

    $resource = lessonResourceFixture('/storage/'.$relative);
    $admin = lessonResourceAdmin();

    $this->actingAs($admin)
        ->get(route('resources.view', $resource))
        ->assertOk()
        ->assertHeader('content-disposition', 'inline');
});

it('blocks download of view-only resources with 403', function () {
    $relative = 'lesson-resources/test-view-only.pdf';
    $path = storage_path('app/public/'.$relative);
    File::ensureDirectoryExists(dirname($path));
    file_put_contents($path, '%PDF-1.4 view only');

    $resource = lessonResourceFixture('/storage/'.$relative, false);
    $admin = lessonResourceAdmin();

    $this->actingAs($admin)
        ->get(route('resources.download', $resource->id))
        ->assertForbidden();
});

it('keeps the existing file when a resource is updated without a new upload', function () {
    $resource = lessonResourceFixture('https://example.r2.cloudflarestorage.com/bucket/original.pdf');

    app(LessonResourceService::class)->resourceUpdate($resource, [
        'title' => 'Renamed PDF',
        'type' => 'document',
        'is_downloadable' => false,
    ]);

    expect($resource->fresh()->resource)->toBe('https://example.r2.cloudflarestorage.com/bucket/original.pdf');
    expect($resource->fresh()->title)->toBe('Renamed PDF');
    expect($resource->fresh()->is_downloadable)->toBeFalse();
});

it('matches truncated resource urls to the completed chunked upload', function () {
    $admin = lessonResourceAdmin();
    $fullUrl = 'https://abc123.r2.cloudflarestorage.com/academy/'.str_repeat('long-path/', 20).'file.pdf';
    $truncated = substr($fullUrl, 0, 255);

    expect(strlen($truncated))->toBe(255);
    expect($truncated)->not->toBe($fullUrl);

    ChunkedUpload::create([
        'user_id' => $admin->id,
        'filename' => 'file.pdf',
        'original_filename' => 'file.pdf',
        'file_url' => $fullUrl,
        'disk' => 's3',
        'key' => 'academy/file.pdf',
        'status' => 'completed',
        'mime_type' => 'application/pdf',
    ]);

    $found = app(ProtectedMediaService::class)->findChunkedUpload($truncated);

    expect($found)->not->toBeNull();
    expect($found->key)->toBe('academy/file.pdf');
});
