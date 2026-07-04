<?php

namespace App\Http\Middleware;

use App\Services\ApiAuditLogger;
use Closure;
use Throwable;

class LogIncomingApiRequest
{
    private ApiAuditLogger $logger;

    public function __construct(ApiAuditLogger $logger)
    {
        $this->logger = $logger;
    }

    public function handle($request, Closure $next)
    {
        $startedAt = microtime(true);
        $response = null;
        $exception = null;

        try {
            $response = $next($request);

            return $response;
        } catch (Throwable $throwable) {
            $exception = $throwable;

            throw $throwable;
        } finally {
            $this->logger->recordIncoming($request, $response, $exception, $startedAt);
        }
    }
}
