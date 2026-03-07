<?php

namespace App\Services;

use App\Helpers\DateTimeHelper;
use App\Models\ChatActionDraft;
use App\Models\Event;
use App\Models\EventCategory;
use App\Models\Reminder;
use App\Models\TodoItem;
use App\Models\TodoList;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ChatbotDraftExecutor
{
    public function execute(User $user, ChatActionDraft $draft): array
    {
        if ($draft->status !== ChatActionDraft::STATUS_NEEDS_CONFIRM) {
            return ['ok' => false, 'message' => 'Draft sudah diproses sebelumnya.'];
        }

        $rawPayload = $draft->payload_json ?? [];
        $payload = is_array($rawPayload['payload'] ?? null)
            ? $rawPayload['payload']
            : $rawPayload;
        $draft->update(['status' => ChatActionDraft::STATUS_CONFIRMED]);

        try {
            $resultMessage = DB::transaction(function () use ($user, $draft, $payload) {
                return match ($draft->action_type) {
                    'create_event' => $this->executeCreateEvent($user, $payload),
                    'update_event' => $this->executeUpdateEvent($user, $payload),
                    'delete_event' => $this->executeDeleteEvent($user, $payload),
                    'create_todo_list' => $this->executeCreateTodoList($user, $payload),
                    default => throw new \RuntimeException('Aksi belum didukung.'),
                };
            });

            $draft->update(['status' => ChatActionDraft::STATUS_EXECUTED]);

            return ['ok' => true, 'message' => $resultMessage];
        } catch (\Throwable $e) {
            $draft->update([
                'status' => ChatActionDraft::STATUS_FAILED,
                'payload_json' => array_merge($rawPayload, ['error' => $e->getMessage()]),
            ]);

            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function cancel(ChatActionDraft $draft): void
    {
        if ($draft->status === ChatActionDraft::STATUS_NEEDS_CONFIRM) {
            $draft->update(['status' => ChatActionDraft::STATUS_CANCELED]);
        }
    }

    private function executeCreateEvent(User $user, array $payload): string
    {
        $title = trim((string) ($payload['title'] ?? ''));
        $startAt = (string) ($payload['start_at'] ?? '');
        $endAt = (string) ($payload['end_at'] ?? '');
        $reminderMinutes = $payload['reminder_minutes'] ?? null;

        if ($title === '' || $startAt === '' || $endAt === '') {
            throw new \RuntimeException('Data event tidak lengkap.');
        }

        $startUtc = DateTimeHelper::convertToUTC($startAt, $user->timezone);
        $endUtc = DateTimeHelper::convertToUTC($endAt, $user->timezone);

        if (! $startUtc->lt($endUtc)) {
            throw new \RuntimeException('Waktu mulai harus sebelum waktu selesai.');
        }

        $this->ensureNoOverlap($user->id, $startUtc, $endUtc);

        $categoryId = null;
        $categoryName = trim((string) ($payload['category_name'] ?? ''));
        if ($categoryName !== '') {
            $category = EventCategory::firstOrCreate(
                ['user_id' => $user->id, 'name' => $categoryName],
                ['color' => '#6366F1']
            );
            $categoryId = $category->id;
        }

        $event = Event::create([
            'user_id' => $user->id,
            'category_id' => $categoryId,
            'title' => $title,
            'description' => null,
            'start_at_utc' => $startUtc,
            'end_at_utc' => $endUtc,
            'status' => 'planned',
            'reminder_minutes' => $reminderMinutes,
        ]);

        $this->createReminder($event);

        return "Event \"{$event->title}\" berhasil dibuat.";
    }

    private function executeUpdateEvent(User $user, array $payload): string
    {
        $targetTitle = trim((string) ($payload['target_title'] ?? ''));
        $startAt = (string) ($payload['start_at'] ?? '');
        $endAt = (string) ($payload['end_at'] ?? '');

        if ($targetTitle === '' || $startAt === '' || $endAt === '') {
            throw new \RuntimeException('Data update event tidak lengkap.');
        }

        $event = $this->findUserEventByTitle($user->id, $targetTitle);

        if (! $event) {
            throw new \RuntimeException("Event \"{$targetTitle}\" tidak ditemukan.");
        }

        $startUtc = DateTimeHelper::convertToUTC($startAt, $user->timezone);
        $endUtc = DateTimeHelper::convertToUTC($endAt, $user->timezone);

        if (! $startUtc->lt($endUtc)) {
            throw new \RuntimeException('Waktu mulai harus sebelum waktu selesai.');
        }

        $this->ensureNoOverlap($user->id, $startUtc, $endUtc, $event->id);

        $event->update([
            'start_at_utc' => $startUtc,
            'end_at_utc' => $endUtc,
        ]);

        $event->pendingReminders()->update(['status' => Reminder::STATUS_CANCELED]);
        $this->createReminder($event);

        return "Event \"{$event->title}\" berhasil diubah.";
    }

    private function executeDeleteEvent(User $user, array $payload): string
    {
        $targetTitle = trim((string) ($payload['target_title'] ?? ''));
        if ($targetTitle === '') {
            throw new \RuntimeException('Judul event yang dihapus tidak ada.');
        }

        $event = $this->findUserEventByTitle($user->id, $targetTitle);

        if (! $event) {
            throw new \RuntimeException("Event \"{$targetTitle}\" tidak ditemukan.");
        }

        $title = $event->title;
        $event->delete();

        return "Event \"{$title}\" berhasil dihapus.";
    }

    private function executeCreateTodoList(User $user, array $payload): string
    {
        $title = trim((string) ($payload['title'] ?? ''));
        $items = $payload['items'] ?? [];

        if ($title === '' || ! is_array($items) || count($items) === 0) {
            throw new \RuntimeException('Data todo tidak lengkap.');
        }

        $list = TodoList::create([
            'user_id' => $user->id,
            'title' => $title,
            'start_date' => null,
            'end_date' => null,
        ]);

        foreach (array_values($items) as $index => $itemTitle) {
            $cleanTitle = trim((string) $itemTitle);
            if ($cleanTitle === '') {
                continue;
            }

            TodoItem::create([
                'todo_list_id' => $list->id,
                'title' => $cleanTitle,
                'is_done' => false,
                'due_date' => null,
                'start_date' => Carbon::today($user->timezone)->toDateString(),
                'end_date' => Carbon::today($user->timezone)->toDateString(),
                'order_index' => $index + 1,
                'completed_at_utc' => null,
            ]);
        }

        return "Todo list \"{$list->title}\" berhasil dibuat.";
    }

    private function ensureNoOverlap(int $userId, Carbon $newStartUtc, Carbon $newEndUtc, ?int $exceptEventId = null): void
    {
        $query = Event::query()
            ->forUser($userId)
            ->notCanceled()
            ->where('start_at_utc', '<', $newEndUtc)
            ->where('end_at_utc', '>', $newStartUtc);

        if ($exceptEventId) {
            $query->where('id', '!=', $exceptEventId);
        }

        if ($query->exists()) {
            throw new \RuntimeException('Jadwal bentrok dengan event lain milik Anda.');
        }
    }

    private function findUserEventByTitle(int $userId, string $targetTitle): ?Event
    {
        $trimmed = trim($targetTitle);
        if ($trimmed === '') {
            return null;
        }

        return Event::query()
            ->forUser($userId)
            ->where('title', 'like', "%{$trimmed}%")
            ->orderByDesc('id')
            ->first();
    }

    private function createReminder(Event $event): void
    {
        if ($event->reminder_minutes === null || $event->status === 'canceled') {
            return;
        }

        if ($event->start_at_utc->lt(now()->subMinutes(30))) {
            return;
        }

        Reminder::create([
            'user_id' => $event->user_id,
            'event_id' => $event->id,
            'send_at_utc' => $event->start_at_utc->copy()->subMinutes((int) $event->reminder_minutes),
            'channel' => Reminder::CHANNEL_EMAIL,
            'status' => Reminder::STATUS_PENDING,
        ]);
    }
}
