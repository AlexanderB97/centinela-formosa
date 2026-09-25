<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

// TEMPORAL: solo para pruebas locales de frontend. Backend define las rutas reales y el middleware — coordinar antes de mergear.
Route::livewire('/staff/usuarios', 'pages::staff.usuarios')->name('staff.usuarios');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
