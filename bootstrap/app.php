<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;

$recordApiExceptionMetric = function (Throwable $e, int $status, Request $request): void {
    if (function_exists('newrelic_add_custom_parameter')) {
        newrelic_add_custom_parameter('api.error.class', class_basename($e));
        newrelic_add_custom_parameter('api.error.status', (string) $status);
        newrelic_add_custom_parameter('api.error.route', $request->path());
        newrelic_add_custom_parameter('api.error.method', $request->method());
    }

    if (function_exists('newrelic_notice_error')) {
        newrelic_notice_error($e->getMessage() ?: 'API error', $e);
    }

    if (function_exists('newrelic_record_metric')) {
        $metricName = 'Custom/ApiError/' . str_replace('\\', '_', class_basename($e)) . '/' . $status;
        newrelic_record_metric($metricName, 1);
        newrelic_record_metric('Custom/ApiError/Total', 1);
    }
};

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(\App\Http\Middleware\RequestCorrelationMiddleware::class);
    })
    ->withExceptions(function (Exceptions $exceptions) use ($recordApiExceptionMetric): void {
        $exceptions->render(function (AuthenticationException $e, Request $request) use ($recordApiExceptionMetric) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $recordApiExceptionMetric($e, 401, $request);

                return response()->json(['message' => $e->getMessage()], 401);
            }
        });

        $exceptions->render(function (\DomainException $e, Request $request) use ($recordApiExceptionMetric) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $recordApiExceptionMetric($e, 422, $request);

                return response()->json(['message' => $e->getMessage()], 422);
            }
        });

        $exceptions->render(function (\InvalidArgumentException $e, Request $request) use ($recordApiExceptionMetric) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $recordApiExceptionMetric($e, 422, $request);

                return response()->json(['message' => $e->getMessage()], 422);
            }
        });

        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, Request $request) use ($recordApiExceptionMetric) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $recordApiExceptionMetric($e, 422, $request);

                return response()->json([
                    'message' => 'The given data was invalid.',
                    'errors' => $e->errors(),
                ], 422);
            }
        });

        $exceptions->render(function (Throwable $e, Request $request) use ($recordApiExceptionMetric) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $status = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;

                if ($status < 400 || $status > 599) {
                    $status = 500;
                }

                $recordApiExceptionMetric($e, $status, $request);

                return response()->json([
                    'message' => $e->getMessage() ?: 'Server Error',
                    'error' => class_basename($e),
                ], $status);
            }
        });
    })->create();
