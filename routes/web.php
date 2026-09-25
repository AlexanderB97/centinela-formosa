<?php

use App\Http\Controllers\AnalisisController;
use Illuminate\Support\Facades\Route;

// TEMPORAL: las URLs públicas / y /analizar son las definitivas. Lo que cambia cuando backend implemente HU2.1
// es el análisis real detrás (hoy es un mock en memoria dentro del componente pages::analizador).
Route::livewire('/', 'pages::analizador')->name('home');
Route::livewire('/analizar', 'pages::analizador')->name('analizar');

// TEMPORAL: solo para pruebas locales de frontend. Backend define las rutas reales y el middleware — coordinar antes de mergear.
Route::livewire('/staff/usuarios', 'pages::staff.usuarios')->name('staff.usuarios');

// API pública del analizador (HU2.1). La pantalla GET /analizar la sirve el componente Livewire.
Route::post('analizar', [AnalisisController::class, 'store'])
    ->middleware('throttle:analizar')
    ->name('analizar.store');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
