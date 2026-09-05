<?php

namespace App\Jobs;

use App\Services\WhatsApp\WhatsAppWebhookService;
use App\Support\CompanyContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessIncomingWhatsAppWebhookJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(public array $payload) {}

    public function handle(WhatsAppWebhookService $webhooks): void
    {
        try {
            $webhooks->handle($this->payload);
        } finally {
            CompanyContext::clear();
        }
    }
}
