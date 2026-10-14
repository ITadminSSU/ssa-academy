<?php

use App\Http\Requests\UpdateCourseRequest;
use App\Models\Course\Course;
use App\Models\Course\CourseEnrollment;
use App\Models\Course\CourseLandingPage;
use App\Models\Course\CourseReview;
use App\Models\Course\CourseSection;
use App\Models\Course\SectionLesson;
use App\Models\Instructor;
use App\Models\User;
use App\Services\Course\CourseService;
use App\Support\DrywallLanding;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

beforeEach(function () {
    $this->withoutMiddleware();
});

it('serves the drywall marketing page and thank-you page', function () {
    $this->withoutVite();

    $landing = $this->get('/drywall-estimating');

    $landing->assertOk();
    $landing->assertInertia(fn ($page) => $page
        ->component('drywall-estimating/index')
        ->where('landing.video_duration', '2:36')
        ->where('landing.coach_line', 'Your coach · Professional engineer.')
        ->has('landing.overview', 5)
        ->has('landing.reviews', 3)
        ->where('landing.reviews.0.type', 'written')
        ->where('landing.reviews.0.image', null)
        ->where('landing.embed_html', fn ($html) => is_string($html)
            && str_contains($html, 'https://api.leadconnectorhq.com/widget/form/fyFpuL3CCfqs4YWlCe1V')
            && str_contains($html, 'https://link.msgsndr.com/js/form_embed.js'))
        ->where('canViewCurriculum', false));

    $thanks = $this->get('/drywall-estimating/thank-you');

    $thanks->assertOk();
    $thanks->assertInertia(fn ($page) => $page
        ->component('drywall-estimating/thank-you')
        ->where('enrollUrl', route('category.courses', ['category' => 'all'])));
});

it('points ready to enroll at the published drywall course', function () {
    createDrywallCourseTable();

    Course::query()->create([
        'title' => 'Drywall Estimating',
        'slug' => 'drywall-estimating',
        'status' => 'approved',
        'audience' => 'public',
    ]);

    $page = app(DrywallLanding::class)->publicPage();

    expect($page['enrollUrl'])->toContain('/courses/details/drywall-estimating/')
        ->and($page['course'])->toBeNull();
});

it('hides the curriculum from guests and shows live instructor reviews', function () {
    createDrywallCourseContent();

    $guest = app(DrywallLanding::class)->publicPage();

    expect($guest['canViewCurriculum'])->toBeFalse()
        ->and($guest['course']['sections'])->toBe([])
        ->and(json_encode($guest['course']))->not->toContain('Basic Drywall Introduction')
        ->and(json_encode($guest['course']))->not->toContain('secret-video.mp4')
        ->and($guest['course']['instructor']['total_reviews_count'])->toBe(2)
        ->and((float) $guest['course']['instructor']['total_average_rating'])->toBe(4.5)
        ->and($guest['course']['instructor']['user']['name'])->toBe('Coach Ana')
        ->and($guest['course']['instructor']['courses'][0]['enrollments_count'])->toBe(1);

    $student = User::query()->where('email', 'student@example.com')->first();
    $this->actingAs($student);

    $enrolled = app(DrywallLanding::class)->publicPage();

    expect($enrolled['canViewCurriculum'])->toBeTrue()
        ->and($enrolled['course']['sections'][0]['section_lessons'][0]['title'])->toBe('Basic Drywall Introduction')
        ->and(json_encode($enrolled['course']['sections']))->not->toContain('secret-video.mp4');

    Auth::logout();
});

