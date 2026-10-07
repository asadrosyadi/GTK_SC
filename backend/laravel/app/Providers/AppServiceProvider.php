<?php

namespace App\Providers;

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        Schema::defaultStringLength(191);

        $user = fn (Request $r) => 'u' . ($r->user()?->id ?? $r->ip());
        RateLimiter::for('api', fn (Request $r) => Limit::perMinute(120)->by('api:' . $user($r)));
        RateLimiter::for('api-login', fn (Request $r) => Limit::perMinute(10)->by('login:' . $r->ip() . '|' . strtolower((string) $r->input('email'))));
        RateLimiter::for('api-forgot', fn (Request $r) => Limit::perMinute(5)->by('forgot:' . $r->ip()));
        RateLimiter::for('api-password', fn (Request $r) => Limit::perMinute(10)->by('password:' . $user($r)));
        RateLimiter::for('api-export', fn (Request $r) => Limit::perMinute(10)->by('export:' . $user($r)));
        RateLimiter::for('ai-chat', fn (Request $r) => Limit::perMinute(20)->by('ai-chat:' . $user($r)));
        RateLimiter::for('device-images', fn (Request $r) => Limit::perMinute(30)->by('images:' . ($r->header('X-IOT-ID') ?: $r->ip())));

        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }

        View::share('languages', config('app.available_locales'));

        Paginator::defaultView('components.pagination');
    }
}
