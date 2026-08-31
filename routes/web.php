<?php

use App\Http\Controllers\ApiDocsController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'name' => config('app.name'),
        'version' => '1.0.0',
        'docs' => url('/docs'),
        'openapi' => url('/docs/openapi.yaml'),
    ]);
});

Route::get('/docs', [ApiDocsController::class, 'ui']);
Route::get('/docs/openapi.yaml', [ApiDocsController::class, 'spec']);
