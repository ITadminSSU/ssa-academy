<?php

use App\Http\Controllers\ChatController;
use Illuminate\Support\Facades\Route;

Route::prefix('messages')->name('messages.')->group(function () {
    Route::get('/', [ChatController::class, 'index'])->name('index');
    Route::get('course/{course}/dm', [ChatController::class, 'openDirect'])->name('dm');
    Route::get('course/{course}/group', [ChatController::class, 'openGroup'])->name('group');
    Route::get('{conversation}', [ChatController::class, 'show'])->name('show');
    Route::post('{conversation}', [ChatController::class, 'store'])->name('store');
});
