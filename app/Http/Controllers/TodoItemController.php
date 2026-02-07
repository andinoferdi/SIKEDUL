<?php

namespace App\Http\Controllers;

use App\Http\Requests\TodoItemRequest;
use App\Http\Resources\TodoItemResource;
use App\Models\TodoItem;
use App\Models\TodoList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TodoItemController extends Controller
{
    public function store(TodoItemRequest $request, TodoList $list): JsonResponse
    {
        if ($list->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $maxOrder = $list->items()->max('order_index') ?? 0;

        $item = TodoItem::create([
            'todo_list_id' => $list->id,
            'title' => $request->input('title'),
            'is_done' => (bool) $request->input('is_done', false),
            'due_date' => $request->input('due_date'),
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'order_index' => $maxOrder + 1,
            'completed_at_utc' => $request->input('is_done') ? now()->utc() : null,
        ]);

        return response()->json([
            'message' => 'Todo item created successfully.',
            'item' => new TodoItemResource($item),
        ], 201);
    }

    public function update(TodoItemRequest $request, TodoItem $item): JsonResponse
    {
        $item->load('todoList');

        if (! $item->todoList || $item->todoList->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validated();
        $updateData = [];

        if (array_key_exists('title', $validated)) {
            $updateData['title'] = $validated['title'];
        }

        if (array_key_exists('is_done', $validated)) {
            $isDone = (bool) $validated['is_done'];
            $updateData['is_done'] = $isDone;
            $updateData['completed_at_utc'] = $isDone ? now()->utc() : null;
        }

        $hasDue = array_key_exists('due_date', $validated);
        $hasRange = array_key_exists('start_date', $validated) || array_key_exists('end_date', $validated);

        if ($hasDue || $hasRange) {
            $updateData['due_date'] = $validated['due_date'] ?? null;
            $updateData['start_date'] = $validated['start_date'] ?? null;
            $updateData['end_date'] = $validated['end_date'] ?? null;

            if ($updateData['due_date']) {
                $updateData['start_date'] = null;
                $updateData['end_date'] = null;
            } else {
                $updateData['due_date'] = null;
            }
        }

        if (! empty($updateData)) {
            $item->update($updateData);
        }

        return response()->json([
            'message' => 'Todo item updated successfully.',
            'item' => new TodoItemResource($item),
        ]);
    }

    public function toggle(Request $request, TodoItem $item): JsonResponse
    {
        $item->load('todoList');

        if (! $item->todoList || $item->todoList->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $isDone = (bool) $request->input('is_done', ! $item->is_done);

        $item->update([
            'is_done' => $isDone,
            'completed_at_utc' => $isDone ? now()->utc() : null,
        ]);

        return response()->json([
            'message' => 'Todo item status updated.',
            'item' => new TodoItemResource($item),
        ]);
    }

    public function destroy(Request $request, TodoItem $item): JsonResponse
    {
        $item->load('todoList');

        if (! $item->todoList || $item->todoList->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $item->delete();

        return response()->json([
            'message' => 'Todo item deleted successfully.',
        ]);
    }

    public function reorder(Request $request, TodoList $list): JsonResponse
    {
        if ($list->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'ordered_ids' => ['required', 'array', 'min:1'],
            'ordered_ids.*' => ['integer'],
        ]);

        $orderedIds = $validated['ordered_ids'];
        $listItemIds = $list->items()->pluck('id')->all();

        if (array_diff($orderedIds, $listItemIds)) {
            return response()->json(['message' => 'Invalid item list.'], 422);
        }

        foreach ($orderedIds as $index => $id) {
            TodoItem::where('id', $id)->update(['order_index' => $index + 1]);
        }

        return response()->json([
            'message' => 'Todo items reordered successfully.',
        ]);
    }
}
