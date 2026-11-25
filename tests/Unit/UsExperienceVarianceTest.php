<?php

use App\Http\Controllers\Course\UsExperiencePlanController;
use App\Models\Course\Course;
use App\Models\Course\CourseCategory;
use App\Models\Course\UsExperienceAttempt;
use App\Models\Course\UsExperiencePlan;
use App\Models\User;
use App\Services\UsExperience\UsExperienceUnlockService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::dropIfExists('us_experience_attempts');
    Schema::dropIfExists('courses');
    Schema::dropIfExists('users');

    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
        $table->string('email')->nullable();
        $table->string('role')->nullable();
        $table->unsignedBigInteger('instructor_id')->nullable();
        $table->timestamps();
    });

    Schema::create('courses', function (Blueprint $table) {
        $table->id();
        $table->string('title')->nullable();
        $table->boolean('show_us_experience_variance')->default(true);
        $table->timestamps();
    });

    Schema::create('us_experience_attempts', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('us_experience_plan_id');
        $table->unsignedBigInteger('user_id');
        $table->unsignedInteger('attempt_number');
        $table->string('takeoff_pdf_url')->nullable();
        $table->string('boq_xlsx_url')->nullable();
        $table->json('grading_breakdown')->nullable();
        $table->string('status');
        $table->timestamps();
    });
});

afterEach(function () {
    Auth::logout();
});

test('a student payload includes the answer quantity and the result shows variance by default', function () {
    $plans = variancePlans(true);

    expect($plans[0]['latest_attempt']['grading_breakdown'][0]['expected_qty'])->toBe(228011.25)
        ->and($plans[0]['attempts'][0]['grading_breakdown'][0]['tolerance'])->toBe(4560.23)
        ->and(file_get_contents(base_path('resources/js/components/exam/quantity-takeoff-breakdown.tsx')))
        ->toContain('showVariance = true')
        ->and(file_get_contents(base_path('resources/js/pages/student/tabs-content/us-experience.tsx')))
        ->toContain('showVariance={course.show_us_experience_variance !== false}');
});

test('turning variance off hides the answer quantities from the student and keeps the stored row', function () {
    $stored = UsExperienceAttempt::query()->create([
        'us_experience_plan_id' => 4,
        'user_id' => 9,
        'attempt_number' => 1,
        'status' => UsExperienceAttempt::STATUS_PASSED,
        'grading_breakdown' => varianceBreakdown(),
    ]);

    $plans = variancePlans(false);
    $line = $plans[0]['latest_attempt']['grading_breakdown'][0];
    $attemptLine = $plans[0]['attempts'][0]['grading_breakdown'][0];

    expect($line)->not->toHaveKey('expected_qty')
        ->and($line)->not->toHaveKey('tolerance')
        ->and($line)->not->toHaveKey('tolerance_percent')
        ->and($line['submitted_qty'])->toBe(228011.25)
        ->and($line['is_correct'])->toBeTrue()
        ->and($attemptLine)->not->toHaveKey('expected_qty')
        ->and($stored->fresh()->grading_breakdown[0]['expected_qty'])->toBe(228011.25);
});

test('the trainer review still receives the answer quantity when students cannot see variance', function () {
    $attempt = new UsExperienceAttempt([
        'grading_breakdown' => varianceBreakdown(),
        'status' => UsExperienceAttempt::STATUS_PASSED,
    ]);

    $trainer = $attempt->toTrainerArray(true);

    expect($trainer['grading_breakdown'][0]['expected_qty'])->toBe(228011.25)
        ->and(file_get_contents(base_path('resources/js/pages/dashboard/courses/us-experience/attempt-review.tsx')))
        ->not->toContain('showVariance');
});

test('quiz and exam breakdowns keep the variance column', function () {
    $files = [
        'resources/js/pages/course-player/partials/takeoff-quiz-viewer.tsx',
        'resources/js/pages/student/partials/quiz-result-dialog.tsx',
        'resources/js/components/exam/question-answer-result.tsx',
        'resources/js/pages/student/tabs-content/exam-result.tsx',
        'resources/js/pages/dashboard/exams/partials/exam-attempt-review.tsx',
    ];

    foreach ($files as $file) {
        expect(file_get_contents(base_path($file)))->not->toContain('showVariance');
    }
});

test('saving the variance switch updates only that flag', function () {
    $admin = User::query()->create(['name' => 'Admin', 'email' => 'admin@example.com', 'role' => 'admin']);
    $course = Course::query()->create([
        'title' => 'Drywall Estimating',
        'show_us_experience_variance' => true,
    ]);
    $category = new CourseCategory;
    $category->forceFill(['slug' => 'estimating', 'title' => 'Estimating']);
    $course->setRelation('course_category', $category);

    Auth::login($admin);
    $request = Request::create('/courses/'.$course->id.'/us-experience/variance', 'PUT', [
        'show_us_experience_variance' => false,
    ]);
    $request->headers->set('referer', '/dashboard/courses/'.$course->id.'/edit');
    $request->setLaravelSession(app('session.store'));

    app(UsExperiencePlanController::class)->updateVariance($request, $course);

    $fresh = $course->fresh();

    expect($fresh->show_us_experience_variance)->toBeFalse()
        ->and($fresh->title)->toBe('Drywall Estimating');
});

test('duplicating a course keeps the variance switch', function () {
    $source = new Course([
        'title' => 'Drywall Estimating',
        'show_us_experience_variance' => false,
    ]);

    $copy = $source->replicate([
        'stripe_product_id',
        'stripe_price_id',
        'stripe_ongoing_price_id',
    ]);

    expect($copy->show_us_experience_variance)->toBeFalse();
});

function variancePlans(bool $showVariance): array
{
    $plan = new UsExperiencePlan([
        'title' => 'Plan 1',
        'group_name' => 'Level 1',
        'group_description' => null,
        'sort_order' => 1,
        'pass_mark' => 85,
        'max_attempts' => 3,
    ]);
    $plan->id = 4;

    $attempt = new UsExperienceAttempt([
        'us_experience_plan_id' => 4,
        'attempt_number' => 1,
        'status' => UsExperienceAttempt::STATUS_PASSED,
        'lines_percent' => 100,
        'lines_correct' => 1,
        'lines_total' => 1,
        'grading_breakdown' => varianceBreakdown(),
    ]);

    return app(UsExperienceUnlockService::class)->studentPlanPayloads(
        collect([$plan]),
        collect([4 => collect([$attempt])]),
        true,
        true,
        $showVariance,
    );
}

function varianceBreakdown(): array
{
    return [[
        'key' => 'asphalt',
        'item' => '2" Asphalt Pavement',
        'unit' => 'SF',
        'expected_qty' => 228011.25,
        'submitted_qty' => 228011.25,
        'within_tolerance' => true,
        'is_correct' => true,
        'tolerance' => 4560.23,
        'tolerance_percent' => 2.0,
    ]];
}
