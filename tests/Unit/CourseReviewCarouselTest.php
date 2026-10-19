<?php

use App\Http\Requests\UpdateCourseRequest;
use App\Models\Course\Course;
use App\Services\Course\CourseService;
use App\Support\CourseReviewCarousel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->withoutMiddleware();
});

it('stores uploaded review images and keeps them when a later save has no new file', function () {
    createReviewCarouselCourseTable();
    createReviewCarouselMediaTable();
    Storage::fake('public');

    $course = Course::query()->create([
        'title' => 'Drywall Estimating',
        'slug' => 'drywall-estimating',
    ]);

    app(CourseService::class)->updateCourse((string) $course->id, [
        'tab' => 'review-carousel',
        'slides' => [
            ['existing_url' => '', 'image' => reviewCarouselImage('first.png')],
            ['existing_url' => '', 'image' => reviewCarouselImage('second.jpg', 'image/jpeg')],
        ],
    ]);

    $withImages = Course::query()->findOrFail($course->id);
    $stored = reviewCarouselStored($withImages);

    expect($stored)->toHaveCount(2)
        ->and($withImages->review_carousel)->toHaveCount(2)
        ->and($withImages->review_carousel[0])->not->toBeEmpty()
        ->and(reviewCarouselMedia($withImages))->toHaveCount(2);

    app(CourseService::class)->updateCourse((string) $course->id, [
        'tab' => 'review-carousel',
        'slides' => [
            ['existing_url' => $stored[0], 'image' => null],
            ['existing_url' => $stored[1], 'image' => null],
        ],
    ]);

    $kept = Course::query()->findOrFail($course->id);

    expect(reviewCarouselStored($kept))->toBe($stored)
        ->and(reviewCarouselMedia($kept))->toHaveCount(2);
});

it('adds and removes one review image without clearing the others', function () {
    createReviewCarouselCourseTable();
    createReviewCarouselMediaTable();
    Storage::fake('public');

    $course = Course::query()->create([
        'title' => 'Drywall Estimating',
        'slug' => 'drywall-estimating',
    ]);
    $service = app(CourseService::class);
    $service->addSingleFile($course, reviewCarouselImage('hero.png'), 'hero-photo');

    $service->updateCourse((string) $course->id, [
        'tab' => 'review-carousel',
        'slides' => [
            ['existing_url' => '', 'image' => reviewCarouselImage('first.png')],
        ],
    ]);

    $first = reviewCarouselStored(Course::query()->findOrFail($course->id));

    $service->updateCourse((string) $course->id, [
        'tab' => 'review-carousel',
        'slides' => [
            ['existing_url' => $first[0], 'image' => null],
            ['existing_url' => '', 'image' => reviewCarouselImage('second.png')],
        ],
    ]);

    $both = Course::query()->findOrFail($course->id);
    $stored = reviewCarouselStored($both);

    expect($stored)->toHaveCount(2)
        ->and($stored[0])->toBe($first[0])
        ->and(reviewCarouselMedia($both))->toHaveCount(2)
        ->and($both->getMedia('*', ['name' => 'hero-photo']))->toHaveCount(1);

    $service->updateCourse((string) $course->id, [
        'tab' => 'review-carousel',
        'slides' => [
            ['existing_url' => $stored[1], 'image' => null],
        ],
    ]);

    $remaining = Course::query()->findOrFail($course->id);

    expect(reviewCarouselStored($remaining))->toBe([$stored[1]])
        ->and(reviewCarouselMedia($remaining))->toHaveCount(1)
        ->and($remaining->getMedia('*', ['name' => 'hero-photo']))->toHaveCount(1);
});

it('saves review images in the submitted order', function () {
    createReviewCarouselCourseTable();
    createReviewCarouselMediaTable();
    Storage::fake('public');

    $course = Course::query()->create([
        'title' => 'Drywall Estimating',
        'slug' => 'drywall-estimating',
    ]);
    $service = app(CourseService::class);

    $service->updateCourse((string) $course->id, [
        'tab' => 'review-carousel',
        'slides' => [
            ['existing_url' => '', 'image' => reviewCarouselImage('first.png')],
            ['existing_url' => '', 'image' => reviewCarouselImage('second.png')],
        ],
    ]);

    $stored = reviewCarouselStored(Course::query()->findOrFail($course->id));

    $service->updateCourse((string) $course->id, [
        'tab' => 'review-carousel',
        'slides' => [
            ['existing_url' => $stored[1], 'image' => null],
            ['existing_url' => $stored[0], 'image' => null],
        ],
    ]);

    expect(reviewCarouselStored(Course::query()->findOrFail($course->id)))->toBe([$stored[1], $stored[0]]);
});

