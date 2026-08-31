<?php

namespace App\Services\WhatsApp;

use App\Enums\WhatsAppAccountStatus;
use App\Models\WhatsAppAccount;
use RuntimeException;

class WhatsAppBridgeStateResolver
{
    /**
     * @return array<string, mixed>
     */
    public function resolve(WhatsAppAccount $account, bool $refresh = false): array
    {
        $account = $account->fresh();

        if (! $refresh && filled($account->bridge_qr)) {
            return $this->normalize(
                status: (string) ($account->metadata['bridge_status'] ?? 'qr'),
                qr: $account->bridge_qr,
                phoneNumber: $account->phone_number,
                message: null,
                source: 'database',
                account: $account,
            );
        }

        if ($account->status === WhatsAppAccountStatus::Connected && filled($account->bridge_connected_at)) {
            return $this->normalize(
                status: 'connected',
                qr: null,
                phoneNumber: $account->phone_number,
                message: __('WhatsApp Web is connected.'),
                source: 'database',
                account: $account,
            );
        }

        try {
            $client = new WhatsAppBridgeClient($account);
            $bridge = $refresh
                ? $client->qr()
                : $this->fetchBridgeState($client);

            $status = (string) ($bridge['status'] ?? 'disconnected');
            $qr = $bridge['qr'] ?? null;

            if ($status === 'connected' || filled($bridge['phone_number'] ?? null)) {
                return $this->normalize(
                    status: 'connected',
                    qr: null,
                    phoneNumber: $bridge['phone_number'] ?? $account->phone_number,
                    message: $bridge['message'] ?? __('WhatsApp Web is connected.'),
                    source: 'bridge',
                    account: $account->fresh(),
                );
            }

            if (filled($qr)) {
                $account->forceFill([
                    'bridge_qr' => $qr,
                    'metadata' => array_merge($account->metadata ?? [], [
                        'bridge_status' => 'qr',
                    ]),
                ])->save();

                return $this->normalize(
                    status: 'qr',
                    qr: $qr,
                    phoneNumber: null,
                    message: __('Scan the QR code with WhatsApp on your phone.'),
                    source: 'bridge',
                    account: $account->fresh(),
                );
            }

            return $this->normalize(
                status: $status,
                qr: null,
                phoneNumber: $bridge['phone_number'] ?? null,
                message: $bridge['message'] ?? null,
                source: 'bridge',
                account: $account,
            );
        } catch (RuntimeException) {
            if (filled($account->bridge_qr)) {
                return $this->normalize(
                    status: 'qr',
                    qr: $account->bridge_qr,
                    phoneNumber: null,
                    message: __('Scan the QR code with WhatsApp on your phone.'),
                    source: 'database',
                    account: $account,
                );
            }

            return $this->normalize(
                status: (string) ($account->metadata['bridge_status'] ?? 'disconnected'),
                qr: null,
                phoneNumber: $account->phone_number,
                message: __('WhatsApp bridge is unavailable. Start the bridge service first.'),
                source: 'database',
                account: $account,
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function fromBridgePayload(WhatsAppAccount $account, array $bridge): array
    {
        $status = (string) ($bridge['status'] ?? 'disconnected');
        $qr = $bridge['qr'] ?? $account->bridge_qr;

        return $this->normalize(
            status: $status,
            qr: $qr,
            phoneNumber: $bridge['phone_number'] ?? $account->phone_number,
            message: $bridge['message'] ?? null,
            source: 'bridge',
            account: $account->fresh(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function normalize(
        string $status,
        ?string $qr,
        ?string $phoneNumber,
        ?string $message,
        string $source,
        WhatsAppAccount $account,
    ): array {
        $isPlaceholderPhone = is_string($phoneNumber) && str_starts_with($phoneNumber, 'web-pending-');

        return [
            'status' => $status,
            'qr_available' => filled($qr),
            'qr_data_url' => $qr,
            'phone_number' => $isPlaceholderPhone ? null : $phoneNumber,
            'message' => $message,
            'source' => $source,
            'is_connected' => $status === 'connected' || $account->status === WhatsAppAccountStatus::Connected,
            'poll_after_ms' => match ($status) {
                'connecting' => 3000,
                'qr' => 8000,
                default => null,
            },
            'qr_rotates' => $status === 'qr',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function fetchBridgeState(WhatsAppBridgeClient $client): array
    {
        $bridge = $client->status();

        if (in_array($bridge['status'] ?? null, ['qr', 'connecting'], true) && empty($bridge['qr'])) {
            $bridge = array_merge($bridge, $client->qr());
        }

        return $bridge;
    }
}
