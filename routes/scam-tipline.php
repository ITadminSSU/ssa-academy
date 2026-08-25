<?php

use App\Http\Controllers\ScamTiplineController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Fraud Training Tipline (admin + social_media)
|--------------------------------------------------------------------------
*/

Route::get('/dashboard/social', function () {
    return redirect()->route('scam-tipline.index');
})->name('dashboard.social');

Route::prefix('dashboard/scam-tipline')->group(function () {
    Route::get('/', [ScamTiplineController::class, 'index'])->name('scam-tipline.index');
    Route::get('export/csv', [ScamTiplineController::class, 'export'])->name('scam-tipline.export');
    Route::put('{report}', [ScamTiplineController::class, 'update'])->name('scam-tipline.update');
    Route::delete('{report}', [ScamTiplineController::class, 'destroy'])->name('scam-tipline.destroy');
    Route::post('{report}/restore', [ScamTiplineController::class, 'restore'])->name('scam-tipline.restore');
});
