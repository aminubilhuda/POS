<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Batasi percobaan login (maks 5x per menit per email + IP)
        RateLimiter::for('login', function (Request $request) {
            $email = strtolower((string) $request->input('email', ''));

            return Limit::perMinute(5)->by($email.'|'.$request->ip());
        });

        // Batasi percobaan PIN Admin pada Void & Refund (maks 5x per menit per user + IP)
        RateLimiter::for('admin-pin', function (Request $request) {
            $userId = (string) ($request->user()?->id ?? 'guest');

            return Limit::perMinute(5)->by($userId.'|'.$request->ip());
        });
    }
}
