<?php

namespace App\Console\Commands;

use App\Jobs\SendReminderEmail;
use App\Models\Reminder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessDueReminders extends Command
{
    protected $signature = 'reminders:process';

    protected $description = 'Process due reminders and dispatch email jobs';

    public function handle(): int
    {
        $this->info('Processing due reminders...');

        $dueReminders = Reminder::query()
            ->due()
            ->notTooOverdue()
            ->with(['event' => function ($query) {
                $query->where('status', '!=', 'canceled');
            }])
            ->get();

        $dispatched = 0;
        $skipped = 0;

        foreach ($dueReminders as $reminder) {
            if (! $reminder->event || $reminder->event->status === 'canceled') {
                $reminder->cancel();
                $skipped++;

                continue;
            }

            SendReminderEmail::dispatch($reminder);
            $dispatched++;
        }

        $this->info("Dispatched {$dispatched} reminder jobs, skipped {$skipped}");
        Log::info("ProcessDueReminders: Dispatched {$dispatched} jobs, skipped {$skipped}");

        return Command::SUCCESS;
    }
}
