<?php

use App\Enums\CourseBillingModel;
use App\Enums\EnrollmentAccessStatus;
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

it('flags staff_preview for admin and the course instructor without enrollment', function () {
    $course = makeCourse();
    $admin = makeUser(['role' => 'admin']);
    $instructor = makeUser(['role' => 'instructor', 'instructor_id' => 10]);
    $otherInstructor = makeUser(['role' => 'instructor', 'instructor_id' => 99]);
    $student = makeUser();

    expect($course->isStaffPreviewer($admin))->toBeTrue();
    expect($course->isStaffPreviewer($instructor))->toBeTrue();
    expect($course->isStaffPreviewer($otherInstructor))->toBeFalse();
    expect($course->isStaffPreviewer($student))->toBeFalse();

    expect($this->service->toFrontendPayload($admin, $course, null)['staff_preview'])->toBeTrue();
    expect($this->service->toFrontendPayload($instructor, $course, null)['staff_preview'])->toBeTrue();
});

it('returns none when there is no enrollment', function () {
    $user = makeUser();
    $course = makeCourse();

    expect($this->service->getAccessMode($user, $course, null))->toBe('none');
    expect($this->service->canAccessPlayer($user, $course, null))->toBeFalse();
});

it('hides public curriculum from guests reserved seats and other instructors', function () {
    $course = makeCourse();
    $student = makeUser();
    $otherInstructor = makeUser(['role' => 'instructor', 'instructor_id' => 99]);
    $courseInstructor = makeUser(['role' => 'instructor', 'instructor_id' => 10]);
    $admin = makeUser(['role' => 'admin']);
    $reserved = makeEnrollment(EnrollmentAccessStatus::RESERVED);
    $canceled = makeEnrollment(EnrollmentAccessStatus::CANCELED);
    $active = makeEnrollment(EnrollmentAccessStatus::ACTIVE);
    $lapsed = makeEnrollment(EnrollmentAccessStatus::SUSPENDED);

    expect($this->service->canViewPublicCurriculum(null, $course, null))->toBeFalse();
    expect($this->service->canViewPublicCurriculum($student, $course, null))->toBeFalse();
    expect($this->service->canViewPublicCurriculum($student, $course, $reserved))->toBeFalse();
    expect($this->service->canViewPublicCurriculum($student, $course, $canceled))->toBeFalse();
    expect($this->service->canViewPublicCurriculum($otherInstructor, $course, null))->toBeFalse();

    expect($this->service->canViewPublicCurriculum($student, $course, $active))->toBeTrue();
    expect($this->service->canViewPublicCurriculum($student, $course, $lapsed))->toBeTrue();
    expect($this->service->canViewPublicCurriculum($courseInstructor, $course, null))->toBeTrue();
    expect($this->service->canViewPublicCurriculum($admin, $course, null))->toBeTrue();
});

it('hides public us experience from guests staff and anyone not enrolled in the course', function () {
    $course = makeCourse();
    $oneTimeCourse = makeCourse(['billing_model' => CourseBillingModel::ONE_TIME]);
    $student = makeUser();
    $admin = makeUser(['role' => 'admin']);
    $courseInstructor = makeUser(['role' => 'instructor', 'instructor_id' => 10]);
    $reserved = makeEnrollment(EnrollmentAccessStatus::RESERVED);
    $canceled = makeEnrollment(EnrollmentAccessStatus::CANCELED);
    $active = makeEnrollment(EnrollmentAccessStatus::ACTIVE);
    $lapsed = makeEnrollment(EnrollmentAccessStatus::SUSPENDED);

    expect($this->service->canViewPublicUsExperience(null, $course, null))->toBeFalse();
    expect($this->service->canViewPublicUsExperience($student, $course, null))->toBeFalse();
    expect($this->service->canViewPublicUsExperience($student, $course, $reserved))->toBeFalse();
    expect($this->service->canViewPublicUsExperience($student, $course, $canceled))->toBeFalse();
    expect($this->service->canViewPublicUsExperience($admin, $course, null))->toBeFalse();
    expect($this->service->canViewPublicUsExperience($courseInstructor, $course, null))->toBeFalse();

    expect($this->service->canViewPublicUsExperience($student, $course, $active))->toBeTrue();
    expect($this->service->canViewPublicUsExperience($student, $course, $lapsed))->toBeTrue();
    expect($this->service->canViewPublicUsExperience($student, $oneTimeCourse, $active))->toBeTrue();
    expect($this->service->canViewPublicUsExperience($student, $oneTimeCourse, $lapsed))->toBeFalse();
});

