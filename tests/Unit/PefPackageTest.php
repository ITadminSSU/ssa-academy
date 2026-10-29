<?php

use App\Enums\EnrollmentAccessStatus;
use App\Mail\PefPackageMail;
use App\Models\Course\Course;
use App\Models\Course\CourseEnrollment;
use App\Models\Setting;
use App\Models\User;
use App\Observers\CourseEnrollmentObserver;
use App\Services\Chat\ChatService;
use App\Services\Course\PefEnrollmentService;
use App\Services\Payment\SubscriptionAccessService;
use App\Services\UserService;
use App\Support\TransactionalMailSender;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Schema::disableForeignKeyConstraints();

    foreach ([
        'section_quizzes',
        'section_lessons',
        'course_enrollments',
        'courses',
        'settings',
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
        $table->string('user_type')->nullable();
        $table->integer('status')->nullable();
        $table->string('candidate_status')->nullable();
        $table->boolean('pef_tagged')->default(false);
        $table->timestamp('pef_tagged_at')->nullable();
        $table->timestamp('pef_access_ends_at')->nullable();
        $table->timestamp('legal_agreement_accepted_at')->nullable();
        $table->timestamps();
    });

    Schema::create('settings', function (Blueprint $table) {
        $table->id();
        $table->string('type');
        $table->string('sub_type')->nullable();
        $table->string('title')->nullable();
        $table->json('fields')->nullable();
        $table->timestamps();
    });

    Schema::create('courses', function (Blueprint $table) {
        $table->id();
        $table->string('title')->nullable();
        $table->string('slug')->nullable();
        $table->string('status')->nullable();
        $table->string('billing_model')->nullable();
        $table->string('expiry_type')->nullable();
        $table->string('expiry_duration')->nullable();
        $table->timestamp('launch_at')->nullable();
        $table->timestamps();
    });

    Schema::create('course_enrollments', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->unsignedBigInteger('course_id');
        $table->string('enrollment_type')->nullable();
        $table->boolean('pef_granted')->default(false);
        $table->string('access_status')->nullable();
        $table->timestamp('entry_date')->nullable();
        $table->timestamp('expiry_date')->nullable();
        $table->timestamp('suspended_at')->nullable();
        $table->timestamp('forfeited_at')->nullable();
        $table->timestamp('welcome_email_sent_at')->nullable();
        $table->timestamps();
    });

    Schema::create('section_lessons', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('course_id')->nullable();
        $table->timestamps();
    });

    Schema::create('section_quizzes', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('course_id')->nullable();
        $table->timestamps();
    });

    CourseEnrollment::flushEventListeners();
    CourseEnrollment::observe(CourseEnrollmentObserver::class);

    $chat = Mockery::mock(ChatService::class);
    $chat->shouldReceive('syncEnrollmentAccess')->zeroOrMoreTimes();
    app()->instance(ChatService::class, $chat);

    $this->pefMails = 0;
    $mail = Mockery::mock(TransactionalMailSender::class);
    $mail->shouldReceive('send')->andReturnUsing(function ($user, $mailable) {
        if ($mailable instanceof PefPackageMail) {
            $this->pefMails++;
            expect($mailable->courses)->not->toBeEmpty();
        }

        return true;
    });
    app()->instance(TransactionalMailSender::class, $mail);

    Http::preventStrayRequests();
});

afterEach(function () {
    Carbon::setTestNow();
    CourseEnrollment::flushEventListeners();
    CourseEnrollment::observe(CourseEnrollmentObserver::class);
});

