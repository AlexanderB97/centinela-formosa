<?php

use App\Http\Controllers\Staff\LoginController;
use App\Http\Controllers\Staff\ModeracionController;
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

        // Cola de moderación (HU2.3): cualquier staff, moderador o admin.
        Route::livewire('reportes', 'pages::staff.reportes')->name('reportes');
        Route::post('reportes/{reporte}/confirmar', [ModeracionController::class, 'confirmar'])->name('reportes.confirmar');
        Route::post('reportes/{reporte}/descartar', [ModeracionController::class, 'descartar'])->name('reportes.descartar');

        // Noticias: cualquier staff crea, edita, publica y despublica. auth:staff también corre en cada
        // request de Livewire (es persistente por defecto), así que no hace falta un middleware propio.
        Route::livewire('noticias', 'pages::staff.noticias')->name('noticias');
        Route::livewire('noticias/crear', 'pages::staff.editar-noticia')->name('noticias.crear');
        Route::livewire('noticias/{noticia}/editar', 'pages::staff.editar-noticia')->whereNumber('noticia')->name('noticias.editar');

        // Gestión de staff (HU1.2): solo admins; un moderador recibe 403.
        Route::middleware('admin.staff')->group(function () {
            Route::livewire('usuarios', 'pages::staff.usuarios')->name('usuarios');
            Route::post('usuarios', [UsuarioController::class, 'store'])->name('usuarios.store');
        });
    });
});
