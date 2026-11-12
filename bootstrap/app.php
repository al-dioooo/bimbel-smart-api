<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\ScopeToMentor;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => EnsureAdmin::class,
            'mentor.scope' => ScopeToMentor::class,
        ]);

        // There is no web login page: a guest gets a 401, never a redirect to
        // route('login'), which does not exist and turned into a 500.
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // The API never redirects: a download request (Accept: application/pdf)
        // that fails validation or authentication still gets a JSON 4xx.
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*') || $request->expectsJson());
    })->create();
