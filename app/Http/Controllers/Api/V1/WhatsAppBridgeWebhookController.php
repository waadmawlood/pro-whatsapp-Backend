<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessBridgeConnectionWebhookJob;
use App\Jobs\ProcessBridgeMessageWebhookJob;
use App\Jobs\ProcessBridgeStatusWebhookJob;
use App\Models\WhatsAppAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsAppBridgeWebhookController extends Controller
{
    public function connection(
        Request $request,
        WhatsAppAccount $whatsappAccount,
    ): JsonResponse {
        ProcessBridgeConnectionWebhookJob::dispatch($whatsappAccount->id, $request->all());

        return response()->json(['success' => true]);
    }

    public function message(
        Request $request,
        WhatsAppAccount $whatsappAccount,
    ): JsonResponse {
        ProcessBridgeMessageWebhookJob::dispatch($whatsappAccount->id, $request->all());

        return response()->json(['success' => true]);
    }

    public function status(
        Request $request,
        WhatsAppAccount $whatsappAccount,
    ): JsonResponse {
        ProcessBridgeStatusWebhookJob::dispatch($whatsappAccount->id, $request->all());

        return response()->json(['success' => true]);
    }
}