it('enrolls an external student in the package and leaves other access unchanged', function () {
    Carbon::setTestNow('2026-01-15 08:00:00');

    $drywall = pefCourse('Drywall Estimating');
    $roofing = pefCourse('Roofing Estimating');
    $siding = pefCourse('Siding Estimating');
    $resume = pefCourse('Build Your Winning Resume');
    $paidOnly = pefCourse('Paid Only Course');
    $setting = pefSettings([$drywall->id, $roofing->id, $siding->id], $resume->id, '2 years');

    $student = pefStudent('PEF Student', 'pef@example.com');
    $other = pefStudent('Other Student', 'other@example.com');

    $paid = CourseEnrollment::create([
        'user_id' => $student->id,
        'course_id' => $drywall->id,
        'enrollment_type' => 'paid',
        'access_status' => EnrollmentAccessStatus::ACTIVE->value,
        'entry_date' => now(),
        'pef_granted' => false,
    ]);
    $otherEnrollment = CourseEnrollment::create([
        'user_id' => $other->id,
        'course_id' => $paidOnly->id,
        'enrollment_type' => 'paid',
        'access_status' => EnrollmentAccessStatus::ACTIVE->value,
        'entry_date' => now(),
        'expiry_date' => now()->subDay(),
        'pef_granted' => false,
    ]);

    app(UserService::class)->updateUser($student->id, [
        'name' => 'PEF Student',
        'email' => $student->email,
        'status' => 1,
        'user_type' => 'external',
        'pef_tagged' => true,
    ]);

    $student = $student->fresh();
    $drywallEnrollment = CourseEnrollment::query()->findOrFail($paid->id);
    $roofingEnrollment = pefEnrollment($student->id, $roofing->id);
    $sidingEnrollment = pefEnrollment($student->id, $siding->id);
    $resumeEnrollment = pefEnrollment($student->id, $resume->id);

    expect($student->pef_tagged)->toBeTrue()
        ->and($student->pef_tagged_at->toDateTimeString())->toBe('2026-01-15 08:00:00')
        ->and($student->pef_access_ends_at->toDateTimeString())->toBe('2028-01-15 08:00:00')
        ->and($drywallEnrollment->enrollment_type)->toBe('paid')
        ->and($drywallEnrollment->pef_granted)->toBeFalse()
        ->and($roofingEnrollment->pef_granted)->toBeTrue()
        ->and($roofingEnrollment->enrollment_type)->toBe('free')
        ->and($roofingEnrollment->welcome_email_sent_at)->toBeNull()
        ->and($sidingEnrollment->pef_granted)->toBeTrue()
        ->and($resumeEnrollment->pef_granted)->toBeFalse()
        ->and($resumeEnrollment->enrollment_type)->toBe('free')
        ->and($this->pefMails)->toBe(1)
        ->and(CourseEnrollment::query()->where('user_id', $student->id)->where('course_id', $drywall->id)->count())->toBe(1);

    $access = app(SubscriptionAccessService::class);
    expect($access->getAccessMode($student, $roofing, $roofingEnrollment))->toBe('full')
        ->and($access->getAccessMode($student, $drywall, $drywallEnrollment))->toBe('full')
        ->and($access->getAccessMode($other, $paidOnly, $otherEnrollment))->toBe('full');

    Carbon::setTestNow('2026-06-01 08:00:00');
    $setting->update([
        'fields' => array_merge($setting->fresh()->fields, [
            'pef_access_duration' => '1 year',
        ]),
    ]);

    app(PefEnrollmentService::class)->syncTag($student->fresh(), true);

    expect($student->fresh()->pef_access_ends_at->toDateTimeString())->toBe('2028-01-15 08:00:00')
        ->and(CourseEnrollment::query()->where('user_id', $student->id)->where('course_id', $roofing->id)->count())->toBe(1)
        ->and($this->pefMails)->toBe(1);

    $later = pefStudent('Later Student', 'later@example.com');
    app(PefEnrollmentService::class)->syncTag($later, true);

    expect($later->fresh()->pef_access_ends_at->toDateTimeString())->toBe('2027-06-01 08:00:00');

    Carbon::setTestNow('2028-01-16 08:00:00');
    $student = $student->fresh();
    $roofingEnrollment = $roofingEnrollment->fresh();
    $history = new \App\Models\Course\WatchHistory([
        'completed_watching' => [
            ['id' => '10', 'type' => 'lesson'],
        ],
    ]);

    expect($access->getAccessMode($student, $roofing, $roofingEnrollment))->toBe('completed_only')
        ->and($access->canAccessItem($student, $roofing, 10, 'lesson', $history, $roofingEnrollment))->toBeTrue()
        ->and($access->canAccessItem($student, $roofing, 99, 'lesson', $history, $roofingEnrollment))->toBeFalse()
        ->and($access->canSubmitAssignments($student, $roofing, $roofingEnrollment))->toBeFalse()
        ->and($access->getAccessMode($student, $drywall, $drywallEnrollment->fresh()))->toBe('full')
        ->and($access->getAccessMode($student, $resume, $resumeEnrollment->fresh()))->toBe('full')
        ->and($access->getAccessMode($other->fresh(), $paidOnly, $otherEnrollment->fresh()))->toBe('full')
        ->and($access->toFrontendPayload($student, $roofing, $roofingEnrollment)['can_resubscribe'])->toBeFalse();

    app(PefEnrollmentService::class)->syncTag($student, false);
    $student = $student->fresh();

    expect($student->pef_tagged)->toBeFalse()
        ->and($student->pef_access_ends_at->toDateTimeString())->toBe('2028-01-15 08:00:00')
        ->and(CourseEnrollment::query()->whereKey($roofingEnrollment->id)->exists())->toBeTrue();
});

