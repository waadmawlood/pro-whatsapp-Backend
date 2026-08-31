<?php

namespace App\Jobs;

use App\Models\WhatsAppAccount;
use App\Services\WhatsApp\WhatsAppBridgeWebhookService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessBridgeConnectionWebhookJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public int $accountId,
        public array $payload,
    ) {}

    public function handle(WhatsAppBridgeWebhookService $webhooks): void
    {
        $account = WhatsAppAccount::withoutGlobalScopes()->find($this->accountId);

        if (! $account) {
            return;
        }

        $webhooks->handleConnection($account, $this->payload);
    }
}
