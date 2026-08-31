<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\DashboardService;
use App\Support\Permissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardService $dashboard): JsonResponse
    {
        if (! $request->user()->can(Permissions::REPORTS_VIEW)) {
            return ApiResponse::error(__('Forbidden.'), 403);
        }

        return ApiResponse::success($dashboard->stats($request->user()->company));
    }
}
