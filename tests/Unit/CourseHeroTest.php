<?php

use App\Http\Requests\ChunkInitiateRequest;
use App\Http\Requests\UpdateCourseRequest;
use App\Models\ChunkedUpload;
use App\Models\Course\Course;
use App\Models\Course\CourseCategory;
use App\Services\Course\CourseService;
use App\Support\CourseHero;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->withoutMiddleware();
});

it('shows each course its own hero and falls back when fields are blank', function () {
    $filled = new Course([
        'title' => 'Roofing Estimating',
        'short_description' => 'Short roofing description',
        'hero_eyebrow' => 'Learn a high-demand skill',
        'hero_headline' => 'Advance Your Roofing Estimating Skills for U.S. Construction',
        'hero_highlights' => ['U.S. Construction', 'Not in the headline'],
        'hero_paragraph' => 'A paragraph written for this course.',
        'hero_photo' => 'https://cdn.example.com/roofing-hero.jpg',
        'hero_video' => 'https://www.youtube.com/watch?v=abcdefghijk',
    ]);
    $filled->setRelation('course_category', new CourseCategory(['title' => 'Estimating']));

    $blank = new Course([
        'title' => 'Electrical Estimating',
        'short_description' => 'Short electrical description',
        'banner' => 'https://cdn.example.com/electrical-banner.jpg',
        'preview' => 'https://cdn.example.com/electrical-preview.mp4',
    ]);
    $blank->setRelation('course_category', new CourseCategory(['title' => 'Electrical']));

    $roofing = CourseHero::present($filled);
    $electrical = CourseHero::present($blank);

    expect($roofing['eyebrow'])->toBe('Learn a high-demand skill')
        ->and($roofing['headline'])->toBe('Advance Your Roofing Estimating Skills for U.S. Construction')
        ->and($roofing['highlights'])->toBe(['U.S. Construction'])
        ->and($roofing['paragraph'])->toBe('A paragraph written for this course.')
        ->and($roofing['photo'])->toBe('https://cdn.example.com/roofing-hero.jpg')
        ->and($roofing['video'])->toBe('https://www.youtube.com/watch?v=abcdefghijk')
        ->and($electrical['eyebrow'])->toBe('Electrical')
        ->and($electrical['headline'])->toBe('Electrical Estimating')
        ->and($electrical['highlights'])->toBe([])
        ->and($electrical['paragraph'])->toBe('Short electrical description')
        ->and($electrical['photo'])->toBe('https://cdn.example.com/electrical-banner.jpg')
        ->and($electrical['video'])->toBe('https://cdn.example.com/electrical-preview.mp4');
});

it('keeps the current hero photo and video when the form has no new file', function () {
    createCourseHeroTable();

    $course = Course::query()->create([
        'title' => 'Roofing Estimating',
        'slug' => 'roofing-estimating',
        'short_description' => 'Short description',
        'price' => 99,
        'hero_photo' => 'https://cdn.example.com/roofing-hero.jpg',
        'hero_video' => 'https://cdn.example.com/lessons/intro.mp4',
        'preview' => 'https://cdn.example.com/media-preview.mp4',
    ]);

    app(CourseService::class)->updateCourse((string) $course->id, [
        'tab' => 'hero',
        'hero_eyebrow' => 'Learn a high-demand skill',
        'hero_headline' => 'Advance Your Roofing Estimating Skills for U.S. Construction',
        'hero_highlights' => ['U.S. Construction'],
        'hero_paragraph' => 'Written for roofing.',
        'hero_video_source' => 'upload',
        'hero_video_url' => '',
        'hero_video_stored' => '',
        'hero_photo' => null,
    ]);

    $fresh = Course::query()->findOrFail($course->id);

    expect($fresh->getRawOriginal('hero_photo'))->toBe('https://cdn.example.com/roofing-hero.jpg')
        ->and($fresh->getRawOriginal('hero_video'))->toBe('https://cdn.example.com/lessons/intro.mp4')
        ->and($fresh->price)->toBe(99)
        ->and($fresh->hero_headline)->toBe('Advance Your Roofing Estimating Skills for U.S. Construction');
});

it('refuses a preview video over 5 GB and leaves the previous video in place', function () {
    createCourseHeroTable();
    createCourseHeroUploadTable();

    $course = Course::query()->create([
        'title' => 'Roofing Estimating',
        'slug' => 'roofing-estimating',
        'hero_video' => 'https://cdn.example.com/lessons/current.mp4',
    ]);

    ChunkedUpload::query()->create([
        'user_id' => 1,
        'filename' => 'huge.mp4',
        'original_filename' => 'huge.mp4',
        'file_path' => 'lessons/huge.mp4',
        'file_url' => 'https://cdn.example.com/lessons/huge.mp4',
        'disk' => 's3',
        'mime_type' => 'video/mp4',
        'size' => CourseHero::MAX_VIDEO_KILOBYTES + 1,
        'key' => 'https://cdn.example.com/lessons/huge.mp4',
        'status' => 'completed',
    ]);

    expect(fn () => app(CourseService::class)->updateCourse((string) $course->id, [
        'tab' => 'hero',
        'hero_video_source' => 'upload',
        'hero_video_url' => '',
        'hero_video_stored' => 'https://cdn.example.com/lessons/huge.mp4',
    ]))->toThrow(ValidationException::class);

    expect(Course::query()->findOrFail($course->id)->getRawOriginal('hero_video'))
        ->toBe('https://cdn.example.com/lessons/current.mp4');
});

