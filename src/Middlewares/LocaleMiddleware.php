<?php

namespace Microcrud\Middlewares;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;

/**
 * LocaleMiddleware
 *
 * Handles application locale detection from HTTP headers.
 * Priority: Accept-Language header > Browser preferred language > Default locale
 */
class LocaleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $locale = $this->detectLocale($request);

        if ($locale) {
            App::setLocale($locale);
        }

        return $next($request);
    }

    /**
     * Detect the appropriate locale from the request.
     *
     * @param Request $request
     * @return string|null
     */
    protected function detectLocale(Request $request): ?string
    {
        $supportedLocales = $this->getSupportedLocales();
        $defaultLocale = $this->getDefaultLocale();

        // Check for custom locale header (highest priority)
        if ($customLocale = $request->header('X-Locale')) {
            if (in_array($customLocale, $supportedLocales)) {
                return $customLocale;
            }
        }

        // Parse Accept-Language header
        if ($acceptLanguage = $request->header('Accept-Language')) {
            $locale = $this->parseAcceptLanguage($acceptLanguage, $supportedLocales);
            if ($locale) {
                return $locale;
            }
        }

        // Fallback to browser's preferred language
        $preferredLanguage = $request->getPreferredLanguage($supportedLocales);
        if ($preferredLanguage) {
            return $preferredLanguage;
        }

        // Return default locale
        return $defaultLocale;
    }

    /**
     * Parse Accept-Language header and find best match.
     *
     * @param string $acceptLanguage
     * @param array $supportedLocales
     * @return string|null
     */
    protected function parseAcceptLanguage(string $acceptLanguage, array $supportedLocales): ?string
    {
        // Parse Accept-Language header (e.g., "en-US,en;q=0.9,ru;q=0.8")
        $languages = [];

        foreach (explode(',', $acceptLanguage) as $lang) {
            $parts = explode(';', trim($lang));
            $locale = trim($parts[0]);

            // Extract quality value (default 1.0)
            $quality = 1.0;
            if (isset($parts[1]) && preg_match('/q=([\d.]+)/', $parts[1], $matches)) {
                $quality = (float) $matches[1];
            }

            $languages[$locale] = $quality;
        }

        // Sort by quality (highest first)
        arsort($languages);

        // Find best matching locale
        foreach (array_keys($languages) as $lang) {
            // Exact match
            if (in_array($lang, $supportedLocales)) {
                return $lang;
            }

            // Try language without region (e.g., "en" from "en-US")
            $langCode = strtok($lang, '-');
            if (in_array($langCode, $supportedLocales)) {
                return $langCode;
            }

            // Try matching any locale that starts with this language
            foreach ($supportedLocales as $supported) {
                if (strpos($supported, $langCode) === 0) {
                    return $supported;
                }
            }
        }

        return null;
    }

    /**
     * Get supported locales from configuration.
     *
     * @return array
     */
    protected function getSupportedLocales(): array
    {
        // Check app config first, then package config
        $locales = Config::has('app.locales')
            ? Config::get('app.locales')
            : Config::get('microcrud.locales', ['en']);

        return is_array($locales) ? $locales : [$locales];
    }

    /**
     * Get default locale from configuration.
     *
     * @return string
     */
    protected function getDefaultLocale(): string
    {
        return Config::has('app.locale')
            ? Config::get('app.locale', 'en')
            : Config::get('microcrud.locale', 'en');
    }
}