it('replaces one review image and leaves the saved images when the upload is rejected', function () {
    createReviewCarouselCourseTable();
    createReviewCarouselMediaTable();
    Storage::fake('public');

    $course = Course::query()->create([
        'title' => 'Drywall Estimating',
        'slug' => 'drywall-estimating',
    ]);

    app(CourseService::class)->updateCourse((string) $course->id, [
        'tab' => 'review-carousel',
        'slides' => [
            ['existing_url' => '', 'image' => reviewCarouselImage('first.png')],
            ['existing_url' => '', 'image' => reviewCarouselImage('second.png')],
        ],
    ]);

    $stored = reviewCarouselStored(Course::query()->findOrFail($course->id));
    $gif = UploadedFile::fake()->create('review.gif', 20, 'image/gif');
    $request = reviewCarouselRequest($course->id, [
        'slides' => [
            ['existing_url' => $stored[0]],
            ['existing_url' => $stored[1]],
        ],
    ], [
        'slides' => [
            1 => ['image' => $gif],
        ],
    ]);

    try {
        $request->validateResolved();
        $this->fail('A GIF should be rejected.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('slides.1.image')
            ->and($exception->errors()['slides.1.image'][0])->toBe('The review image must be a JPG, PNG, or WebP image.');
    }

    $unchanged = Course::query()->findOrFail($course->id);

    expect(reviewCarouselStored($unchanged))->toBe($stored)
        ->and(reviewCarouselMedia($unchanged))->toHaveCount(2);

    app(CourseService::class)->updateCourse((string) $course->id, [
        'tab' => 'review-carousel',
        'slides' => [
            ['existing_url' => $stored[0], 'image' => null],
            ['existing_url' => $stored[1], 'image' => reviewCarouselImage('replacement.jpg', 'image/jpeg')],
        ],
    ]);

    $replaced = Course::query()->findOrFail($course->id);
    $next = reviewCarouselStored($replaced);

    expect($next)->toHaveCount(2)
        ->and($next[0])->toBe($stored[0])
        ->and($next[1])->not->toBe($stored[1])
        ->and(reviewCarouselMedia($replaced))->toHaveCount(2);
});

it('rejects a review image that is not already saved on the course', function () {
    createReviewCarouselCourseTable();

    $course = Course::query()->create([
        'title' => 'Drywall Estimating',
        'slug' => 'drywall-estimating',
        'review_carousel' => ['https://cdn.example.com/saved.jpg'],
    ]);
    $original = $course->getRawOriginal('review_carousel');
    $request = reviewCarouselRequest($course->id, [
        'slides' => [
            ['existing_url' => 'https://evil.example/stolen.jpg'],
        ],
    ]);

    try {
        $request->validateResolved();
        $this->fail('An unknown review image should be rejected.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('slides.0.existing_url');
    }

    expect(Course::query()->findOrFail($course->id)->getRawOriginal('review_carousel'))->toBe($original);
});

it('keeps saved review images when the form submits their urls and no new file', function () {
    createReviewCarouselCourseTable();

    $course = Course::query()->create([
        'title' => 'Drywall Estimating',
        'slug' => 'drywall-estimating',
        'review_carousel' => [
            'https://cdn.example.com/one.jpg',
            'https://cdn.example.com/two.jpg',
        ],
    ]);

    $request = reviewCarouselRequest($course->id, [
        'slides' => [
            ['existing_url' => 'https://cdn.example.com/two.jpg'],
            ['existing_url' => 'https://cdn.example.com/one.jpg'],
        ],
    ]);
    $request->validateResolved();

    expect($request->validated()['slides'])->toBe([
        ['existing_url' => 'https://cdn.example.com/two.jpg', 'image' => null],
        ['existing_url' => 'https://cdn.example.com/one.jpg', 'image' => null],
    ]);

    expect($course->fresh()->review_carousel)->toBe([
        'https://cdn.example.com/one.jpg',
        'https://cdn.example.com/two.jpg',
    ]);
});

function reviewCarouselImage(string $name, string $mime = 'image/png'): UploadedFile
{
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
    $jpeg = base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wCEAAkGBxISEhUQEhIVFhUVFRUYFxUXGBYYFxUYHBcYGBYVHSggGBolGxUVITEhJSkrLi4uFx8zODMtNygtLisBCgoKDg0OGhAQGy0lHyUtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLf/AABEIAAMABAMBIgACEQEDEQH/xAAbAAACAwEBAQAAAAAAAAAAAAADBAIFAAEGBwf/xAAUAQEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAH/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCfABj/2Q==');

    return UploadedFile::fake()->createWithContent($name, $mime === 'image/jpeg' ? $jpeg : $png);
}

function reviewCarouselRequest(int $courseId, array $overrides = [], array $files = []): UpdateCourseRequest
{
    $request = UpdateCourseRequest::create('/courses/'.$courseId, 'POST', array_merge([
        'tab' => 'review-carousel',
        'slides' => [],
    ], $overrides), [], $files);

    $request->setRouteResolver(function () use ($courseId) {
        return new class($courseId)
        {
            public function __construct(private int $courseId) {}

            public function parameter(string $name, mixed $default = null): mixed
            {
                return $name === 'id' ? $this->courseId : $default;
            }
        };
    });

    $request->headers->set('Accept', 'application/json');
    $request->setContainer(app());
    $request->setRedirector(app('redirect'));

    return $request;
}

function reviewCarouselStored(Course $course): array
{
    return CourseReviewCarousel::storedUrls($course->getRawOriginal('review_carousel'));
}

function reviewCarouselMedia(Course $course)
{
    $course->unsetRelation('media');

    return $course->getMedia()->filter(
        fn ($media) => str_starts_with((string) $media->getCustomProperty('name'), 'review-carousel-'),
    );
}

function createReviewCarouselCourseTable(): void
{
    Schema::dropIfExists('courses');
    Schema::create('courses', function (Blueprint $table) {
        $table->id();
        $table->string('title')->nullable();
        $table->string('slug')->nullable();
        $table->text('review_carousel')->nullable();
        $table->timestamps();
    });
}

function createReviewCarouselMediaTable(): void
{
    Schema::dropIfExists('media');
    Schema::create('media', function (Blueprint $table) {
        $table->id();
        $table->morphs('model', 'ssu_review_media_morph_idx');
        $table->uuid()->nullable()->unique();
        $table->string('collection_name');
        $table->string('name');
        $table->string('file_name');
        $table->string('mime_type')->nullable();
        $table->string('disk');
        $table->string('conversions_disk')->nullable();
        $table->unsignedBigInteger('size');
        $table->json('manipulations');
        $table->json('custom_properties');
        $table->json('generated_conversions');
        $table->json('responsive_images');
        $table->unsignedInteger('order_column')->nullable()->index();
        $table->nullableTimestamps();
    });
}
