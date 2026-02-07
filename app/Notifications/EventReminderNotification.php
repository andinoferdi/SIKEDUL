<?php

namespace App\Notifications;

use App\Helpers\DateTimeHelper;
use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EventReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected Event $event
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $userTimezone = $notifiable->timezone ?? 'Asia/Jakarta';
        $startTime = DateTimeHelper::convertFromUTC($this->event->start_at_utc, $userTimezone);

        $calendarUrl = route('calendar.index');

        return (new MailMessage)
            ->subject('Reminder: '.$this->event->title.' - SIKEDUL')
            ->greeting('Hello '.$notifiable->name.'!')
            ->line('This is a reminder for your upcoming event:')
            ->line('**'.$this->event->title.'**')
            ->line('**Starts at:** '.$startTime->format('l, F j, Y \a\t g:i A').' ('.$userTimezone.')')
            ->action('View Calendar', $calendarUrl)
            ->line('Stay organized with SIKEDUL!')
            ->salutation('Best regards, SIKEDUL Team');
    }

    public function toArray($notifiable): array
    {
        return [
            'event_id' => $this->event->id,
            'event_title' => $this->event->title,
            'start_at_utc' => $this->event->start_at_utc,
        ];
    }
}
