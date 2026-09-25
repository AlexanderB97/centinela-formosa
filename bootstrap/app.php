<?php

use App\Http\Middleware\EnsureIsAdmin;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: [__DIR__.'/../routes/web.php', __DIR__.'/../routes/staff.php'],
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin.staff' => EnsureIsAdmin::class,
        ]);

        // POST /analizar and POST /reportar are public anonymous APIs: no session form, so no CSRF token.
        $middleware->validateCsrfTokens(except: ['analizar', 'reportar']);

        $middleware->redirectGuestsTo(
            fn (Request $request) => $request->is('staff', 'staff/*') ? route('staff.login') : route('login'),
        );

        $middleware->redirectUsersTo(
            fn (Request $request) => $request->is('staff', 'staff/*') ? route('staff.dashboard') : route('dashboard'),
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson()
                || ($request->is('analizar', 'reportar') && $request->isMethod('POST')),
        );
    })->create();
