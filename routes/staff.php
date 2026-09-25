<?php

use App\Http\Controllers\Staff\LoginController;
use App\Http\Controllers\Staff\UsuarioController;
use Illuminate\Support\Facades\Route;

Route::prefix('staff')->name('staff.')->group(function () {
    Route::middleware('guest:staff')->group(function () {
        Route::get('login', [LoginController::class, 'create'])->name('login');
        Route::post('login', [LoginController::class, 'store'])->name('login.store');
    });

    Route::middleware('auth:staff')->group(function () {
        Route::view('dashboard', 'pages::staff.dashboard')->name('dashboard');
        Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

        // Gestión de staff (HU1.2): solo admins; un moderador recibe 403.
        Route::middleware('admin.staff')->group(function () {
            Route::livewire('usuarios', 'pages::staff.usuarios')->name('usuarios');
            Route::post('usuarios', [UsuarioController::class, 'store'])->name('usuarios.store');
        });
    });
});
