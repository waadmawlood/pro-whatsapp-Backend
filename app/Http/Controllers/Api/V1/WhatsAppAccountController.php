<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\WhatsAppAccountStatus;
use App\Enums\WhatsAppConnectionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWhatsAppAccountRequest;
use App\Http\Requests\UpdateWhatsAppAccountRequest;
use App\Http\Resources\WhatsAppAccountResource;
use App\Http\Resources\WhatsAppBridgeResource;
use App\Http\Responses\ApiResponse;
use App\Models\WhatsAppAccount;
use App\Services\AuditLogger;
use App\Services\WhatsApp\WhatsAppBridgeClient;
use App\Services\WhatsApp\WhatsAppBridgeStateResolver;
use App\Support\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;

class WhatsAppAccountController extends Controller
{
    public function __construct(protected AuditLogger $auditLogger)
    {
        $this->authorizeResource(WhatsAppAccount::class, 'whatsappAccount');
    }

    public function index(): JsonResponse
    {
        $accounts = WhatsAppAccount::query()->latest()->get();

        return ApiResponse::success(WhatsAppAccountResource::collection($accounts));
    }

    public function store(StoreWhatsAppAccountRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['company_id'] = $request->user()->company_id;
        $connectionType = WhatsAppConnectionType::tryFrom($data['connection_type'] ?? 'web')
            ?? WhatsAppConnectionType::Web;
        $data['connection_type'] = $connectionType;
        $data['webhook_verify_token'] ??= Str::random(40);
        $data['status'] = WhatsAppAccountStatus::Pending;

        if ($connectionType === WhatsAppConnectionType::Web) {
            $data['phone_number'] ??= 'web-pending-'.Str::uuid();
        } else {
            $data['phone_number'] = PhoneNumber::normalize($data['phone_number']);
        }

        if ($request->boolean('is_default')) {
            WhatsAppAccount::query()->where('company_id', $request->user()->company_id)->update(['is_default' => false]);
        }

        $account = WhatsAppAccount::create($data);

        $this->auditLogger->log(
            'whatsapp.created',
            $account,
            sprintf('%s added WhatsApp account %s', $request->user()->name, $account->name),
        );

        return ApiResponse::created(new WhatsAppAccountResource($account));
    }

    public function show(WhatsAppAccount $whatsappAccount): JsonResponse
    {
        return ApiResponse::resource(new WhatsAppAccountResource($whatsappAccount));
    }

    public function update(UpdateWhatsAppAccountRequest $request, WhatsAppAccount $whatsappAccount): JsonResponse
    {
        $data = $request->validated();

        if (isset($data['phone_number'])) {
            $data['phone_number'] = PhoneNumber::normalize($data['phone_number']);
        }

        if ($request->boolean('is_default')) {
            WhatsAppAccount::query()->where('company_id', $request->user()->company_id)->update(['is_default' => false]);
        }

        $whatsappAccount->update($data);

        $this->auditLogger->log(
            'whatsapp.updated',
            $whatsappAccount,
            sprintf('%s updated WhatsApp account %s', $request->user()->name, $whatsappAccount->name),
        );

        return ApiResponse::resource(new WhatsAppAccountResource($whatsappAccount));
    }

    public function destroy(Request $request, WhatsAppAccount $whatsappAccount): JsonResponse
    {
        if ($whatsappAccount->isWebConnection()) {
            try {
                (new WhatsAppBridgeClient($whatsappAccount))->logout();
            } catch (RuntimeException) {
                // Bridge may already be offline.
            }
        }

        $whatsappAccount->delete();

        $this->auditLogger->log(
            'whatsapp.deleted',
            $whatsappAccount,
            sprintf('%s deleted WhatsApp account %s', $request->user()->name, $whatsappAccount->name),
        );

        return ApiResponse::success(null, __('WhatsApp account deleted.'));
    }

