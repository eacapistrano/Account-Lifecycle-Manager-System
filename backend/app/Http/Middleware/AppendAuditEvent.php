<?php

namespace App\Http\Middleware;

use App\Services\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AppendAuditEvent
{
    public function __construct(
        protected AuditLogger $audit,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $response;
        }

        $path = ltrim((string) $request->path(), '/');

        // These flows already emit per-item audit records in controller/job code.
        if (in_array($path, ['api/students/import', 'api/students/sync', 'api/students/suspend', 'api/students/delete'], true)) {
            return $response;
        }

        $payload = $this->sanitizePayload($request->all());
        $routeName = $request->route()?->getName();
        $action = $routeName ?: $request->method().' '.$path;
        $module = str_starts_with($path, 'api/policies')
            ? 'policy_execution'
            : ((str_starts_with($path, 'api/suspended-accounts') || str_starts_with($path, 'api/suspended'))
                ? 'suspended_accounts'
                : 'api');

        $this->audit->record(
            $module,
            $action,
            null,
            [
                'request' => $payload,
                'response_status' => $response->getStatusCode(),
            ],
            $response->getStatusCode() < 400,
            $response->getStatusCode() < 400 ? null : 'Request returned non-success status.',
            null,
            $request,
        );

        return $response;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function sanitizePayload(array $payload): array
    {
        $sensitiveKeys = ['password', 'token', 'secret', 'api_key'];
        $clean = [];

        foreach ($payload as $key => $value) {
            $normalizedKey = strtolower((string) $key);
            if (in_array($normalizedKey, $sensitiveKeys, true)) {
                $clean[$key] = '[redacted]';

                continue;
            }

            if (is_array($value)) {
                $clean[$key] = $this->sanitizePayload($value);

                continue;
            }

            $clean[$key] = $value;
        }

        return $clean;
    }
}
