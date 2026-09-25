<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

// TEMPORAL: la URL pública /analizar es la definitiva. Lo que cambia cuando backend implemente HU2.1
// es el análisis real detrás (hoy es un mock en memoria dentro del componente pages::analizador).
Route::livewire('/analizar', 'pages::analizador')->name('analizar');

// TEMPORAL: solo para pruebas locales de frontend. Backend define la ruta real (GET+POST) y el middleware — coordinar antes de mergear.
Route::view('/staff/login', 'pages::auth.staff-login')->name('staff.login');

// TEMPORAL: solo para pruebas locales de frontend. Backend define las rutas reales y el middleware — coordinar antes de mergear.
Route::view('/staff/dashboard', 'pages::staff.dashboard')->name('staff.dashboard');
Route::livewire('/staff/usuarios', 'pages::staff.usuarios')->name('staff.usuarios');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
