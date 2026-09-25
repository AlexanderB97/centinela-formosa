<?php

use App\Http\Controllers\AnalisisController;
use App\Http\Controllers\ReporteController;
use Illuminate\Support\Facades\Route;

// TEMPORAL: las URLs públicas / y /analizar son las definitivas. Lo que cambia cuando backend implemente HU2.1
// es el análisis real detrás (hoy es un mock en memoria dentro del componente pages::analizador).
Route::livewire('/', 'pages::analizador')->name('home');
Route::livewire('/analizar', 'pages::analizador')->name('analizar');

// API pública del analizador (HU2.1). La pantalla GET /analizar la sirve el componente Livewire.
Route::post('analizar', [AnalisisController::class, 'store'])
    ->middleware('throttle:analizar')
    ->name('analizar.store');

// TEMPORAL: la URL pública /impacto es la definitiva. Lo que cambia cuando backend implemente HU3.1
// es el origen de los datos (hoy es un mock en memoria dentro del componente pages::impacto).
Route::livewire('/impacto', 'pages::impacto')->name('impacto');

// API pública de reportes anónimos (HU2.2). El componente Livewire usa la misma lógica sin pasar por HTTP.
Route::post('reportar', [ReporteController::class, 'store'])
    ->middleware('throttle:reportar')
    ->name('reportar.store');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
