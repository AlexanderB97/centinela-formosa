<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

// TEMPORAL: solo para pruebas locales de frontend. Backend define la ruta real (GET+POST) y el middleware — coordinar antes de mergear.
Route::view('/staff/login', 'pages::auth.staff-login')->name('staff.login');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
