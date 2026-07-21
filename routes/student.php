<?php

use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\ChunkedUploadController;
use App\Http\Controllers\Course\AssignmentSampleController;
use App\Http\Controllers\Course\AssignmentSubmissionController;
use App\Http\Controllers\Course\CourseEnrollmentController;
use App\Http\Controllers\Course\CourseForumController;
use App\Http\Controllers\Course\CourseForumModerationController;
use App\Http\Controllers\Course\CourseForumReplyController;
use App\Http\Controllers\Course\CourseReviewController;
use App\Http\Controllers\Course\CourseWishlistController;
use App\Http\Controllers\Course\LessonResourceController;
use App\Http\Controllers\Course\PlayerController;
use App\Http\Controllers\Course\ProtectedMediaController;
use App\Http\Controllers\Course\VideoStreamController;
use App\Http\Controllers\Course\QuizSubmissionController;
use App\Http\Controllers\Course\TopPerformerController;
use App\Http\Controllers\InstructorController;
use App\Http\Controllers\LiveClassController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\UsersController;
use App\Services\AuthService;
use Illuminate\Support\Facades\Route;

Route::get('student/{tab}', function (string $tab) {
    return redirect(app(AuthService::class)->homeUrlFor(auth()->user(), ['tab' => $tab]));
})->name('student.index')->middleware(['auth', 'smtpConfig']);
Route::get('dashboard/browse/{category}', [StudentController::class, 'browse_category'])->name('student.category.courses')->middleware('auth');
Route::get('student/courses/{id}/{tab}', [StudentController::class, 'show_course'])->name('student.course.show');
Route::get('student/exams/{id}/{tab}', [StudentController::class, 'show_exam'])->name('student.exam.show');
Route::post('student/profile', [StudentController::class, 'update_profile'])->name('student.profile.update');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('subscriptions/portal', [SubscriptionController::class, 'portal'])->name('subscriptions.portal');
});

Route::controller(InstructorController::class)->middleware(['smtpConfig', 'checkSmtp', 'checkCourseCreation'])->group(function () {
    Route::post('become-instructor', 'store')->name('become-instructor.store');
    Route::post('become-instructor/{id}', 'update')->name('become-instructor.update');
});

Route::post('enrollments', [CourseEnrollmentController::class, 'store'])->name('enrollments.store');

// Project Library submissions (learner uploads completed work)
Route::post('projects/{project}/submit', [\App\Http\Controllers\ProjectSubmissionController::class, 'store'])
    ->middleware('auth')
    ->name('project-submissions.store');

Route::resource('course-wishlists', CourseWishlistController::class)->only(['store', 'destroy']);

Route::resource('quiz-submissions', QuizSubmissionController::class)->only(['store']);

// lesson resource route
Route::middleware('auth')->group(function () {
    Route::get('lesson/resources/view/{resource}', [LessonResourceController::class, 'view'])->name('resources.view');
    Route::get('lesson/resources/download/{id}', [LessonResourceController::class, 'download'])->name('resources.download');
    Route::get('play-course/media/{lesson}', [ProtectedMediaController::class, 'streamLesson'])
        ->middleware('signed')
        ->name('course.player.media');

    Route::get('play-course/video/{lesson}/stream-url', [VideoStreamController::class, 'streamUrl'])
        ->middleware('throttle:30,1')
        ->name('course.player.video.stream-url');

    Route::get('play-course/video/{lesson}/stream', [VideoStreamController::class, 'stream'])
        ->middleware('signed')
        ->name('course.player.video.stream');
});

Route::get('assignment/{assignment}/sample/download', [AssignmentSampleController::class, 'download'])
    ->middleware('auth')
    ->name('assignment.sample.download');

// assignment submission route
Route::controller(AssignmentSubmissionController::class)->group(function () {
    Route::post('assignment/submission', 'store')->name('assignment.submission.store');
    Route::put('assignment/submission/{id}', 'update')->name('assignment.submission.update');
    Route::get('assignment/submission/{assignmentId}/student', 'getStudentSubmissions')->name('assignment.submission.student');
    Route::get('assignment/submission/{id}', 'show')->name('assignment.submission.show');
});

