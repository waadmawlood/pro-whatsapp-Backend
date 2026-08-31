<?php

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && (! $user->is_active || ! $user->company?->is_active)) {
            optional($user->currentAccessToken())->delete();

            return ApiResponse::error(__('Your account is disabled.'), 403);
        }

        return $next($request);
    }
}