it('keeps a leadconnector iframe and the official embed script', function () {
    $clean = DrywallLanding::sanitizeEmbed(<<<'HTML'
<iframe src="https://api.leadconnectorhq.com/widget/form/fyFpuL3CCfqs4YWlCe1V" data-form-id="fyFpuL3CCfqs4YWlCe1V" title="Drywall Estimating"></iframe>
<script src="https://evil.example/steal.js"></script>
HTML);

    expect($clean)->toBeString()
        ->toContain('https://api.leadconnectorhq.com/widget/form/fyFpuL3CCfqs4YWlCe1V')
        ->toContain('data-form-id="fyFpuL3CCfqs4YWlCe1V"')
        ->toContain('https://link.msgsndr.com/js/form_embed.js')
        ->not->toContain('evil.example');

    expect(DrywallLanding::sanitizeEmbed('<iframe src="https://evil.example/form"></iframe>'))->toBeNull();
    expect(DrywallLanding::sanitizeEmbed('<iframe src="https://api.leadconnectorhq.com/widget/form/abc" onclick="alert(1)"></iframe>'))->toBeNull();
});

it('accepts landing page settings for the drywall course', function () {
    createDrywallCourseTable();
    $course = Course::query()->create([
        'title' => 'Drywall Estimating',
        'slug' => 'drywall-estimating',
        'status' => 'approved',
        'audience' => 'public',
    ]);

    $request = landingRequest($course->id, [
        'reviews' => [
            ['quote' => 'Clear lessons.', 'name' => 'Amina'],
            ['quote' => '   ', 'name' => '   '],
        ],
    ]);

    $request->validateResolved();

    expect($request->validated()['reviews'])->toBe([
        [
            'type' => 'written',
            'quote' => 'Clear lessons.',
            'name' => 'Amina',
            'existing_image' => '',
            'image' => null,
        ],
    ]);
    expect($request->validated()['countdown_ends_at'])->toBe('2026-11-01 18:00:00');
});

it('keeps older written reviews and image reviews with a stored picture', function () {
    expect(DrywallLanding::normalizeReviews([
        ['quote' => 'Clear lessons.', 'name' => 'Amina'],
        ['type' => 'image', 'name' => 'Luis', 'image' => 'https://cdn.example.com/card.jpg', 'quote' => 'ignore'],
        ['type' => 'image', 'name' => 'No file', 'image' => 'javascript:alert(1)'],
    ]))->toBe([
        ['type' => 'written', 'quote' => 'Clear lessons.', 'name' => 'Amina', 'image' => null],
        ['type' => 'image', 'quote' => '', 'name' => 'Luis', 'image' => 'https://cdn.example.com/card.jpg'],
    ]);
});

it('requires a picture when an image review has none yet', function () {
    createDrywallCourseTable();
    $course = Course::query()->create([
        'title' => 'Drywall Estimating',
        'slug' => 'drywall-estimating',
        'status' => 'approved',
        'audience' => 'public',
    ]);

    $request = landingRequest($course->id, [
        'reviews' => [
            ['type' => 'image', 'name' => 'Amina', 'existing_image' => 'javascript:alert(1)'],
        ],
    ]);

    expect(fn () => $request->validateResolved())->toThrow(ValidationException::class);
});

it('accepts an image review that already has a picture', function () {
    createDrywallCourseTable();
    $course = Course::query()->create([
        'title' => 'Drywall Estimating',
        'slug' => 'drywall-estimating',
        'status' => 'approved',
        'audience' => 'public',
    ]);

    $request = landingRequest($course->id, [
        'reviews' => [
            [
                'type' => 'image',
                'quote' => 'This quote is not shown.',
                'name' => 'Amina',
                'existing_image' => 'https://cdn.example.com/card.jpg',
            ],
            ['quote' => '   ', 'name' => '   '],
        ],
    ]);

    $request->validateResolved();

    expect($request->validated()['reviews'])->toBe([
        [
            'type' => 'image',
            'quote' => '',
            'name' => 'Amina',
            'existing_image' => 'https://cdn.example.com/card.jpg',
            'image' => null,
        ],
    ]);
});

