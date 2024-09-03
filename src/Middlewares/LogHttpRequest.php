<?php

namespace Microcrud\Middlewares;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LogHttpRequest
{
    public function handle(Request $request, Closure $next)
    {
        // Log the requested URL
        Log::info('Requested URL: ' . $request->fullUrl());
        
        // Log request headers
        Log::info('Request Headers:');
        foreach ($request->header() as $key => $value) {
            Log::info($key . ': ' . implode(', ', $value));
        }
        
        // Log request parameters
        Log::info('Request Parameters:');
        Log::info(json_encode($request->all(), JSON_PRETTY_PRINT));
        
        return $next($request);
    }
}
