<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Permissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(protected AuditLogger $auditLogger)
    {
        $this->authorizeResource(User::class, 'user');
    }

    public function index(Request $request): JsonResponse
    {
        $users = User::query()
            ->with(['roles', 'permissions'])
            ->when($request->filled('q'), function ($query) use ($request): void {
                $q = '%'.$request->string('q').'%';
                $query->where(fn ($builder) => $builder->where('name', 'like', $q)->orWhere('email', 'like', $q));
            })
            ->when($request->filled('role'), fn ($query) => $query->role($request->string('role')))
            ->latest()
            ->paginate($this->perPage($request));

        return ApiResponse::paginated($users, UserResource::class);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = User::create([
            'company_id' => $request->user()->company_id,
            'name' => $request->validated('name'),
            'email' => strtolower($request->validated('email')),
            'phone' => $request->validated('phone'),
            'password' => $request->validated('password'),
            'locale' => $request->validated('locale') ?? $request->user()->locale,
            'is_active' => $request->boolean('is_active', true),
        ]);

        $user->assignRole($request->validated('role'));

        if ($request->validated('role') === 'employee') {
            $user->syncPermissions($request->validated('permissions') ?? Permissions::employeeDefaults());
        }

        $this->auditLogger->log('user.created', $user, sprintf('%s created user %s', $request->user()->name, $user->name));

        return ApiResponse::created(new UserResource($user->load(['roles', 'permissions'])));
    }

    public function show(User $user): JsonResponse
    {
        return ApiResponse::resource(new UserResource($user->load(['roles', 'permissions', 'company'])));
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $data = $request->safe()->except(['password', 'role', 'permissions']);

        if ($request->filled('email')) {
            $data['email'] = strtolower($request->validated('email'));
        }

        if ($request->filled('password')) {
            $data['password'] = $request->validated('password');
        }

        $user->update($data);

        if ($request->filled('role')) {
            $user->syncRoles([$request->validated('role')]);
        }

        if ($request->exists('permissions') && ! $user->isAdmin()) {
            $user->syncPermissions($request->validated('permissions') ?? []);
        }

        $this->auditLogger->log('user.updated', $user, sprintf('%s updated user %s', $request->user()->name, $user->name));

        return ApiResponse::resource(new UserResource($user->fresh(['roles', 'permissions'])));
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $user->tokens()->delete();
        $user->delete();

        $this->auditLogger->log('user.deleted', $user, sprintf('%s deleted user %s', $request->user()->name, $user->name));

        return ApiResponse::success(null, __('User deleted.'));
    }

    protected function perPage(Request $request): int
    {
        return min(max($request->integer('per_page', 20), 1), 100);
    }
}
