<?php

use App\Enums\EnrollmentAccessStatus;
use App\Enums\CourseBillingModel;
use App\Enums\LearnerUserType;
use App\Enums\SubscriptionStatus;
use App\Models\Course\Course;
use App\Models\Course\CourseEnrollment;
use App\Models\Course\WatchHistory;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Payment\SubscriptionAccessService;
use Carbon\Carbon;

beforeEach(function () {
    $this->service = app(SubscriptionAccessService::class);
});

function makeCourse(array $overrides = []): Course
{
    $course = new Course(array_merge([
        'id' => 1,
        'title' => 'Test Course',
        'slug' => 'test-course',
        'instructor_id' => 10,
        'billing_model' => CourseBillingModel::SUBSCRIPTION,
        'pricing_type' => 'paid',
    ], $overrides));

    $course->exists = true;

    return $course;
}

function makeUser(array $overrides = []): User
{
    $user = new User(array_merge([
        'id' => 1,
        'role' => 'student',
        'user_type' => LearnerUserType::EXTERNAL,
        'instructor_id' => null,
    ], $overrides));

    $user->exists = true;

    return $user;
}

function makeEnrollment(EnrollmentAccessStatus $status, ?Subscription $subscription = null): CourseEnrollment
{
    $enrollment = new CourseEnrollment([
        'access_status' => $status,
        'user_id' => 1,
        'course_id' => 1,
    ]);
    $enrollment->exists = true;

    if ($subscription) {
        $enrollment->setRelation('subscription', $subscription);
    }

    return $enrollment;
}

it('grants full access to active subscription enrollments', function () {
    $user = makeUser();
    $course = makeCourse();
    $enrollment = makeEnrollment(EnrollmentAccessStatus::ACTIVE);

    expect($this->service->getAccessMode($user, $course, $enrollment))->toBe('full');
    expect($this->service->canMarkProgress($user, $course, $enrollment))->toBeTrue();
});

it('uses completed_only mode for suspended subscription enrollments', function () {
    $user = makeUser();
    $course = makeCourse();
    $enrollment = makeEnrollment(
        EnrollmentAccessStatus::SUSPENDED,
        new Subscription(['status' => SubscriptionStatus::CANCELED]),
    );

    expect($this->service->getAccessMode($user, $course, $enrollment))->toBe('completed_only');
    expect($this->service->canMarkProgress($user, $course, $enrollment))->toBeFalse();
    expect($this->service->toFrontendPayload($user, $course, $enrollment)['can_resubscribe'])->toBeTrue();
});

it('allows read-only access only to completed items when suspended', function () {
    $user = makeUser();
    $course = makeCourse();
    $enrollment = makeEnrollment(EnrollmentAccessStatus::SUSPENDED);
    $watchHistory = new WatchHistory([
        'completed_watching' => json_encode([
            ['id' => '10', 'type' => 'lesson'],
            ['id' => '20', 'type' => 'quiz'],
        ]),
    ]);

    expect($this->service->canAccessItem($user, $course, 10, 'lesson', $watchHistory, $enrollment))->toBeTrue();
    expect($this->service->canAccessItem($user, $course, 99, 'lesson', $watchHistory, $enrollment))->toBeFalse();
});

it('keeps full access during past_due grace on suspended enrollment rows', function () {
    $user = makeUser();
    $course = makeCourse();
    $subscription = new Subscription([
        'status' => SubscriptionStatus::PAST_DUE,
        'grace_ends_at' => Carbon::now()->addDay(),
    ]);
    $enrollment = makeEnrollment(EnrollmentAccessStatus::SUSPENDED, $subscription);

    expect($enrollment->hasFullAccess())->toBeTrue();
    expect($this->service->getAccessMode($user, $course, $enrollment))->toBe('full');
});

it('returns none when there is no enrollment', function () {
    $user = makeUser();
    $course = makeCourse();

    expect($this->service->getAccessMode($user, $course, null))->toBe('none');
    expect($this->service->canAccessPlayer($user, $course, null))->toBeFalse();
});

it('allows employees through linked exam gate without subscription', function () {
    $user = makeUser(['user_type' => LearnerUserType::EMPLOYEE]);

    expect($this->service->hasActiveSubscriptionForLinkedExam($user, 999))->toBeTrue();
});

it('allows non-subscription linked courses through exam gate', function () {
    $user = makeUser();

    expect($this->service->hasActiveSubscriptionForLinkedExam($user, 999))->toBeTrue();
});
