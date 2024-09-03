<?php

namespace Microcrud\Middlewares;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class LocaleMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $locales = (config()->has('app.locales')) ? config('app.locales') : config('microcrud.locales');
        if ($acceptLanguages = $request->header('Accept-Language')) {
            $lang = null;
            foreach ($locales as $locale) {
                if (str_contains($acceptLanguages, $locale)) {
                    $lang = $locale;
                    break;
                }
            }
            if (!$lang) {
                $locale = (config()->has('app.locale')) ? config('app.locale', 'uz') : config('microcrud.locale', 'uz');
                App::setLocale(config('app.locale', $locale));
            } else {
                App::setLocale($lang);
            }
        } else {
            App::setLocale($request->getPreferredLanguage($locales));
        }
        return $next($request);
    }
}
