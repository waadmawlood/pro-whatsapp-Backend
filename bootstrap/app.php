<?php

use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\SetCompanyContext;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\VerifyWhatsAppBridgeSecret;
use App\Http\Responses\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['prefix' => 'api', 'middleware' => ['api', 'auth:sanctum']],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'bridge.secret' => VerifyWhatsAppBridgeSecret::class,
            'company' => SetCompanyContext::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);

        $middleware->api(prepend: [
            SetLocale::class,
            SetCompanyContext::class,
        ]);

        $middleware->throttleApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error(__('Unauthenticated.'), 401);
            }
        });

        $exceptions->render(function (AuthorizationException $exception, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error(__('Forbidden.'), 403);
            }
        });

        $exceptions->render(function (NotFoundHttpException $exception, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error(__('Resource not found.'), 404);
            }
        });
    })->create();
