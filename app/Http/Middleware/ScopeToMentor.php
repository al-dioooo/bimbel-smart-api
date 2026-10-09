<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pins ?mentor_id= to the signed-in mentor, overriding whatever the client
 * sent, so every list and report that filters on it only returns their own
 * kelas. Admins pass through untouched.
 */
class ScopeToMentor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->isAdmin()) {
            // -1 matches nothing; an empty filter would match everything.
            $request->query->set('mentor_id', $user->mentor?->id ?? -1);
        }

        return $next($request);
    }
}
