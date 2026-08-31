<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Support\Permissions;
use Illuminate\Http\JsonResponse;

class PermissionController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return ApiResponse::success([
            'all' => Permissions::all(),
            'employee_defaults' => Permissions::employeeDefaults(),
        ]);
    }
}
