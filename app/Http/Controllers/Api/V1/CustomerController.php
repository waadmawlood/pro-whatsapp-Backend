<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\StoreNoteRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\NoteResource;
use App\Http\Responses\ApiResponse;
use App\Models\Customer;
use App\Services\CustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function __construct(protected CustomerService $customers)
    {
        $this->authorizeResource(Customer::class, 'customer');
    }

    public function index(Request $request): JsonResponse
    {
        $customers = Customer::query()
            ->with(['tags', 'assignedUser'])
            ->search($request->string('q'))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('assigned_user_id'), fn ($query) => $query->where('assigned_user_id', $request->integer('assigned_user_id')))
            ->when($request->filled('tag_id'), fn ($query) => $query->whereHas('tags', fn ($tags) => $tags->where('tags.id', $request->integer('tag_id'))))
            ->latest()
            ->paginate(min(max($request->integer('per_page', 20), 1), 100));

        return ApiResponse::paginated($customers, CustomerResource::class);
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $customer = $this->customers->create($request->user(), $request->validated());

        return ApiResponse::created(new CustomerResource($customer));
    }

    public function show(Customer $customer): JsonResponse
    {
        return ApiResponse::resource(new CustomerResource($customer->load(['tags', 'assignedUser', 'whatsappAccount'])));
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): JsonResponse
    {
        $customer = $this->customers->update($customer, $request->user(), $request->validated());

        return ApiResponse::resource(new CustomerResource($customer));
    }

    public function destroy(Customer $customer): JsonResponse
    {
        $this->authorize('delete', $customer);
        $customer->delete();

        return ApiResponse::success(null, __('Customer deleted.'));
    }

    public function notes(Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);

        return ApiResponse::success(
            NoteResource::collection($customer->notes()->with('user')->latest()->get())
        );
    }

    public function storeNote(StoreNoteRequest $request, Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);

        $note = $customer->notes()->create([
            'company_id' => $request->user()->company_id,
            'user_id' => $request->user()->id,
            'conversation_id' => $request->validated('conversation_id'),
            'body' => $request->validated('body'),
        ]);

        return ApiResponse::created(new NoteResource($note->load('user')));
    }

    public function syncTags(Request $request, Customer $customer): JsonResponse
    {
        $this->authorize('update', $customer);

        $data = $request->validate([
            'tag_ids' => ['required', 'array'],
            'tag_ids.*' => ['integer'],
        ]);

        $customer = $this->customers->update($customer, $request->user(), ['tag_ids' => $data['tag_ids']]);

        return ApiResponse::resource(new CustomerResource($customer));
    }
}
