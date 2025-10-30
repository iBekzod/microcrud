<?php

namespace Microcrud\Middlewares;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * LogHttpRequest
 *
 * Logs HTTP requests with configurable detail levels and sensitive data filtering.
 * Useful for debugging and monitoring API traffic.
 */
class LogHttpRequest
{
    /**
     * Sensitive header names to filter from logs.
     *
     * @var array
     */
    protected $sensitiveHeaders = [
        'authorization',
        'api-key',
        'x-api-key',
        'api-token',
        'x-api-token',
        'cookie',
        'set-cookie',
        'x-csrf-token',
        'x-xsrf-token',
    ];

    /**
     * Sensitive parameter names to filter from logs.
     *
     * @var array
     */
    protected $sensitiveParams = [
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
        'token',
        'access_token',
        'refresh_token',
        'api_key',
        'secret',
        'client_secret',
        'card_number',
        'cvv',
        'ssn',
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
        // Generate unique request ID
        $requestId = Str::uuid()->toString();
        $request->attributes->set('request_id', $requestId);

        // Log the incoming request
        $this->logRequest($request, $requestId);

        // Process request and capture response
        $response = $next($request);

        // Log the response
        $this->logResponse($request, $response, $requestId);

        return $response;
    }

    /**
     * Log incoming HTTP request.
     *
     * @param Request $request
     * @param string $requestId
     * @return void
     */
    protected function logRequest(Request $request, string $requestId): void
    {
        $data = [
            'request_id' => $requestId,
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ];

        // Add headers if enabled
        if ($this->shouldLogHeaders()) {
            $data['headers'] = $this->filterSensitiveData(
                $request->headers->all(),
                $this->sensitiveHeaders
            );
        }

        // Add request body if enabled
        if ($this->shouldLogBody()) {
            $data['body'] = $this->filterSensitiveData(
                $request->all(),
                $this->sensitiveParams
            );
        }

        // Add query parameters
        if (!empty($request->query())) {
            $data['query'] = $this->filterSensitiveData(
                $request->query(),
                $this->sensitiveParams
            );
        }

        Log::info('HTTP Request', $data);
    }

    /**
     * Log HTTP response.
     *
     * @param Request $request
     * @param mixed $response
     * @param string $requestId
     * @return void
     */
    protected function logResponse(Request $request, $response, string $requestId): void
    {
        $data = [
            'request_id' => $requestId,
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'status' => $response->status(),
            'duration' => $this->getRequestDuration($request),
        ];

        // Add response body if enabled and not too large
        if ($this->shouldLogResponseBody() && method_exists($response, 'content')) {
            $content = $response->content();

            if (strlen($content) < 10000) { // Max 10KB
                $data['response'] = $this->truncateResponse($content);
            } else {
                $data['response_size'] = strlen($content) . ' bytes (too large to log)';
            }
        }

        // Log at appropriate level based on status code
        $logLevel = $this->getLogLevel($response->status());
        Log::log($logLevel, 'HTTP Response', $data);
    }

    /**
     * Filter sensitive data from array.
     *
     * @param array $data
     * @param array $sensitiveKeys
     * @return array
     */
    protected function filterSensitiveData(array $data, array $sensitiveKeys): array
    {
        foreach ($data as $key => $value) {
            $lowerKey = strtolower($key);

            // Check if key matches any sensitive pattern
            foreach ($sensitiveKeys as $sensitiveKey) {
                if (Str::contains($lowerKey, $sensitiveKey)) {
                    $data[$key] = '[FILTERED]';
                    break;
                }
            }

            // Recursively filter nested arrays
            if (is_array($value)) {
                $data[$key] = $this->filterSensitiveData($value, $sensitiveKeys);
            }
        }

        return $data;
    }

    /**
     * Get request duration in milliseconds.
     *
     * @param Request $request
     * @return float
     */
    protected function getRequestDuration(Request $request): float
    {
        if (defined('LARAVEL_START')) {
            return round((microtime(true) - LARAVEL_START) * 1000, 2);
        }

        return 0;
    }

    /**
     * Get appropriate log level based on status code.
     *
     * @param int $statusCode
     * @return string
     */
    protected function getLogLevel(int $statusCode): string
    {
        if ($statusCode >= 500) {
            return 'error';
        }

        if ($statusCode >= 400) {
            return 'warning';
        }

        return 'info';
    }

    /**
     * Truncate response for logging.
     *
     * @param string $content
     * @param int $maxLength
     * @return string
     */
    protected function truncateResponse(string $content, int $maxLength = 1000): string
    {
        if (strlen($content) <= $maxLength) {
            return $content;
        }

        return substr($content, 0, $maxLength) . '... (truncated)';
    }

    /**
     * Check if request headers should be logged.
     *
     * @return bool
     */
    protected function shouldLogHeaders(): bool
    {
        return Config::get('microcrud.logging.log_headers', false);
    }

    /**
     * Check if request body should be logged.
     *
     * @return bool
     */
    protected function shouldLogBody(): bool
    {
        return Config::get('microcrud.logging.log_body', true);
    }

    /**
     * Check if response body should be logged.
     *
     * @return bool
     */
    protected function shouldLogResponseBody(): bool
    {
        return Config::get('microcrud.logging.log_response', false);
    }

    /**
     * Set custom sensitive headers.
     *
     * @param array $headers
     * @return $this
     */
    public function setSensitiveHeaders(array $headers): self
    {
        $this->sensitiveHeaders = array_map('strtolower', $headers);
        return $this;
    }

    /**
     * Set custom sensitive parameters.
     *
     * @param array $params
     * @return $this
     */
    public function setSensitiveParams(array $params): self
    {
        $this->sensitiveParams = array_map('strtolower', $params);
        return $this;
    }

    /**
     * Add additional sensitive headers.
     *
     * @param array $headers
     * @return $this
     */
    public function addSensitiveHeaders(array $headers): self
    {
        $this->sensitiveHeaders = array_unique(
            array_merge($this->sensitiveHeaders, array_map('strtolower', $headers))
        );
        return $this;
    }

    /**
     * Add additional sensitive parameters.
     *
     * @param array $params
     * @return $this
     */
    public function addSensitiveParams(array $params): self
    {
        $this->sensitiveParams = array_unique(
            array_merge($this->sensitiveParams, array_map('strtolower', $params))
        );
        return $this;
    }
}