it('rejects a preview video over 5 GB when the chunked upload starts', function () {
    request()->merge(['filetype' => 'video']);

    $validator = validator([
        'filename' => 'huge.mp4',
        'filesize' => CourseHero::MAX_VIDEO_KILOBYTES + 1,
        'mimetype' => 'video/mp4',
        'filetype' => 'video',
        'total_chunks' => 1,
    ], (new ChunkInitiateRequest)->rules(), (new ChunkInitiateRequest)->messages());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('filesize'))->toContain('5 GB');
});

it('stores a youtube link and rejects any other link without clearing the saved video', function () {
    createCourseHeroTable();

    $course = Course::query()->create([
        'title' => 'Drywall Estimating',
        'slug' => 'drywall-estimating',
        'hero_video' => 'https://cdn.example.com/lessons/current.mp4',
    ]);

    app(CourseService::class)->updateCourse((string) $course->id, [
        'tab' => 'hero',
        'hero_video_source' => 'link',
        'hero_video_url' => 'https://www.youtube.com/watch?v=abcdefghijk',
        'hero_video_stored' => '',
    ]);

    expect(Course::query()->findOrFail($course->id)->hero_video)
        ->toBe('https://www.youtube.com/watch?v=abcdefghijk');

    expect(fn () => app(CourseService::class)->updateCourse((string) $course->id, [
        'tab' => 'hero',
        'hero_video_source' => 'link',
        'hero_video_url' => 'https://cdn.example.com/not-a-host.mp4',
        'hero_video_stored' => '',
    ]))->toThrow(ValidationException::class);

    expect(Course::query()->findOrFail($course->id)->hero_video)
        ->toBe('https://www.youtube.com/watch?v=abcdefghijk');
});

it('accepts hero copy and only jpg png or webp photos', function () {
    $request = UpdateCourseRequest::create('/courses/1', 'POST', [
        'tab' => 'hero',
        'hero_eyebrow' => 'Learn a high-demand skill',
        'hero_headline' => 'Master Drywall Estimating and Get Real-World U.S. Experience',
        'hero_highlights' => "Get\nReal-World U.S. Experience",
        'hero_paragraph' => 'A beginner-friendly course.',
        'hero_video_source' => 'link',
        'hero_video_url' => '',
    ]);
    $request->setContainer(app())->setRedirector(app('redirect'));
    $request->validateResolved();

    expect($request->validated()['hero_highlights'])->toBe(['Get', 'Real-World U.S. Experience']);

    $gif = UploadedFile::fake()->create('hero.gif', 20, 'image/gif');
    $imageRequest = UpdateCourseRequest::create('/courses/1', 'POST', [
        'tab' => 'hero',
        'hero_video_source' => 'upload',
    ], [], ['hero_photo' => $gif]);
    $imageRequest->setContainer(app())->setRedirector(app('redirect'));

    expect(fn () => $imageRequest->validateResolved())->toThrow(ValidationException::class);
});

function createCourseHeroTable(): void
{
    Schema::dropIfExists('courses');
    Schema::create('courses', function (Blueprint $table) {
        $table->id();
        $table->string('title')->nullable();
        $table->string('slug')->nullable();
        $table->text('short_description')->nullable();
        $table->string('hero_eyebrow')->nullable();
        $table->text('hero_headline')->nullable();
        $table->json('hero_highlights')->nullable();
        $table->text('hero_paragraph')->nullable();
        $table->text('hero_photo')->nullable();
        $table->text('hero_video')->nullable();
        $table->text('banner')->nullable();
        $table->text('thumbnail')->nullable();
        $table->text('preview')->nullable();
        $table->integer('price')->nullable();
        $table->timestamps();
    });
}

function createCourseHeroUploadTable(): void
{
    Schema::dropIfExists('chunked_uploads');
    Schema::create('chunked_uploads', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->string('filename');
        $table->string('original_filename');
        $table->string('file_path')->nullable();
        $table->text('file_url')->nullable();
        $table->string('disk')->default('s3');
        $table->string('mime_type')->nullable();
        $table->bigInteger('size')->default(0);
        $table->string('upload_id')->nullable();
        $table->text('key')->nullable();
        $table->string('status')->default('pending');
        $table->integer('chunks_completed')->default(0);
        $table->integer('total_chunks')->default(0);
        $table->json('metadata')->nullable();
        $table->timestamps();
    });
}
