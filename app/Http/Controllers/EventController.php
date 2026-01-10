<?php

namespace App\Http\Controllers;

use App\Helpers\DateTimeHelper;
use App\Http\Requests\EventStoreRequest;
use App\Http\Requests\EventUpdateRequest;
use App\Http\Resources\EventResource;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    /**
     * Display the calendar page.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('dashboard/calendar/index', [
            'timezone' => $request->user()->timezone,
        ]);
    }

    /**
     * Get events for a specific date range (JSON API).
     */
    public function list(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after:start'],
        ]);

        // Convert the user's timezone dates to UTC for database query
        $startUtc = DateTimeHelper::convertToUTC($validated['start'], $user->timezone);
        $endUtc = DateTimeHelper::convertToUTC($validated['end'], $user->timezone);

        $events = Event::query()
            ->forUser($user->id)
            ->with('category')
            ->inDateRange($startUtc, $endUtc)
            ->orderBy('start_at_utc')
            ->get();

        return response()->json([
            'events' => EventResource::collection($events),
        ]);
    }

    /**
     * Store a newly created event.
     */
    public function store(EventStoreRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        // Convert times from user timezone to UTC
        $startUtc = DateTimeHelper::convertToUTC($validated['start_at'], $user->timezone);
        $endUtc = DateTimeHelper::convertToUTC($validated['end_at'], $user->timezone);

        $event = Event::create([
            'user_id' => $user->id,
            'category_id' => $validated['category_id'] ?? null,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'start_at_utc' => $startUtc,
            'end_at_utc' => $endUtc,
            'status' => $validated['status'] ?? 'planned',
        ]);

        $event->load('category');

        return response()->json([
            'message' => 'Event created successfully.',
            'event' => new EventResource($event),
        ], 201);
    }

    /**
     * Display the specified event.
     */
    public function show(Request $request, Event $event): JsonResponse
    {
        // Verify ownership
        if ($event->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 403);
        }

        $event->load('category');

        return response()->json([
            'event' => new EventResource($event),
        ]);
    }

    /**
     * Update the specified event.
     */
    public function update(EventUpdateRequest $request, Event $event): JsonResponse
    {
        // Verify ownership
        if ($event->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 403);
        }

        $user = $request->user();
        $validated = $request->validated();

        // Prepare data for update
        $updateData = [];

        if (isset($validated['title'])) {
            $updateData['title'] = $validated['title'];
        }

        if (isset($validated['description'])) {
            $updateData['description'] = $validated['description'];
        }

        if (isset($validated['category_id'])) {
            $updateData['category_id'] = $validated['category_id'];
        }

        if (isset($validated['status'])) {
            $updateData['status'] = $validated['status'];
        }

        // Convert times from user timezone to UTC if provided
        if (isset($validated['start_at'])) {
            $updateData['start_at_utc'] = DateTimeHelper::convertToUTC($validated['start_at'], $user->timezone);
        }

        if (isset($validated['end_at'])) {
            $updateData['end_at_utc'] = DateTimeHelper::convertToUTC($validated['end_at'], $user->timezone);
        }

        $event->update($updateData);
        $event->load('category');

        return response()->json([
            'message' => 'Event updated successfully.',
            'event' => new EventResource($event),
        ]);
    }

    /**
     * Remove the specified event.
     */
    public function destroy(Request $request, Event $event): JsonResponse
    {
        // Verify ownership
        if ($event->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 403);
        }

        $event->delete();

        return response()->json([
            'message' => 'Event deleted successfully.',
        ]);
    }
}