it('locks an installment course immediately without using the subscription grace period', function () {
    $user = makeUser();
    $course = makeCourse([
        'billing_model' => CourseBillingModel::INSTALLMENT_SUBSCRIPTION,
        'installment_price' => 15,
        'installment_months' => 6,
        'subscription_price' => 6,
    ]);
    $subscription = new Subscription([
        'status' => SubscriptionStatus::PAST_DUE,
        'grace_ends_at' => Carbon::now()->addDays(3),
        'open_invoice_url' => 'https://pay.stripe.test/in_123',
    ]);
    $enrollment = makeEnrollment(EnrollmentAccessStatus::SUSPENDED, $subscription);
    $watchHistory = new WatchHistory([
        'completed_watching' => json_encode([
            ['id' => '10', 'type' => 'lesson'],
        ]),
    ]);

    expect($this->service->getAccessMode($user, $course, $enrollment))->toBe('installment_locked');
    expect($this->service->canAccessPlayer($user, $course, $enrollment))->toBeFalse();
    expect($this->service->canAccessItem($user, $course, 10, 'lesson', $watchHistory, $enrollment))->toBeFalse();

    $payload = $this->service->toFrontendPayload($user, $course, $enrollment);

    expect($payload['can_resubscribe'])->toBeFalse();
    expect($payload['can_pay_invoice'])->toBeTrue();
    expect($payload['invoice_url'])->toBe('https://pay.stripe.test/in_123');
    expect($payload['can_mark_progress'])->toBeFalse();
});

it('gives full access to an active installment subscription', function () {
    $user = makeUser();
    $course = makeCourse([
        'billing_model' => CourseBillingModel::INSTALLMENT_SUBSCRIPTION,
    ]);
    $enrollment = makeEnrollment(
        EnrollmentAccessStatus::ACTIVE,
        new Subscription(['status' => SubscriptionStatus::ACTIVE]),
    );

    expect($this->service->getAccessMode($user, $course, $enrollment))->toBe('full');
    expect($this->service->canMarkProgress($user, $course, $enrollment))->toBeTrue();
    expect($this->service->toFrontendPayload($user, $course, $enrollment)['can_resubscribe'])->toBeFalse();
    expect($this->service->toFrontendPayload($user, $course, $enrollment)['can_pay_invoice'])->toBeFalse();
});

it('allows employees through linked exam gate without subscription', function () {
    $user = makeUser(['user_type' => LearnerUserType::EMPLOYEE]);

    expect($this->service->hasActiveSubscriptionForLinkedExam($user, 999))->toBeTrue();
});

it('allows non-subscription linked courses through exam gate', function () {
    $user = makeUser();

    expect($this->service->hasActiveSubscriptionForLinkedExam($user, 999))->toBeTrue();
});

it('keeps a normal one-time enrollment open when the course expiry date has passed', function () {
    $user = makeUser();
    $course = makeCourse(['billing_model' => CourseBillingModel::ONE_TIME]);
    $enrollment = makeEnrollment(EnrollmentAccessStatus::ACTIVE);
    $enrollment->expiry_date = Carbon::now()->subDay();

    expect($this->service->getAccessMode($user, $course, $enrollment))->toBe('full');
});

it('gives a PEF enrollment full access before its end date', function () {
    $user = makeUser(['pef_access_ends_at' => Carbon::now()->addYear()]);
    $course = makeCourse([
        'billing_model' => CourseBillingModel::INSTALLMENT_SUBSCRIPTION,
    ]);
    $enrollment = makeEnrollment(EnrollmentAccessStatus::ACTIVE);
    $enrollment->pef_granted = true;

    expect($this->service->getAccessMode($user, $course, $enrollment))->toBe('full');
    expect($this->service->canMarkProgress($user, $course, $enrollment))->toBeTrue();
    expect($this->service->toFrontendPayload($user, $course, $enrollment)['pef_limited'])->toBeFalse();
});

it('limits an ended PEF enrollment to finished items without a resubscribe offer', function () {
    $user = makeUser(['pef_access_ends_at' => Carbon::now()->subMinute()]);
    $course = makeCourse(['billing_model' => CourseBillingModel::SUBSCRIPTION]);
    $enrollment = makeEnrollment(EnrollmentAccessStatus::ACTIVE);
    $enrollment->pef_granted = true;
    $watchHistory = new WatchHistory([
        'completed_watching' => [
            ['id' => '10', 'type' => 'lesson'],
        ],
    ]);

    expect($this->service->getAccessMode($user, $course, $enrollment))->toBe('completed_only');
    expect($this->service->canAccessItem($user, $course, 10, 'lesson', $watchHistory, $enrollment))->toBeTrue();
    expect($this->service->canAccessItem($user, $course, 99, 'lesson', $watchHistory, $enrollment))->toBeFalse();
    expect($this->service->canSubmitAssignments($user, $course, $enrollment))->toBeFalse();

    $payload = $this->service->toFrontendPayload($user, $course, $enrollment);

    expect($payload['pef_limited'])->toBeTrue();
    expect($payload['can_resubscribe'])->toBeFalse();
    expect($payload['can_mark_progress'])->toBeFalse();
});
