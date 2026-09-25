<?php

use App\Http\Controllers\Auth\ActivationController;
use App\Http\Controllers\Auth\ChangePasswordController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Auth\SignUpController;
use App\Http\Controllers\EntitySwitchController;
use App\Livewire\Dashboard;
use App\Livewire\Placeholder;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login')->name('login.store');

    Route::get('/sign-up', [SignUpController::class, 'create'])->name('sign-up');
    Route::post('/sign-up', [SignUpController::class, 'store'])->middleware('throttle:auth-email')->name('sign-up.store');

    Route::get('/activate/{user}', [ActivationController::class, 'show'])->middleware('signed')->name('activation.show');
    Route::post('/activate/{user}', [ActivationController::class, 'store'])->middleware(['signed', 'throttle:login'])->name('activation.store');

    Route::get('/forgot-password', [ForgotPasswordController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])->middleware('throttle:auth-email')->name('password.email');

    Route::get('/reset-password/{token}', [ResetPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [ResetPasswordController::class, 'store'])->middleware('throttle:login')->name('password.update');
});

Route::middleware(['auth', 'idle', 'entity'])->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/password/change', [ChangePasswordController::class, 'edit'])->name('password.change');
    Route::put('/password/change', [ChangePasswordController::class, 'update'])->name('password.change.update');

    Route::middleware('password.fresh')->group(function () {
        Route::redirect('/', '/dashboard');
        Route::get('/dashboard', Dashboard::class)->name('dashboard');
        Route::post('/entity/switch', EntitySwitchController::class)->name('entity.switch');

        // Sidebar destinations delivered in later phases.
        Route::get('/capex', Placeholder::class)->name('capex.index')->defaults('section', 'capex');
        Route::get('/payments', Placeholder::class)->name('payments.index')->defaults('section', 'payments');
        Route::get('/master-data', Placeholder::class)->name('master-data.index')->defaults('section', 'master-data');
        Route::get('/reports', Placeholder::class)->name('reports.index')->defaults('section', 'reports');
    });
});
