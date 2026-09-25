<?php

use App\Http\Controllers\Staff\LoginController;
use Illuminate\Support\Facades\Route;

Route::prefix('staff')->name('staff.')->group(function () {
    Route::middleware('guest:staff')->group(function () {
        Route::get('login', [LoginController::class, 'create'])->name('login');
        Route::post('login', [LoginController::class, 'store'])->name('login.store');
    });

    Route::middleware('auth:staff')->group(function () {
        Route::view('dashboard', 'pages::staff.dashboard')->name('dashboard');
        Route::post('logout', [LoginController::class, 'destroy'])->name('logout');
    });
});
