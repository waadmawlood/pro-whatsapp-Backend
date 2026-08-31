<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->header('Accept-Language', $request->user()?->locale ?? config('app.locale'));
        $locale = strtolower(substr((string) $locale, 0, 2));

        if (in_array($locale, ['ar', 'en'], true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
