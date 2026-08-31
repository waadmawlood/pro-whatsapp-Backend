<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\MessageResource;
use App\Http\Responses\ApiResponse;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Support\Permissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $q = trim((string) $request->string('q'));
        $type = $request->string('type', 'all');

        if ($q === '') {
            return ApiResponse::error(__('Search query is required.'), 422);
        }

        $limit = min(max($request->integer('limit', 10), 1), 50);

        $data = [];

        if (in_array($type, ['all', 'customers'], true) && $request->user()->can(Permissions::CUSTOMERS_VIEW)) {
            $data['customers'] = CustomerResource::collection(
                Customer::query()->with('tags')->search($q)->latest()->limit($limit)->get()
            );
        }

        if (in_array($type, ['all', 'conversations'], true) && $request->user()->can(Permissions::CONVERSATIONS_VIEW)) {
            $data['conversations'] = ConversationResource::collection(
                Conversation::query()
                    ->with(['customer.tags', 'assignedUser'])
                    ->visibleTo($request->user())
                    ->search($q)
                    ->orderByDesc('last_message_at')
                    ->limit($limit)
                    ->get()
            );
        }

        if (in_array($type, ['all', 'messages'], true) && $request->user()->can(Permissions::MESSAGES_VIEW)) {
            $messages = Message::query()
                ->with(['conversation.customer', 'media', 'user'])
                ->where('body', 'like', '%'.$q.'%')
                ->whereHas('conversation', fn ($conversation) => $conversation->visibleTo($request->user()))
                ->latest()
                ->limit($limit)
                ->get();

            $data['messages'] = MessageResource::collection($messages);
        }

        return ApiResponse::success($data);
    }
}