it('stores an uploaded review picture and drops a picture that is no longer used', function () {
    createDrywallCourseTable();
    createDrywallLandingTables();

    $course = Course::query()->create([
        'title' => 'Drywall Estimating',
        'slug' => 'drywall-estimating',
        'status' => 'approved',
        'audience' => 'public',
    ]);

    $service = app(CourseService::class);
    $service->updateCourse((string) $course->id, [
        'tab' => 'landing',
        'countdown_ends_at' => '2026-11-01 18:00:00',
        'video_url' => 'https://www.youtube.com/watch?v=abcdefghijk',
        'video_duration' => '2:36',
        'coach_line' => 'Your coach · Professional engineer.',
        'embed_code' => DrywallLanding::DEFAULT_EMBED,
        'hero_photo' => tinyJpegUpload('hero.jpg'),
        'reviews' => [
            [
                'type' => 'image',
                'quote' => 'leftover',
                'name' => 'Amina',
                'existing_image' => '',
                'image' => tinyJpegUpload('first.jpg'),
            ],
            [
                'type' => 'written',
                'quote' => 'Clear lessons.',
                'name' => 'Luis',
                'existing_image' => '',
                'image' => null,
            ],
        ],
    ]);

    $landing = CourseLandingPage::query()->where('course_id', $course->id)->firstOrFail();
    $storedImage = $landing->reviews[0]['image'];

    expect($landing->reviews[0])->toMatchArray([
        'type' => 'image',
        'quote' => '',
        'name' => 'Amina',
    ])
        ->and($storedImage)->toBeString()->not->toBe('')
        ->and($landing->getMedia()->filter(fn ($media) => str_starts_with((string) $media->getCustomProperty('name'), 'review-')))->toHaveCount(1)
        ->and($landing->reviews[1])->toBe([
            'type' => 'written',
            'quote' => 'Clear lessons.',
            'name' => 'Luis',
            'image' => null,
        ])
        ->and($landing->hero_photo)->toBeString()->not->toBe('');

    $service->updateCourse((string) $course->id, [
        'tab' => 'landing',
        'countdown_ends_at' => '2026-11-01 18:00:00',
        'video_url' => 'https://www.youtube.com/watch?v=abcdefghijk',
        'video_duration' => '2:36',
        'coach_line' => 'Your coach · Professional engineer.',
        'embed_code' => DrywallLanding::DEFAULT_EMBED,
        'reviews' => [
            [
                'type' => 'written',
                'quote' => 'Now a quote.',
                'name' => 'Amina',
                'existing_image' => $storedImage,
                'image' => null,
            ],
        ],
    ]);

    $landing->refresh();
    $landing->unsetRelation('media');

    expect($landing->reviews)->toBe([
        [
            'type' => 'written',
            'quote' => 'Now a quote.',
            'name' => 'Amina',
            'image' => null,
        ],
    ])
        ->and($landing->hero_photo)->not->toBe('')
        ->and($landing->getMedia()->filter(fn ($media) => str_starts_with((string) $media->getCustomProperty('name'), 'review-')))->toHaveCount(0)
        ->and($landing->getMedia()->filter(fn ($media) => $media->getCustomProperty('name') === 'hero'))->toHaveCount(1);
});

it('rejects a landing embed that is not a gohighlevel form', function () {
    createDrywallCourseTable();
    $course = Course::query()->create([
        'title' => 'Drywall Estimating',
        'slug' => 'drywall-estimating',
        'status' => 'approved',
        'audience' => 'public',
    ]);

    $request = landingRequest($course->id, [
        'embed_code' => '<iframe src="https://example.com/form"></iframe>',
    ]);

    expect(fn () => $request->validateResolved())->toThrow(ValidationException::class);
});

it('does not open landing settings on another course', function () {
    createDrywallCourseTable();
    $course = Course::query()->create([
        'title' => 'Other',
        'slug' => 'other-course',
        'status' => 'approved',
        'audience' => 'public',
    ]);

    $request = landingRequest($course->id);

    expect(fn () => $request->validateResolved())->toThrow(NotFoundHttpException::class);
});

