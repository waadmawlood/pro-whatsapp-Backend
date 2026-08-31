<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Http\Responses\ApiResponse;
use App\Models\AuditLog;
use App\Support\Permissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        if (! $request->user()->can(Permissions::AUDIT_VIEW)) {
            return ApiResponse::error(__('Forbidden.'), 403);
        }

        $logs = AuditLog::query()
            ->with('user')
            ->when($request->filled('action'), fn ($query) => $query->where('action', $request->string('action')))
            ->when($request->filled('user_id'), fn ($query) => $query->where('user_id', $request->integer('user_id')))
            ->when($request->filled('q'), function ($query) use ($request): void {
                $q = '%'.$request->string('q').'%';
                $query->where(fn ($builder) => $builder->where('action', 'like', $q)->orWhere('description', 'like', $q));
            })
            ->latest('id')
            ->paginate(min(max($request->integer('per_page', 30), 1), 100));

        return ApiResponse::paginated($logs, AuditLogResource::class);
    }
}
