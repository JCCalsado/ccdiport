<?php

use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(function () {
    // ✅ Redirect /settings to /settings/profile
    Route::redirect('settings', '/settings/profile');

    // ✅ Profile Routes
    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    
    // ✅ Profile Picture Routes (CRITICAL FIX)
    Route::post('settings/profile-picture', [ProfileController::class, 'updatePicture'])
        ->name('profile.update-picture');
    Route::delete('settings/profile-picture', [ProfileController::class, 'removePicture'])
        ->name('profile.remove-picture');

    // ✅ Password Routes
    Route::get('settings/password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('settings/password', [PasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('password.update');

    // ✅ Appearance Route
    Route::get('settings/appearance', function () {
        return Inertia::render('settings/Appearance');
    })->name('appearance');
});