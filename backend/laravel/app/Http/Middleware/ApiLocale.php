<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class ApiLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $available = array_keys(config('app.available_locales', []));
        $locale = $request->user()?->locale ?: $request->getPreferredLanguage($available) ?: 'id';
        if (in_array($locale, $available, true)) {
            App::setLocale($locale);
        }

        return $next($request);
    }
}
