<?php

namespace App\Models;

use App\Helpers\DateTimeHelper;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Event extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'category_id',
        'title',
        'description',
        'start_at_utc',
        'end_at_utc',
        'status',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_at_utc' => 'datetime',
            'end_at_utc' => 'datetime',
        ];
    }

    /**
     * Get the user that owns the event.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the category that the event belongs to.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(EventCategory::class, 'category_id');
    }

    /**
     * Scope a query to only include events for a specific user.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $userId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to only include events within a date range.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string|\Carbon\Carbon $start
     * @param string|\Carbon\Carbon $end
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeInDateRange($query, $start, $end)
    {
        return $query->where(function ($q) use ($start, $end) {
            $q->whereBetween('start_at_utc', [$start, $end])
                ->orWhereBetween('end_at_utc', [$start, $end])
                ->orWhere(function ($q2) use ($start, $end) {
                    $q2->where('start_at_utc', '<=', $start)
                       ->where('end_at_utc', '>=', $end);
                });
        });
    }

    /**
     * Scope a query to only include non-canceled events.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeNotCanceled($query)
    {
        return $query->where('status', '!=', 'canceled');
    }

    /**
     * Get the event start time in a specific timezone.
     *
     * @param string $timezone
     * @return Carbon
     */
    public function getStartInTimezone(string $timezone): Carbon
    {
        return DateTimeHelper::convertFromUTC($this->start_at_utc, $timezone);
    }

    /**
     * Get the event end time in a specific timezone.
     *
     * @param string $timezone
     * @return Carbon
     */
    public function getEndInTimezone(string $timezone): Carbon
    {
        return DateTimeHelper::convertFromUTC($this->end_at_utc, $timezone);
    }

    /**
     * Check if this event overlaps with a given time range.
     *
     * @param Carbon $start
     * @param Carbon $end
     * @return bool
     */
    public function overlapsWith(Carbon $start, Carbon $end): bool
    {
        return DateTimeHelper::rangesOverlap(
            $this->start_at_utc,
            $this->end_at_utc,
            $start,
            $end
        );
    }
}
