<?php

use Illuminate\Support\Facades\Route;
use Modules\Language\Http\Controllers\LanguageController;

Route::post('change-lang', [LanguageController::class, 'change_lang'])->name('change.lang');

Route::middleware(['installed', 'appConfig', 'auth', 'role:admin'])->prefix('dashboard/settings')->group(function () {
    Route::resource('language', LanguageController::class)->except(['create']);
    Route::post('language/default/{id}', [LanguageController::class, 'default'])->name('language.default');

    Route::get('/language/property/{property}', [LanguageController::class, 'edit_property'])->name('language.property.edit');
    Route::put('/language/property/{property}', [LanguageController::class, 'update_property'])->name('language.property.update');
});
