<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class SetCompanyContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user() ?? Auth::guard('sanctum')->user();

        if ($user) {
            app()->instance('current_company_id', $user->company_id);
            app(PermissionRegistrar::class)->setPermissionsTeamId($user->company_id);
            $user->forceFill(['last_seen_at' => now()])->saveQuietly();
        }

        return $next($request);
    }
}