    public function bridgeState(Request $request, WhatsAppAccount $whatsappAccount, WhatsAppBridgeStateResolver $bridgeState): JsonResponse
    {
        $this->authorize('view', $whatsappAccount);

        if (! $whatsappAccount->isWebConnection()) {
            return ApiResponse::error(__('This account does not use WhatsApp Web.'), 422);
        }

        $state = $bridgeState->resolve($whatsappAccount, $request->boolean('refresh'));

        return ApiResponse::success(new WhatsAppBridgeResource([
            ...$state,
            'account' => $whatsappAccount->fresh(),
        ]));
    }

    public function bridgeStatus(WhatsAppAccount $whatsappAccount, WhatsAppBridgeStateResolver $bridgeState): JsonResponse
    {
        $this->authorize('view', $whatsappAccount);

        if (! $whatsappAccount->isWebConnection()) {
            return ApiResponse::error(__('This account does not use WhatsApp Web.'), 422);
        }

        return ApiResponse::success(new WhatsAppBridgeResource([
            ...$bridgeState->resolve($whatsappAccount),
            'account' => $whatsappAccount->fresh(),
        ]));
    }

    public function bridgeQr(WhatsAppAccount $whatsappAccount, WhatsAppBridgeStateResolver $bridgeState): JsonResponse
    {
        $this->authorize('view', $whatsappAccount);

        if (! $whatsappAccount->isWebConnection()) {
            return ApiResponse::error(__('This account does not use WhatsApp Web.'), 422);
        }

        $state = $bridgeState->resolve($whatsappAccount, refresh: true);

        if (! $state['qr_available']) {
            return ApiResponse::error(__('QR code is not available yet. Call bridge/connect first.'), 404);
        }

        return ApiResponse::success(new WhatsAppBridgeResource([
            ...$state,
            'account' => $whatsappAccount->fresh(),
        ]));
    }

    public function bridgeConnect(WhatsAppAccount $whatsappAccount, WhatsAppBridgeStateResolver $bridgeState): JsonResponse
    {
        $this->authorize('update', $whatsappAccount);

        if (! $whatsappAccount->isWebConnection()) {
            return ApiResponse::error(__('This account does not use WhatsApp Web.'), 422);
        }

        try {
            $result = (new WhatsAppBridgeClient($whatsappAccount))->startSession();
        } catch (RuntimeException $exception) {
            return ApiResponse::error($exception->getMessage(), 503);
        }

        $whatsappAccount->update(array_filter([
            'status' => WhatsAppAccountStatus::Pending,
            'bridge_qr' => $result['qr'] ?? null,
            'metadata' => array_merge($whatsappAccount->metadata ?? [], [
                'bridge_status' => $result['status'] ?? 'connecting',
            ]),
        ], fn ($value) => $value !== null));

        $state = $bridgeState->fromBridgePayload($whatsappAccount, $result);

        return ApiResponse::success([
            'account' => new WhatsAppAccountResource($whatsappAccount->fresh()),
            'bridge' => new WhatsAppBridgeResource($state),
        ], __('WhatsApp Web session started. Scan the QR code to connect.'));
    }

    public function bridgeDisconnect(WhatsAppAccount $whatsappAccount): JsonResponse
    {
        $this->authorize('update', $whatsappAccount);

        if (! $whatsappAccount->isWebConnection()) {
            return ApiResponse::error(__('This account does not use WhatsApp Web.'), 422);
        }

        try {
            $result = (new WhatsAppBridgeClient($whatsappAccount))->logout();
        } catch (RuntimeException $exception) {
            return ApiResponse::error($exception->getMessage(), 503);
        }

        $whatsappAccount->update([
            'status' => WhatsAppAccountStatus::Disconnected,
            'bridge_qr' => null,
            'bridge_connected_at' => null,
        ]);

        return ApiResponse::success([
            'account' => new WhatsAppAccountResource($whatsappAccount->fresh()),
            'bridge' => $result,
        ], __('WhatsApp Web session disconnected.'));
    }
}
