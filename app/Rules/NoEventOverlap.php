<?php

namespace App\Rules;

use App\Helpers\DateTimeHelper;
use App\Models\Event;
use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class NoEventOverlap implements ValidationRule
{
    protected int $userId;
    protected string $timezone;
    protected Carbon $startUtc;
    protected Carbon $endUtc;
    protected ?int $exceptEventId;
    protected bool $ignoreConflict;

    /**
     * Create a new rule instance.
     *
     * @param int $userId The user ID to check events for
     * @param string $timezone The user's timezone
     * @param string $startDateTime The start datetime in user's timezone
     * @param string $endDateTime The end datetime in user's timezone
     * @param int|null $exceptEventId Optional event ID to exclude from the check (for updates)
     */
    public function __construct(
        int $userId,
        string $timezone,
        string $startDateTime,
        string $endDateTime,
        ?int $exceptEventId = null,
        bool $ignoreConflict = false
    ) {
        $this->userId = $userId;
        $this->timezone = $timezone;
        $this->startUtc = DateTimeHelper::convertToUTC($startDateTime, $timezone);
        $this->endUtc = DateTimeHelper::convertToUTC($endDateTime, $timezone);
        $this->exceptEventId = $exceptEventId;
        $this->ignoreConflict = $ignoreConflict;
    }

    /**
     * Run the validation rule.
     *
     * @param string $attribute
     * @param mixed $value
     * @param Closure $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $query = Event::query()
            ->forUser($this->userId)
            ->notCanceled()
            ->where('start_at_utc', '<', $this->endUtc)
            ->where('end_at_utc', '>', $this->startUtc);

        if ($this->exceptEventId) {
            $query->where('id', '!=', $this->exceptEventId);
        }

        $conflictingEvents = $query->get();

        if ($conflictingEvents->isNotEmpty() && ! $this->ignoreConflict) {
            $eventTitles = $conflictingEvents->pluck('title')->take(3)->implode(', ');
            $fail("Event time overlaps with: {$eventTitles}");
        }
    }
}
