<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\CertificateVerificationController;
use App\Http\Controllers\Course\CourseController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InstructorController;
use App\Http\Controllers\JobCircularController;
use App\Http\Controllers\SubscribeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['guest', 'authConfig'])->group(function () {
    Route::get('/', [AuthenticatedSessionController::class, 'create'])->name('home');
});

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
Route::controller(CourseController::class)->group(function () {
    Route::get('courses/{category}/{category_child?}', 'category_courses')->name('category.courses');
    Route::get('courses/details/{slug}/{id}', 'show')->name('course.details');
});

Route::get('instructors/{instructor}', [InstructorController::class, 'show'])->name('instructors.show');
Route::resource('subscribes', SubscribeController::class)->only(['index', 'store']);
