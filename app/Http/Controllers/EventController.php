<?php

namespace App\Http\Controllers;

use App\Helpers\DateTimeHelper;
use App\Http\Requests\EventStoreRequest;
use App\Http\Requests\EventUpdateRequest;
use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Models\Reminder;
use App\Models\TodoItem;
use Carbon\Carbon;
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
            'end' => ['required', 'date'],
            'include_todos' => ['nullable', 'boolean'],
        ]);

        // Convert the user's timezone dates to UTC for database query
        $startUtc = DateTimeHelper::convertToUTC($validated['start'], $user->timezone);
        $endUtc = DateTimeHelper::convertToUTC($validated['end'], $user->timezone);

        if ($endUtc->lessThanOrEqualTo($startUtc)) {
            $endUtc = $startUtc->copy()->addDay();
        }

        $events = Event::query()
            ->forUser($user->id)
            ->with('category')
            ->inDateRange($startUtc, $endUtc)
            ->orderBy('start_at_utc')
            ->get();

        $entries = collect(EventResource::collection($events)->resolve())
            ->map(function (array $event) {
                $event['type'] = 'event';
                $event['allDay'] = false;
                return $event;
            });

        $includeTodos = (bool) ($validated['include_todos'] ?? false);

        if ($includeTodos) {
            $rangeStartDate = Carbon::parse($validated['start'], $user->timezone)->toDateString();
            $rangeEndDate = Carbon::parse($validated['end'], $user->timezone)->toDateString();

            $todoItems = TodoItem::query()
                ->whereHas('todoList', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })
                ->where(function ($query) use ($rangeStartDate, $rangeEndDate) {
                    $query->whereNotNull('due_date')
                        ->whereBetween('due_date', [$rangeStartDate, $rangeEndDate])
                        ->orWhere(function ($rangeQuery) use ($rangeStartDate, $rangeEndDate) {
                            $rangeQuery->whereNull('due_date')
                                ->whereNotNull('start_date')
                                ->whereNotNull('end_date')
                                ->where('start_date', '<=', $rangeEndDate)
                                ->where('end_date', '>=', $rangeStartDate);
                        });
                })
                ->orderBy('due_date')
                ->orderBy('start_date')
                ->get();

            $today = Carbon::now($user->timezone)->startOfDay();

            $todoEntries = $todoItems->map(function (TodoItem $item) use ($user, $today) {
                if ($item->due_date) {
                    $startDate = $item->due_date->toDateString();
                    $endDate = $item->due_date->copy()->addDay()->toDateString();
                    $rangeStart = $item->due_date->copy()->startOfDay();
                    $rangeEnd = $item->due_date->copy()->endOfDay();
                } else {
                    $startDate = $item->start_date->toDateString();
                    $endDate = $item->end_date->copy()->addDay()->toDateString();
                    $rangeStart = $item->start_date->copy()->startOfDay();
                    $rangeEnd = $item->end_date->copy()->endOfDay();
                }

                if ($item->is_done) {
                    $status = 'done';
                } elseif ($today->lt($rangeStart)) {
                    $status = 'upcoming';
                } elseif ($today->gt($rangeEnd)) {
                    $status = 'done';
                } else {
                    $status = 'ongoing';
                }

                return [
                    'id' => $item->id,
                    'title' => $item->title,
                    'start' => $startDate,
                    'end' => $endDate,
                    'allDay' => true,
                    'type' => 'todo',
                    'is_done' => $item->is_done,
                    'todo_list_id' => $item->todo_list_id,
                    'todo_status' => $status,
                    'due_date' => $item->due_date?->format('Y-m-d'),
                    'start_date' => $item->start_date?->format('Y-m-d'),
                    'end_date' => $item->end_date?->format('Y-m-d'),
                ];
            });

            $entries = $entries->concat($todoEntries);
        }

        return response()->json([
            'entries' => $entries->values(),
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
            'reminder_minutes' => $validated['reminder_minutes'] ?? null,
        ]);

        $this->createReminderForEvent($event);

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

        if (array_key_exists('reminder_minutes', $validated)) {
            $updateData['reminder_minutes'] = $validated['reminder_minutes'];
        }

        // Convert times from user timezone to UTC if provided
        if (isset($validated['start_at'])) {
            $updateData['start_at_utc'] = DateTimeHelper::convertToUTC($validated['start_at'], $user->timezone);
        }

        if (isset($validated['end_at'])) {
            $updateData['end_at_utc'] = DateTimeHelper::convertToUTC($validated['end_at'], $user->timezone);
        }

        $event->update($updateData);

        $this->refreshReminderForEvent($event);

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

    private function createReminderForEvent(Event $event): void
    {
        if ($event->reminder_minutes === null || $event->status === 'canceled') {
            return;
        }

        $sendAtUtc = $event->start_at_utc->copy()->subMinutes($event->reminder_minutes);

        // Jangan buat reminder jika event start sudah lewat lebih dari 30 menit
        if ($event->start_at_utc->lt(now()->subMinutes(30))) {
            return;
        }

        Reminder::create([
            'user_id' => $event->user_id,
            'event_id' => $event->id,
            'send_at_utc' => $sendAtUtc,
            'channel' => Reminder::CHANNEL_EMAIL,
            'status' => Reminder::STATUS_PENDING,
        ]);
    }

    private function refreshReminderForEvent(Event $event): void
    {
        $event->pendingReminders()->update(['status' => Reminder::STATUS_CANCELED]);

        if ($event->status !== 'canceled') {
            $this->createReminderForEvent($event);
        }
    }
}
