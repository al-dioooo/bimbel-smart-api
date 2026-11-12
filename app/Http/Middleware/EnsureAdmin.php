<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Admin-only routes: master data, salary rules, approvals, single absensi edits. */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Fitur ini hanya dapat digunakan oleh Admin/Owner.');

        return $next($request);
    }
}
