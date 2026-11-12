<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\ScopeToMentor;
use App\Http\Middleware\TrustFrontendClientIp;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(TrustFrontendClientIp::class);

        $middleware->alias([
            'admin' => EnsureAdmin::class,
            'mentor.scope' => ScopeToMentor::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
