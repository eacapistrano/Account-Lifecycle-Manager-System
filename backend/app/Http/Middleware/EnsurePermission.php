<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    /**
     * @param  string  ...$chunks  Permission slugs or comma-separated lists; user must match at least one.
     */
    public function handle(Request $request, Closure $next, string ...$chunks): Response
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $slugs = [];
        foreach ($chunks as $chunk) {
            foreach (array_map('trim', explode(',', $chunk)) as $part) {
                if ($part !== '') {
                    $slugs[] = $part;
                }
            }
        }

        foreach ($slugs as $slug) {
            if ($user->hasPermission($slug)) {
                return $next($request);
            }
        }

        return response()->json(['message' => 'Forbidden.'], 403);
    }
}
