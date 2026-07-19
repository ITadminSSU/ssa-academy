<?php

use Illuminate\Support\Facades\Route;
use Modules\Exam\Http\Controllers\ExamController;
use Modules\Exam\Http\Controllers\ExamQuestionController;
use Modules\Exam\Http\Controllers\ExamEnrollmentController;
use Modules\Exam\Http\Controllers\ExamAttemptController;
use Modules\Exam\Http\Controllers\ExamLeaderboardController;
use Modules\Exam\Http\Controllers\ExamReviewController;
use Modules\Exam\Http\Controllers\ExamWishlistController;
use Modules\Exam\Http\Controllers\ExamFaqController;
use Modules\Exam\Http\Controllers\ExamRequirementController;
use Modules\Exam\Http\Controllers\ExamOutcomeController;
use Modules\Exam\Http\Controllers\ExamResourceController;
use Modules\Exam\Http\Controllers\ExamQuantityTakeoffController;
use Modules\Exam\Http\Middleware\CheckExamEnrollMiddleware;

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:admin'])->prefix('dashboard')->group(function () {
    // Exams (Admin only)
    Route::delete('exams/{exam}', [ExamController::class, 'destroy'])->name('exams.destroy');

    // course enrolment
    Route::delete('enrollments/exams/{id}', [ExamEnrollmentController::class, 'destroy'])->name('exam-enrollments.destroy');
});

/*
|--------------------------------------------------------------------------
| Instructor Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'legalAgreement', 'role:instructor,admin'])->prefix('dashboard')->group(function () {
    // Exams (Admin can manage all)
    Route::resource('exams', ExamController::class)->except(['show', 'update', 'destroy']);
    Route::post('exams/{exam}', [ExamController::class, 'update'])->name('exams.update');
    Route::post('exams/{exam}/takeoff/answer-key', [ExamQuantityTakeoffController::class, 'importAnswerKey'])->name('exams.takeoff.answer-key');
    Route::post('exams/{exam}/takeoff/tutorial', [ExamQuantityTakeoffController::class, 'saveTutorial'])->name('exams.takeoff.tutorial');
    Route::post('exams/{exam}/takeoff/tolerances', [ExamQuantityTakeoffController::class, 'saveTolerances'])->name('exams.takeoff.tolerances');
    Route::post('exams/{exam}/takeoff/student-template', [ExamQuantityTakeoffController::class, 'saveStudentTemplate'])->name('exams.takeoff.student-template');
    Route::get('exams/{exam}/takeoff/template', [ExamQuantityTakeoffController::class, 'downloadTemplate'])->name('exams.takeoff.template');

    // Exam Info (FAQs, Requirements, Outcomes)
    Route::resource('exam-faqs', ExamFaqController::class)->only(['store', 'update', 'destroy']);
    Route::resource('exam-requirements', ExamRequirementController::class)->only(['store', 'update', 'destroy']);
    Route::resource('exam-outcomes', ExamOutcomeController::class)->only(['store', 'update', 'destroy']);

    // Exam Questions
    Route::resource('exam-questions', ExamQuestionController::class)->only(['store', 'update', 'destroy']);
    Route::post('exam-questions/reorder', [ExamQuestionController::class, 'reorder'])->name('exam-questions.reorder');
    Route::post('exam-questions/{question}/duplicate', [ExamQuestionController::class, 'duplicate'])->name('exam-questions.duplicate');

    // lesson resource route
    Route::resource('exam-resources', ExamResourceController::class)->only(['store', 'update', 'destroy']);

    // Exam Attempt Review 
    Route::post('exam-attempts/{attempt}/grade', [ExamAttemptController::class, 'grade'])->name('exam-attempts.grade');
    Route::post('exam-attempts/{attempt}/takeoff-overrides', [ExamAttemptController::class, 'saveTakeoffOverrides'])->name('exam-attempts.takeoff-overrides');

    // Exam talent leaderboard (all test-takers ranked by score)
    Route::get('exams/leaderboard', [ExamLeaderboardController::class, 'index'])->name('exams.leaderboard');

    // course enrolment
    Route::get('enrollments/exams', [ExamEnrollmentController::class, 'index'])->name('exam-enrollments.index');
});

/*
|--------------------------------------------------------------------------
| Student Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'legalAgreement', 'role:student,instructor,admin'])->prefix('student')->group(function () {
    Route::get('exam/resources/download/{id}', [ExamResourceController::class, 'download'])->name('exam-resources.download');

    // Exam Attempts
    Route::post('exams/{exam}/attempts/start', [ExamAttemptController::class, 'start'])->name('exam-attempts.start')->middleware(CheckExamEnrollMiddleware::class);
    Route::get('exam-attempts/{attempt}/take', [ExamAttemptController::class, 'take'])->name('exam-attempts.take');
    Route::get('exam-attempts/{attempt}/takeoff-template', [ExamAttemptController::class, 'downloadTakeoffTemplate'])->name('exam-attempts.takeoff-template');
    Route::post('exam-attempts/{attempt}/submit', [ExamAttemptController::class, 'submit'])->name('exam-attempts.submit');
    Route::post('exam-attempts/{attempt}/abandon', [ExamAttemptController::class, 'abandon'])->name('exam-attempts.abandon');

    // Reviews
    Route::get('exams/{exam}/reviews', [ExamReviewController::class, 'index'])->name('exam-reviews.index');
    Route::post('exam-reviews', [ExamReviewController::class, 'store'])->name('exam-reviews.store');
    Route::put('exam-reviews/{review}', [ExamReviewController::class, 'update'])->name('exam-reviews.update');
    Route::delete('exam-reviews/{review}', [ExamReviewController::class, 'destroy'])->name('exam-reviews.destroy');

    // Wishlist
    Route::resource('exam-wishlists', ExamWishlistController::class)->only(['store', 'destroy']);

    // free enrolment
    Route::post('enrollments/exams', [ExamEnrollmentController::class, 'store'])->name('exam-enrollments.store');
});

/*
|--------------------------------------------------------------------------
| Public/Guest Routes (similar to course routes)
|--------------------------------------------------------------------------
*/

// Exam browsing
Route::get('browse/exams', [ExamController::class, 'browse_exams'])->name('exams.browse');
Route::get('exams/details/{slug}/{id}', [ExamController::class, 'show'])->name('exams.details');
