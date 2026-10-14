<?php

use App\Http\Requests\UpdateCourseRequest;
use App\Models\Course\Course;
use App\Support\DrywallLanding;
use Illuminate\Database\Schema\Blueprint;
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
        ['quote' => 'Clear lessons.', 'name' => 'Amina'],
    ]);
    expect($request->validated()['countdown_ends_at'])->toBe('2026-11-01 18:00:00');
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

function landingRequest(int $courseId, array $overrides = []): UpdateCourseRequest
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
    ], $overrides));

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
