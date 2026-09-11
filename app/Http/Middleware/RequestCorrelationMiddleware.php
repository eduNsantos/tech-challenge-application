<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RequestCorrelationMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $request->header('X-Request-Id')
            ?? $request->headers->get('x-request-id')
            ?? (string) Str::uuid();

        $request->attributes->set('request_id', $requestId);
        $request->headers->set('X-Request-Id', $requestId);

        Log::withContext([
            'request_id' => $requestId,
            'request_method' => $request->method(),
            'request_path' => $request->path(),
            'request_ip' => $request->ip(),
            'request_user_agent' => $request->userAgent(),
        ]);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
