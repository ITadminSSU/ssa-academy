<?php

use App\Http\Controllers\StudentController;
use App\Services\AuthService;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Role-specific dashboard entry routes (SSU Academy)
|--------------------------------------------------------------------------
|
| /dashboard/admin    — Administrators
| /dashboard/trainer  — Trainers / instructors
| /dashboard/internal — Internal employee learners
| /dashboard/external — External paid/public learners
|
*/

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', function () {
        return redirect(app(AuthService::class)->homeUrlFor(auth()->user()));
    })->name('dashboard');

    Route::middleware(['verified', 'legalAgreement', 'learnerDashboard:internal'])->group(function () {
        Route::get('/dashboard/internal/{tab?}', [StudentController::class, 'index'])
            ->where('tab', 'home|courses|exams|certificates|announcements|community|professional-development|project-library|resources|help-center|wishlist|profile|settings|subscriptions')
            ->defaults('tab', 'home')
            ->name('dashboard.internal');
    });

    Route::middleware(['verified', 'legalAgreement', 'learnerDashboard:external'])->group(function () {
        Route::get('/dashboard/external/{tab?}', [StudentController::class, 'index'])
            ->where('tab', 'home|courses|exams|certificates|announcements|community|professional-development|project-library|resources|help-center|wishlist|profile|settings|subscriptions')
            ->defaults('tab', 'home')
            ->name('dashboard.external');
    });
});
