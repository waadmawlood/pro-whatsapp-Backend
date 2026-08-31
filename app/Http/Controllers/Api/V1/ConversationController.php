<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ConversationStatus;
use App\Enums\MessageType;
use App\Http\Controllers\Controller;
use App\Http\Requests\AssignConversationRequest;
use App\Http\Requests\StoreMessageRequest;
use App\Http\Requests\UpdateConversationRequest;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Http\Responses\ApiResponse;
use App\Models\Conversation;
use App\Models\User;
use App\Services\ConversationService;
use App\Services\MessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class ConversationController extends Controller
{
    public function __construct(
        protected ConversationService $conversations,
        protected MessageService $messages,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Conversation::class);

        $conversations = Conversation::query()
            ->with(['customer.tags', 'assignedUser', 'whatsappAccount'])
            ->visibleTo($request->user())
            ->search($request->string('q'))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->boolean('unassigned'), fn ($query) => $query->unassigned())
            ->when($request->filled('assigned_user_id'), fn ($query) => $query->where('assigned_user_id', $request->integer('assigned_user_id')))
            ->when($request->filled('customer_id'), fn ($query) => $query->where('customer_id', $request->integer('customer_id')))
            ->when($request->filled('link_id'), fn ($query) => $query->where('link_id', $request->string('link_id')))
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate(min(max($request->integer('per_page', 20), 1), 100));

        return ApiResponse::paginated($conversations, ConversationResource::class);
    }

    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        if ($request->boolean('mark_read', true)) {
            $this->conversations->markRead($conversation);
        }

        return ApiResponse::resource(
            new ConversationResource($conversation->fresh(['customer.tags', 'assignedUser', 'whatsappAccount']))
        );
    }

    public function update(UpdateConversationRequest $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('update', $conversation);

        $conversation = $this->conversations->update($conversation, $request->user(), $request->validated());

        return ApiResponse::resource(new ConversationResource($conversation));
    }

    public function messages(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $messages = $conversation->messages()
            ->with(['media', 'user'])
            ->when($request->filled('q'), fn ($query) => $query->where('body', 'like', '%'.$request->string('q').'%'))
            ->latest('id')
            ->paginate(min(max($request->integer('per_page', 30), 1), 100));

        return ApiResponse::paginated($messages, MessageResource::class);
    }

    public function storeMessage(StoreMessageRequest $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('sendMessage', $conversation);

        if ($conversation->status === ConversationStatus::Closed) {
            return ApiResponse::error(__('Conversation is closed.'), 422);
        }

        $file = $request->file('file');
        $type = $this->resolveType($request->input('type'), $file);

        $message = $this->messages->send($conversation, $request->user(), [
            'type' => $type->value,
            'body' => $request->validated('body'),
            'caption' => $request->validated('caption'),
        ], $file);

        return ApiResponse::created(new MessageResource($message));
    }

    public function assign(AssignConversationRequest $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('assign', $conversation);

        $assignee = $request->filled('user_id')
            ? User::query()->where('company_id', $request->user()->company_id)->findOrFail($request->integer('user_id'))
            : null;

        $conversation = $this->conversations->assign($conversation, $assignee, $request->user());

        return ApiResponse::resource(new ConversationResource($conversation));
    }

    public function close(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('close', $conversation);

        return ApiResponse::resource(
            new ConversationResource($this->conversations->close($conversation, $request->user()))
        );
    }

    public function reopen(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('close', $conversation);

        return ApiResponse::resource(
            new ConversationResource($this->conversations->reopen($conversation, $request->user()))
        );
    }

    public function destroy(Conversation $conversation): JsonResponse
    {
        $this->authorize('delete', $conversation);
        $conversation->delete();

        return ApiResponse::success(null, __('Conversation deleted.'));
    }

    protected function resolveType(?string $type, ?UploadedFile $file): MessageType
    {
        if ($type) {
            return MessageType::from($type);
        }

        if (! $file) {
            return MessageType::Text;
        }

        $mime = (string) $file->getMimeType();

        return match (true) {
            str_starts_with($mime, 'image/') => MessageType::Image,
            str_starts_with($mime, 'video/') => MessageType::Video,
            str_starts_with($mime, 'audio/') => MessageType::Audio,
            default => MessageType::Document,
        };
    }
}
