<?php

use App\Http\Controllers\AnalisisController;
use App\Http\Controllers\ImagenNoticiaController;
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

// Dashboard público de impacto (HU3.1). La página Livewire lee los agregados reales de EstadisticasImpacto.
Route::livewire('/impacto', 'pages::impacto')->name('impacto');

// Ranking público de lo más consultado. La página Livewire lee los agregados de RankingConsultados.
Route::livewire('/rankings', 'pages::rankings')->name('rankings');

// Mapa público de zonas afectadas. La página Livewire lee los agregados de MapaZonasAfectadas.
Route::livewire('/mapa', 'pages::mapa')->name('mapa');

// Noticias públicas: solo las publicadas. La imagen de portada sale del disco privado por un controlador
// (sin storage:link): un borrador devuelve 404 a los visitantes aunque adivinen la URL.
Route::livewire('/noticias', 'pages::noticias')->name('noticias');
Route::livewire('/noticias/{noticia}', 'pages::noticia')->whereNumber('noticia')->name('noticias.show');
Route::get('/noticias/{noticia}/imagen', ImagenNoticiaController::class)->whereNumber('noticia')->name('noticias.imagen');

// API pública de reportes anónimos (HU2.2). El componente Livewire usa la misma lógica sin pasar por HTTP.
Route::post('reportar', [ReporteController::class, 'store'])
    ->middleware('throttle:reportar')
    ->name('reportar.store');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
