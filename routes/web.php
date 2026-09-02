<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\CaptivePortalForm;
use App\Http\Controllers\AdminController;

Route::get('/', CaptivePortalForm::class)->name('captive-portal');

require __DIR__.'/auth.php';

Route::middleware(['auth', 'verified', 'can:access-admin-panel'])->group(function () {
    Route::get('/dashboard', [AdminController::class, 'index'])->name('admin.dashboard');
});

Route::get('/logout', function () {
    auth()->logout();
    return redirect('/');
})->name('logout');

Route::get('/terms', function () {
    return view('terms');
})->name('terms');