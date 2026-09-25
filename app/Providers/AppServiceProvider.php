<?php

namespace App\Providers;

use App\Http\Middleware\EnsureIsAdmin;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();

        // Re-run the admin check on every Livewire request of a component mounted behind it,
        // not only on the initial page load (route middleware is not re-applied otherwise).
        Livewire::addPersistentMiddleware([EnsureIsAdmin::class]);
    }

    /**
     * Limit the public analyzer API, which consumes the VirusTotal and Gemini quotas,
     * and the public report API, so the moderation queue cannot be flooded.
     * The IP only lives in the rate limiter cache; it is never stored with a report.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('analizar', fn (Request $request) => Limit::perMinute(20)->by((string) $request->ip()));
        RateLimiter::for('reportar', fn (Request $request) => Limit::perMinute(10)->by((string) $request->ip()));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
