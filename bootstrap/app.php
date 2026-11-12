<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\ScopeToMentor;
use App\Http\Middleware\TrustFrontendClientIp;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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

        // There is no web login page: a guest gets a 401, never a redirect to
        // route('login'), which does not exist and turned into a 500.
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // The API never redirects: a download request (Accept: application/pdf)
        // that fails validation or authentication still gets a JSON 4xx.
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*') || $request->expectsJson());

        // §3.1 e (N3): the framework's own API errors in Indonesian.
        $api = fn (Request $request) => $request->is('api/*') || $request->expectsJson();
        $exceptions->render(fn (AuthenticationException $e, Request $request) => $api($request)
            ? response()->json(['message' => 'Sesi Anda telah berakhir atau Anda belum login. Silakan login kembali.'], 401)
            : null);
        $exceptions->render(fn (NotFoundHttpException $e, Request $request) => $api($request)
            ? response()->json(['message' => $e->getPrevious() instanceof ModelNotFoundException ? 'Data tidak ditemukan.' : 'Alamat tidak ditemukan.'], 404)
            : null);
        $exceptions->render(fn (MethodNotAllowedHttpException $e, Request $request) => $api($request)
            ? response()->json(['message' => 'Metode permintaan tidak didukung untuk alamat ini.'], 405)
            : null);
        $exceptions->render(fn (AccessDeniedHttpException $e, Request $request) => $api($request) && $e->getMessage() === 'This action is unauthorized.'
            ? response()->json(['message' => 'Anda tidak memiliki izin untuk tindakan ini.'], 403)
            : null);
        $exceptions->render(fn (ThrottleRequestsException $e, Request $request) => $api($request)
            ? response()->json(['message' => 'Terlalu banyak permintaan. Silakan coba lagi dalam '.($e->getHeaders()['Retry-After'] ?? 60).' detik.'], 429, $e->getHeaders())
            : null);
    })->create();
