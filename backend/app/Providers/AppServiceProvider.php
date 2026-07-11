<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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
        $this->configureRateLimiting();
    }

    /**
     * Configure the application's rate limiters.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $email = Str::lower((string) $request->input('email'));

            return Limit::perMinute(5)->by($email.'|'.$request->ip());
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?? $request->ip());
        });

        RateLimiter::for('chat', function (Request $request) {
            $key = $request->user()?->id ?? $request->ip();

            return [
                Limit::perMinute(10)->by('minute:'.$key),
                Limit::perDay(50)->by('day:'.$key),
            ];
        });

        RateLimiter::for('admin-chat', function (Request $request) {
            $key = $request->user()?->id ?? $request->ip();

            return [
                Limit::perMinute(20)->by('minute:'.$key),
                Limit::perDay(200)->by('day:'.$key),
            ];
        });
    }
}