function landingRequest(int $courseId, array $overrides = [], array $files = []): UpdateCourseRequest
{
    $request = UpdateCourseRequest::create('/courses/'.$courseId, 'POST', array_merge([
        'tab' => 'landing',
        'countdown_ends_at' => '2026-11-01T18:00',
        'video_url' => 'https://www.youtube.com/watch?v=abcdefghijk',
        'video_duration' => '2:36',
        'coach_line' => 'Your coach · Professional engineer.',
        'embed_code' => DrywallLanding::DEFAULT_EMBED,
        'reviews' => [
            ['quote' => 'A useful takeoff walkthrough.', 'name' => 'Student review'],
        ],
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

function createDrywallCourseContent(): void
{
    foreach ([
        'course_reviews',
        'course_enrollments',
        'section_lessons',
        'section_quizzes',
        'course_sections',
        'course_faqs',
        'course_requirements',
        'course_outcomes',
        'media',
        'courses',
        'instructors',
        'users',
    ] as $table) {
        Schema::dropIfExists($table);
    }

    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password')->nullable();
        $table->string('role')->nullable();
        $table->string('photo')->nullable();
        $table->unsignedBigInteger('instructor_id')->nullable();
        $table->timestamps();
    });

    Schema::create('instructors', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id')->nullable();
        $table->text('biography')->nullable();
        $table->json('payout_methods')->nullable();
        $table->timestamps();
    });

    Schema::create('courses', function (Blueprint $table) {
        $table->id();
        $table->string('title')->nullable();
        $table->string('slug')->nullable();
        $table->string('status')->nullable();
        $table->string('audience')->nullable();
        $table->unsignedBigInteger('instructor_id')->nullable();
        $table->timestamp('launch_at')->nullable();
        $table->boolean('allow_staff_preview')->default(true);
        $table->boolean('allow_internal_preview')->default(false);
        $table->timestamps();
    });

    Schema::create('course_enrollments', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->unsignedBigInteger('course_id');
        $table->string('access_status')->nullable();
        $table->timestamps();
    });

    Schema::create('course_reviews', function (Blueprint $table) {
        $table->id();
        $table->text('review')->nullable();
        $table->integer('rating');
        $table->unsignedBigInteger('user_id');
        $table->unsignedBigInteger('course_id');
        $table->timestamps();
    });

    foreach (['course_faqs', 'course_requirements', 'course_outcomes'] as $name) {
        Schema::create($name, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('course_id');
            $table->timestamps();
        });
    }

    Schema::create('course_sections', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('course_id');
        $table->string('title')->nullable();
        $table->integer('sort')->default(0);
        $table->timestamps();
    });

    Schema::create('section_lessons', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('course_id')->nullable();
        $table->unsignedBigInteger('course_section_id');
        $table->string('title')->nullable();
        $table->integer('sort')->default(0);
        $table->string('lesson_type')->nullable();
        $table->string('duration')->nullable();
        $table->unsignedInteger('lesson_number')->nullable();
        $table->text('lesson_src')->nullable();
        $table->timestamps();
    });

    Schema::create('section_quizzes', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('course_section_id');
        $table->string('title')->nullable();
        $table->integer('sort')->default(0);
        $table->string('duration')->nullable();
        $table->timestamps();
    });

    Schema::create('media', function (Blueprint $table) {
        $table->id();
        $table->morphs('model');
        $table->uuid('uuid')->nullable()->unique();
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
        $table->unsignedInteger('order_column')->nullable();
        $table->nullableTimestamps();
    });

    $coach = User::query()->create([
        'name' => 'Coach Ana',
        'email' => 'coach@example.com',
        'password' => 'secret',
        'role' => 'instructor',
    ]);
    $student = User::query()->create([
        'name' => 'Student',
        'email' => 'student@example.com',
        'password' => 'secret',
        'role' => 'student',
    ]);
    $instructor = Instructor::query()->create([
        'user_id' => $coach->id,
        'biography' => 'Professional engineer.',
    ]);
    $course = Course::query()->create([
        'title' => 'Drywall Estimating',
        'slug' => 'drywall-estimating',
        'status' => 'approved',
        'audience' => 'public',
        'instructor_id' => $instructor->id,
    ]);
    $section = CourseSection::query()->create([
        'course_id' => $course->id,
        'title' => 'Introduction',
        'sort' => 1,
    ]);
    SectionLesson::query()->create([
        'course_id' => $course->id,
        'course_section_id' => $section->id,
        'title' => 'Basic Drywall Introduction',
        'sort' => 1,
        'lesson_type' => 'video',
        'duration' => '00:03:35',
        'lesson_src' => 'secret-video.mp4',
    ]);
    CourseEnrollment::query()->create([
        'user_id' => $student->id,
        'course_id' => $course->id,
        'access_status' => 'active',
    ]);
    CourseReview::query()->create([
        'review' => 'Clear lessons.',
        'rating' => 5,
        'user_id' => $student->id,
        'course_id' => $course->id,
    ]);
    CourseReview::query()->create([
        'review' => 'Useful takeoff.',
        'rating' => 4,
        'user_id' => $coach->id,
        'course_id' => $course->id,
    ]);
}


