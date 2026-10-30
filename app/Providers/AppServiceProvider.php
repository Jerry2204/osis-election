<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

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
        \Illuminate\Support\Facades\RateLimiter::for('nis', fn (\Illuminate\Http\Request $request) =>
            \Illuminate\Cache\RateLimiting\Limit::perMinute(30)->by($request->ip())->response(fn () =>
                response()->view('errors.problem', ['code' => 429, 'message' => 'Terlalu banyak percobaan. Tunggu satu menit, lalu coba kembali.'], 429)
            )
        );
    }
}
