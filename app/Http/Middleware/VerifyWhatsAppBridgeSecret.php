<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyWhatsAppBridgeSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('whatsapp.bridge.secret');
        $provided = $request->header('X-Bridge-Secret');

        if (! $secret || ! $provided || ! hash_equals($secret, $provided)) {
            return response()->json(['message' => 'Invalid bridge secret.'], 403);
        }

        return $next($request);
    }
}
