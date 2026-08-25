<?php

use App\Http\Controllers\ChatController;
use Illuminate\Support\Facades\Route;

Route::prefix('messages')->name('messages.')->group(function () {
    Route::get('/', [ChatController::class, 'index'])->name('index');
    Route::get('unread', [ChatController::class, 'unread'])->name('unread');
    Route::post('presence', [ChatController::class, 'presence'])->name('presence');
    Route::get('course/{course}/dm', [ChatController::class, 'openDirect'])->name('dm');
    Route::get('course/{course}/group', [ChatController::class, 'openGroup'])->name('group');
    Route::get('{conversation}', [ChatController::class, 'show'])->name('show');
    Route::post('{conversation}/read', [ChatController::class, 'read'])->name('read');
    Route::post('{conversation}', [ChatController::class, 'store'])->name('store');
    Route::post('{conversation}/resolve', [ChatController::class, 'resolve'])->name('resolve');
    Route::post('{conversation}/reopen', [ChatController::class, 'reopen'])->name('reopen');
    Route::post('{conversation}/mute', [ChatController::class, 'mute'])->name('mute');
    Route::post('{conversation}/pin/{message}', [ChatController::class, 'pin'])->name('pin');
    Route::delete('{conversation}/pin', [ChatController::class, 'unpin'])->name('unpin');
    Route::delete('{conversation}/messages/{message}', [ChatController::class, 'destroyMessage'])->name('message.destroy');
});
