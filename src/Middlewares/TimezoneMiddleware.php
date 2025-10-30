<?php

namespace Microcrud\Middlewares;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

/**
 * TimezoneMiddleware
 *
 * Handles timezone detection from HTTP headers and converts date parameters to UTC.
 * Supports: X-Timezone header, Timezone header, or falls back to config default.
 */
class TimezoneMiddleware
{
    /**
     * Common date field names to convert.
     *
     * @var array
     */
    protected $dateFields = [
        'created_at',
        'updated_at',
        'deleted_at',
        'start_date',
        'end_date',
        'from_date',
        'to_date',
        'date',
        'datetime',
        'timestamp',
    ];

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $timezone = $this->detectTimezone($request);

        if ($timezone && $this->isValidTimezone($timezone)) {
            // Set PHP timezone
            date_default_timezone_set($timezone);

            // Store timezone in request for later use
            $request->attributes->set('_timezone', $timezone);

            // Convert date parameters to UTC
            $this->convertDatesToUTC($request, $timezone);
        }

        return $next($request);
    }

    /**
     * Detect timezone from request headers.
     *
     * @param Request $request
     * @return string
     */
    protected function detectTimezone(Request $request): string
    {
        $defaultTimezone = Config::get('microcrud.timezone', 'UTC');

        // Priority: X-Timezone > Timezone > Default
        return $request->header('X-Timezone')
            ?? $request->header('Timezone')
            ?? $defaultTimezone;
    }

    /**
     * Check if timezone identifier is valid.
     *
     * @param string $timezone
     * @return bool
     */
    protected function isValidTimezone(string $timezone): bool
    {
        try {
            new \DateTimeZone($timezone);
            return true;
        } catch (\Exception $e) {
            Log::warning("Invalid timezone provided: {$timezone}. Using default UTC.", [
                'exception' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Convert date parameters from client timezone to UTC.
     *
     * @param Request $request
     * @param string $timezone
     * @return void
     */
    protected function convertDatesToUTC(Request $request, string $timezone): void
    {
        $converted = [];

        foreach ($this->dateFields as $field) {
            if ($request->has($field)) {
                $value = $request->input($field);
                $convertedValue = $this->convertToUTC($value, $timezone);

                if ($convertedValue !== null) {
                    $converted[$field] = $convertedValue;
                }
            }
        }

        // Merge converted dates back into request
        if (!empty($converted)) {
            $request->merge($converted);
        }
    }

    /**
     * Convert a date value to UTC.
     *
     * @param mixed $value
     * @param string $timezone
     * @return string|null
     */
    protected function convertToUTC($value, string $timezone): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            // Handle Unix timestamp (integer)
            if (is_numeric($value)) {
                return Carbon::createFromTimestamp($value, $timezone)
                    ->setTimezone('UTC')
                    ->toDateTimeString();
            }

            // Handle date string
            if (is_string($value)) {
                return Carbon::parse($value, $timezone)
                    ->setTimezone('UTC')
                    ->toDateTimeString();
            }

            // Handle Carbon instance
            if ($value instanceof Carbon) {
                return $value->setTimezone('UTC')->toDateTimeString();
            }

            // Handle DateTime instance
            if ($value instanceof \DateTime) {
                return Carbon::instance($value)
                    ->setTimezone('UTC')
                    ->toDateTimeString();
            }
        } catch (\Exception $e) {
            Log::debug("Failed to convert date value to UTC: " . json_encode($value), [
                'timezone' => $timezone,
                'exception' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Set custom date fields to convert.
     *
     * @param array $fields
     * @return $this
     */
    public function setDateFields(array $fields): self
    {
        $this->dateFields = $fields;
        return $this;
    }

    /**
     * Add additional date fields to convert.
     *
     * @param array $fields
     * @return $this
     */
    public function addDateFields(array $fields): self
    {
        $this->dateFields = array_unique(array_merge($this->dateFields, $fields));
        return $this;
    }
}