it('accepts a newly uploaded review picture', function () {
    createDrywallCourseTable();
    $course = Course::query()->create([
        'title' => 'Drywall Estimating',
        'slug' => 'drywall-estimating',
        'status' => 'approved',
        'audience' => 'public',
    ]);

    $upload = tinyJpegUpload('card.jpg');
    $request = landingRequest($course->id, [
        'reviews' => [
            ['type' => 'image', 'name' => '', 'existing_image' => ''],
        ],
    ], [
        'reviews' => [
            ['image' => $upload],
        ],
    ]);

    $request->validateResolved();

    expect($request->validated()['reviews'][0]['type'])->toBe('image')
        ->and($request->validated()['reviews'][0]['name'])->toBe('')
        ->and($request->validated()['reviews'][0]['image'])->toBeInstanceOf(UploadedFile::class);
});

function tinyJpegUpload(string $name): UploadedFile
{
    $contents = base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wCEAAkGBxISEhUQEhIVFhUVFRUVFRUVFRUWFxUVFRUYHSggGBolGxUVITEhJSkrLi4uFx8zODMtNygtLisBCgoKDg0OGhAQGy0lHyUtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLf/AABEIAAMABAMBIgACEQEDEQH/xAAbAAACAgMBAAAAAAAAAAAAAAADBAECBQYAB//EAB4QAAICAQUBAAAAAAAAAAAAAAECAxEEEiExQVFh/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwDZo7S0l0qS0pKS0pA//Z');
    $path = tempnam(sys_get_temp_dir(), 'review');
    file_put_contents($path, $contents);

    return new UploadedFile($path, $name, 'image/jpeg', null, true);
}

function createDrywallLandingTables(): void
{
    Schema::dropIfExists('media');
    Schema::dropIfExists('course_landing_pages');

    Schema::create('course_landing_pages', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('course_id')->unique();
        $table->timestamp('countdown_ends_at')->nullable();
        $table->text('hero_photo')->nullable();
        $table->string('video_url', 2048)->nullable();
        $table->string('video_duration', 20)->nullable();
        $table->string('coach_line')->nullable();
        $table->longText('embed_html')->nullable();
        $table->json('reviews')->nullable();
        $table->timestamps();
    });

    Schema::create('media', function (Blueprint $table) {
        $table->id();
        $table->morphs('model');
        $table->uuid('uuid')->nullable()->unique();
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
        $table->unsignedInteger('order_column')->nullable();
        $table->nullableTimestamps();
    });
}

function createDrywallCourseTable(): void
{
    Schema::dropIfExists('courses');
    Schema::create('courses', function (Blueprint $table) {
        $table->id();
        $table->string('title')->nullable();
        $table->string('slug')->nullable();
        $table->string('status')->nullable();
        $table->string('audience')->nullable();
        $table->timestamp('launch_at')->nullable();
        $table->boolean('allow_staff_preview')->default(true);
        $table->boolean('allow_internal_preview')->default(false);
        $table->timestamps();
    });
}