it('rejects a PEF tag for an employee and does not change that account', function () {
    $employee = pefStudent('Staff Member', 'staff@example.com', 'employee');

    $exception = null;

    try {
        app(UserService::class)->updateUser($employee->id, [
            'name' => 'Changed Name',
            'email' => $employee->email,
            'status' => 1,
            'user_type' => 'employee',
            'pef_tagged' => true,
        ]);
    } catch (ValidationException $caught) {
        $exception = $caught;
    }

    expect($exception)->not->toBeNull()
        ->and($exception->errors()['pef_tagged'][0])->toBe('Only an external student can be tagged PEF.')
        ->and($employee->fresh()->name)->toBe('Staff Member')
        ->and($employee->fresh()->pef_tagged)->toBeFalse()
        ->and(CourseEnrollment::query()->count())->toBe(0);
});

it('does not enroll anyone while the PEF package is off', function () {
    pefCourse('Drywall Estimating');
    pefSettings([1], null, '2 years', enabled: false);
    $student = pefStudent('External Student', 'off@example.com');

    $exception = null;

    try {
        app(PefEnrollmentService::class)->syncTag($student, true);
    } catch (ValidationException $caught) {
        $exception = $caught;
    }

    expect($exception)->not->toBeNull()
        ->and($exception->errors()['pef_tagged'][0])->toBe('Turn on the PEF package and choose its courses before tagging a student.')
        ->and($student->fresh()->pef_tagged)->toBeFalse()
        ->and(CourseEnrollment::query()->count())->toBe(0)
        ->and($this->pefMails)->toBe(0);
});

function pefStudent(string $name, string $email, string $userType = 'external'): User
{
    return User::create([
        'name' => $name,
        'email' => $email,
        'password' => 'password',
        'role' => 'student',
        'user_type' => $userType,
        'status' => 1,
    ]);
}

function pefCourse(string $title): Course
{
    return Course::create([
        'title' => $title,
        'slug' => str($title)->slug().'-'.uniqid(),
        'status' => 'approved',
        'billing_model' => 'one_time',
        'expiry_type' => 'lifetime',
    ]);
}

/**
 * @param  list<int>  $courseIds
 */
function pefSettings(array $courseIds, ?int $companionCourseId, string $duration, bool $enabled = true): Setting
{
    return Setting::create([
        'type' => 'system',
        'sub_type' => 'collaborative',
        'title' => 'System Settings',
        'fields' => [
            'pef_enabled' => $enabled,
            'pef_course_ids' => $courseIds,
            'pef_access_duration' => $duration,
            'companion_auto_enroll_enabled' => $companionCourseId !== null,
            'companion_course_id' => $companionCourseId,
        ],
    ]);
}

function pefEnrollment(int $userId, int $courseId): CourseEnrollment
{
    return CourseEnrollment::query()
        ->where('user_id', $userId)
        ->where('course_id', $courseId)
        ->firstOrFail();
}
