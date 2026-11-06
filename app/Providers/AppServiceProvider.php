<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        foreach (['upload-start' => 20, 'upload-chunks' => 240, 'upload-status' => 120] as $name => $requestsPerMinute) {
            RateLimiter::for($name, fn (Request $request): Limit => Limit::perMinute($requestsPerMinute)->by($request->user()?->id ?? $request->ip()));
        }
    }
}
