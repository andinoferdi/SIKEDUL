<?php

namespace App\Http\Controllers;

use App\Http\Requests\EventCategoryRequest;
use App\Http\Resources\EventCategoryResource;
use App\Models\EventCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventCategoryController extends Controller
{
    /**
     * Display a listing of the user's event categories.
     */
    public function index(Request $request): JsonResponse
    {
        $categories = EventCategory::query()
            ->forUser($request->user()->id)
            ->withCount('events')
            ->orderBy('name')
            ->get();

        return response()->json([
            'categories' => EventCategoryResource::collection($categories),
        ]);
    }

    /**
     * Store a newly created event category.
     */
    public function store(EventCategoryRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $category = EventCategory::create([
            'user_id' => $request->user()->id,
            'name' => $validated['name'],
            'color' => $validated['color'] ?? null,
        ]);

        return response()->json([
            'message' => 'Category created successfully.',
            'category' => new EventCategoryResource($category),
        ], 201);
    }

    /**
     * Update the specified event category.
     */
    public function update(EventCategoryRequest $request, EventCategory $category): JsonResponse
    {
        // Verify ownership
        if ($category->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 403);
        }

        $validated = $request->validated();

        $category->update([
            'name' => $validated['name'],
            'color' => $validated['color'] ?? $category->color,
        ]);

        return response()->json([
            'message' => 'Category updated successfully.',
            'category' => new EventCategoryResource($category),
        ]);
    }

    /**
     * Remove the specified event category.
     */
    public function destroy(Request $request, EventCategory $category): JsonResponse
    {
        // Verify ownership
        if ($category->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 403);
        }

        // When category is deleted, events will have category_id set to null due to onDelete('set null')
        $category->delete();

        return response()->json([
            'message' => 'Category deleted successfully.',
        ]);
    }
}
