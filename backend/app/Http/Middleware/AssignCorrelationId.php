<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AssignCorrelationId
{
    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header('X-Correlation-ID');
        $id = is_string($header) && $header !== '' ? $header : (string) Str::uuid();
        $request->attributes->set('correlation_id', $id);
        $request->headers->set('X-Correlation-ID', $id);

        /** @var Response $response */
        $response = $next($request);
        $response->headers->set('X-Correlation-ID', $id);

        return $response;
    }
}
