<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTagRequest;
use App\Http\Resources\TagResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class TagController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Tag::class, 'tag');
    }

    public function index(): JsonResponse
    {
        return ApiResponse::success(TagResource::collection(Tag::query()->orderBy('name')->get()));
    }

    public function store(StoreTagRequest $request): JsonResponse
    {
        $tag = Tag::create([
            'company_id' => $request->user()->company_id,
            'name' => $request->validated('name'),
            'slug' => Str::slug($request->validated('name')) ?: Str::random(8),
            'color' => $request->validated('color') ?? '#2563EB',
        ]);

        return ApiResponse::created(new TagResource($tag));
    }

    public function update(StoreTagRequest $request, Tag $tag): JsonResponse
    {
        $tag->update([
            'name' => $request->validated('name'),
            'slug' => Str::slug($request->validated('name')) ?: $tag->slug,
            'color' => $request->validated('color') ?? $tag->color,
        ]);

        return ApiResponse::resource(new TagResource($tag));
    }

    public function destroy(Tag $tag): JsonResponse
    {
        $tag->delete();

        return ApiResponse::success(null, __('Tag deleted.'));
    }
}
