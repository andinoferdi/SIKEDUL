<?php

namespace App\Jobs;

use App\Models\Reminder;
use App\Notifications\EventReminderNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendReminderEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public bool $deleteWhenMissingModels = true;

    public int $tries = 1;

    public function __construct(
        protected Reminder $reminder
    ) {}

    public function handle(): void
    {
        $this->reminder->refresh();

        if ($this->reminder->status !== Reminder::STATUS_PENDING) {
            Log::info('SendReminderEmail: Skipping reminder '.$this->reminder->id.' - status is '.$this->reminder->status);

            return;
        }

        if ($this->reminder->isTooOverdue()) {
            Log::info('SendReminderEmail: Skipping reminder '.$this->reminder->id.' - too overdue');
            $this->reminder->update([
                'status' => Reminder::STATUS_FAILED,
                'last_error' => 'Reminder was more than 30 minutes overdue',
            ]);

            return;
        }

        $this->reminder->load(['user', 'event']);

        if (! $this->reminder->user || ! $this->reminder->event) {
            Log::warning('SendReminderEmail: User or event missing for reminder '.$this->reminder->id);
            $this->reminder->cancel();

            return;
        }

        if ($this->reminder->event->status === 'canceled') {
            Log::info('SendReminderEmail: Event is canceled for reminder '.$this->reminder->id);
            $this->reminder->cancel();

            return;
        }

        try {
            $this->reminder->user->notify(new EventReminderNotification($this->reminder->event));

            $this->reminder->markAsSent();

            Log::info('SendReminderEmail: Successfully sent reminder '.$this->reminder->id);
        } catch (\Exception $e) {
            Log::error('SendReminderEmail: Failed to send reminder '.$this->reminder->id.': '.$e->getMessage());

            $this->reminder->markAsFailed($e->getMessage());

            if ($this->reminder->attempt_count < Reminder::MAX_ATTEMPTS) {
                $backoffSeconds = $this->reminder->getBackoffSeconds();

                SendReminderEmail::dispatch($this->reminder)
                    ->delay(now()->addSeconds($backoffSeconds));

                Log::info('SendReminderEmail: Scheduled retry for reminder '.$this->reminder->id.' in '.$backoffSeconds.' seconds');
            }
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SendReminderEmail job failed permanently for reminder '.$this->reminder->id.': '.$exception->getMessage());

        $this->reminder->update([
            'status' => Reminder::STATUS_FAILED,
            'last_error' => 'Job failed: '.$exception->getMessage(),
        ]);
    }
}
