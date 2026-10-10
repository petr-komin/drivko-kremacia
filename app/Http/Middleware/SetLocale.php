<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * Jazyk prostředí: přihlášený podle profilu (users.locale),
 * nepřihlášený podle prohlížeče (Accept-Language).
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locales = array_keys(config('kremacia.locales'));
        $locale  = $request->user()?->locale;

        if (! in_array($locale, $locales, true)) {
            $locale = $request->getPreferredLanguage($locales) ?: config('app.locale');
        }

        app()->setLocale($locale);
        Carbon::setLocale($locale);

        return $next($request);
    }
}