Route::controller(\App\Http\Controllers\Course\LessonActivitySubmissionController::class)->middleware('auth')->group(function () {
    Route::post('lesson-activity/submission', 'store')->name('lesson-activity.submission.store');
    Route::put('lesson-activity/submission/{id}', 'update')->name('lesson-activity.submission.update');
});

Route::controller(PlayerController::class)->middleware(['checkEnroll'])->group(function () {
    Route::post('player/init/watch-history', 'intWatchHistory')->name('player.init.watch-history');
    Route::get('play-course/{type}/{watch_history}/{lesson_id}', 'course_player')->name('course.player');
    Route::post('play-course/complete/{watch_history}', 'mark_complete')->name('course.player.complete');
    Route::post('play-course/watch-progress/{watch_history}', 'record_watch_progress')->name('course.player.watch-progress');
    Route::get('play-course/finish/{watch_history}', 'finish_course')->name('course.player.finish');
});

Route::resource('notifications', NotificationController::class)->only(['index', 'show']);
Route::put('notifications/mark-as-read/all', [NotificationController::class, 'markAllAsRead'])->name('notifications.mark-all-as-read');

Route::resource('course-forums', CourseForumController::class)->only(['store', 'update', 'destroy']);
Route::resource('course-forum-replies', CourseForumReplyController::class)->only(['store', 'update', 'destroy']);

Route::middleware('auth')->group(function () {
    Route::post('course-forums/{forum}/resolve', [CourseForumModerationController::class, 'resolve'])->name('course-forums.resolve');
    Route::post('course-forums/{forum}/reopen', [CourseForumModerationController::class, 'reopen'])->name('course-forums.reopen');
    Route::post('course-forums/{forum}/pin-reply/{reply}', [CourseForumModerationController::class, 'pinReply'])->name('course-forums.pin-reply');
    Route::delete('course-forums/{forum}/pin-reply', [CourseForumModerationController::class, 'unpinReply'])->name('course-forums.unpin-reply');
});
Route::resource('course-reviews', CourseReviewController::class)->only(['store', 'update', 'destroy']);

// settings
Route::middleware('smtpConfig', 'checkSmtp')->prefix('settings/account')->group(function () {
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])->name('account.forgot-password');
    Route::put('change-password', [PasswordResetLinkController::class, 'update'])->name('account.change-password');

    Route::post('change-email', [EmailVerificationNotificationController::class, 'update'])->name('account.change-email');
    Route::get('change-email/save', [EmailVerificationNotificationController::class, 'save'])->name('account.save-email');
});

// Live class routes accessible to both instructors and students
Route::get('live-class/start/{id}', [LiveClassController::class, 'index'])->name('live-class.start');
Route::get('live-class/signature/{id}', [LiveClassController::class, 'signature'])->name('live-class.signature');

// Cross-course top performers (learners)
Route::middleware(['auth', 'legalAgreement'])->group(function () {
    Route::get('top-performers', [TopPerformerController::class, 'index'])->name('learner.top-performers');
});

// users
Route::delete('users/{id}', [UsersController::class, 'destroy'])->name('users.destroy');

// Chunked uploads for large files
Route::prefix('dashboard/uploads/chunked')->controller(ChunkedUploadController::class)->group(function () {
    Route::post('initialize', 'initialize')->name('chunked.upload.initialize');
    Route::post('{id}/chunk', 'uploadChunk')->name('chunked.upload.chunk');
    Route::post('{id}/complete', 'complete')->name('chunked.upload.complete');
    Route::get('{id}/status', 'status')->name('chunked.upload.status');
    Route::delete('{id}/abort', 'abort')->name('chunked.upload.abort');
});

Route::prefix('dashboard/uploads/bunny')->controller(\App\Http\Controllers\BunnyUploadController::class)->group(function () {
    Route::post('initialize', 'initialize')->name('bunny.upload.initialize');
    Route::post('complete', 'complete')->name('bunny.upload.complete');
    Route::delete('abort', 'abort')->name('bunny.upload.abort');
});
