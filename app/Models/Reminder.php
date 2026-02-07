<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reminder extends Model
{
    protected $fillable = [
        'user_id',
        'event_id',
        'send_at_utc',
        'channel',
        'status',
        'attempt_count',
        'last_error',
        'sent_at_utc',
    ];

    protected function casts(): array
    {
        return [
            'send_at_utc' => 'datetime',
            'sent_at_utc' => 'datetime',
            'attempt_count' => 'integer',
        ];
    }

    const STATUS_PENDING = 'pending';
    const STATUS_SENT = 'sent';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELED = 'canceled';

    const CHANNEL_EMAIL = 'email';

    const MAX_ATTEMPTS = 5;

    const BACKOFF_SECONDS = [60, 300, 900, 3600];

    const MAX_OVERDUE_SECONDS = 1800;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function scopeDue($query)
    {
        return $query->where('status', self::STATUS_PENDING)
            ->where('send_at_utc', '<=', now());
    }

    public function scopeNotTooOverdue($query)
    {
        return $query->where('send_at_utc', '>=', now()->subSeconds(self::MAX_OVERDUE_SECONDS));
    }

    public function isTooOverdue(): bool
    {
        return $this->send_at_utc->lt(now()->subSeconds(self::MAX_OVERDUE_SECONDS));
    }

    public function getBackoffSeconds(): int
    {
        $index = min($this->attempt_count, count(self::BACKOFF_SECONDS) - 1);

        return self::BACKOFF_SECONDS[$index];
    }

    public function markAsSent(): void
    {
        $this->update([
            'status' => self::STATUS_SENT,
            'sent_at_utc' => now(),
        ]);
    }

    public function markAsFailed(string $error): void
    {
        $this->increment('attempt_count');
        $this->update([
            'last_error' => $error,
            'status' => $this->attempt_count >= self::MAX_ATTEMPTS
                ? self::STATUS_FAILED
                : self::STATUS_PENDING,
        ]);
    }

    public function cancel(): void
    {
        $this->update(['status' => self::STATUS_CANCELED]);
    }
}
