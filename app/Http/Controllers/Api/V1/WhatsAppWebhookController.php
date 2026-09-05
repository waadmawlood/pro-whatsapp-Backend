<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessIncomingWhatsAppWebhookJob;
use App\Models\WhatsAppAccount;
use App\Services\WhatsApp\WhatsAppWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request, WhatsAppAccount $whatsappAccount): Response|JsonResponse
    {
        $mode = $request->query('hub_mode', $request->query('hub.mode'));
        $token = $request->query('hub_verify_token', $request->query('hub.verify_token'));
        $challenge = $request->query('hub_challenge', $request->query('hub.challenge'));

        if ($mode === 'subscribe' && hash_equals((string) $whatsappAccount->webhook_verify_token, (string) $token)) {
            return response((string) $challenge, 200)->header('Content-Type', 'text/plain');
        }

        return response()->json(['message' => 'Invalid verify token'], 403);
    }

    public function receive(
        Request $request,
        WhatsAppAccount $whatsappAccount,
        WhatsAppWebhookService $webhooks,
    ): JsonResponse {
        $signature = $request->header('X-Hub-Signature-256');

        if (! $webhooks->verifySignature($whatsappAccount, $request->getContent(), $signature)) {
            Log::warning('Invalid WhatsApp webhook signature', ['account_id' => $whatsappAccount->id]);

            return response()->json(['message' => 'Invalid signature'], 403);
        }

        ProcessIncomingWhatsAppWebhookJob::dispatch($request->all());

        return response()->json(['success' => true]);
    }
}
