<?php

use App\Http\Controllers\CertificateVerificationController;
use App\Http\Controllers\Course\CourseController;
use App\Http\Controllers\Course\CourseLaunchNotificationController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InstructorController;
use App\Http\Controllers\JobCircularController;
use App\Http\Controllers\SubscribeController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('about-us', [HomeController::class, 'about'])->name('about');

// Certificate verification — restricted to admin & trainer for verification purposes.
Route::middleware(['auth', 'role:admin,instructor'])->group(function () {
    Route::get('verify-certificate/{reference?}', [CertificateVerificationController::class, 'show'])
        ->name('certificate.verify');
});
Route::get('demo/{slug}', [HomeController::class, 'demo'])->name('home.demo');
Route::get('job-circulars/{job_circular}', [JobCircularController::class, 'show'])
    ->middleware('feature:job_circulars')
    ->name('job-circulars.show');

// course page
Route::redirect('courses', '/courses/all');

Route::controller(CourseController::class)->group(function () {
    Route::get('courses/details/{slug}/{id}', 'show')->name('course.details');
    Route::get('courses/{category}/{category_child?}', 'category_courses')->name('category.courses');
});

Route::post('courses/{course}/launch-notifications', [CourseLaunchNotificationController::class, 'store'])
    ->name('course.launch-notifications.store');

Route::get('instructors/{instructor}', [InstructorController::class, 'show'])->name('instructors.show');
Route::resource('subscribes', SubscribeController::class)->only(['index', 'store']);
