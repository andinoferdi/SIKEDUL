<?php

namespace App\Http\Controllers;

use App\Http\Requests\TodoListRequest;
use App\Http\Resources\TodoListResource;
use App\Models\TodoList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TodoListController extends Controller
{
    public function indexPage(): Response
    {
        return Inertia::render('dashboard/todo/index');
    }

    public function index(Request $request): JsonResponse
    {
        $lists = TodoList::query()
            ->forUser($request->user()->id)
            ->with(['items'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'lists' => TodoListResource::collection($lists),
        ]);
    }

    public function store(TodoListRequest $request): JsonResponse
    {
        $list = TodoList::create([
            'user_id' => $request->user()->id,
            'title' => $request->input('title'),
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
        ]);

        return response()->json([
            'message' => 'Todo list created successfully.',
            'list' => new TodoListResource($list->load('items')),
        ], 201);
    }

    public function update(TodoListRequest $request, TodoList $list): JsonResponse
    {
        if ($list->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $list->update($request->validated());

        return response()->json([
            'message' => 'Todo list updated successfully.',
            'list' => new TodoListResource($list->load('items')),
        ]);
    }

    public function destroy(Request $request, TodoList $list): JsonResponse
    {
        if ($list->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $list->delete();

        return response()->json([
            'message' => 'Todo list deleted successfully.',
        ]);
    }
}
