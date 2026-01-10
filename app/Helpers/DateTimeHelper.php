<?php

namespace App\Helpers;

use Carbon\Carbon;

class DateTimeHelper
{
    /**
     * Validate and normalize timezone
     *
     * @param string $timezone The timezone to validate
     * @return string Valid timezone or default 'Asia/Jakarta'
     */
    public static function validateTimezone(string $timezone): string
    {
        try {
            new \DateTimeZone($timezone);
            return $timezone;
        } catch (\Exception $e) {
            return 'Asia/Jakarta'; // Default fallback
        }
    }

    /**
     * Convert a datetime from a specific timezone to UTC
     *
     * @param string $datetime The datetime string to convert
     * @param string $timezone The source timezone (e.g., 'Asia/Jakarta')
     * @return Carbon The datetime in UTC
     */
    public static function convertToUTC(string $datetime, string $timezone): Carbon
    {
        $validTimezone = self::validateTimezone($timezone);
        return Carbon::parse($datetime, $validTimezone)->setTimezone('UTC');
    }

    /**
     * Convert a UTC datetime to a specific timezone
     *
     * @param Carbon|string $datetime The UTC datetime to convert
     * @param string $timezone The target timezone (e.g., 'Asia/Jakarta')
     * @return Carbon The datetime in the target timezone
     */
    public static function convertFromUTC($datetime, string $timezone): Carbon
    {
        $validTimezone = self::validateTimezone($timezone);
        $carbon = $datetime instanceof Carbon ? $datetime : Carbon::parse($datetime, 'UTC');
        return $carbon->setTimezone($validTimezone);
    }

    /**
     * Check if two datetime ranges overlap
     *
     * @param Carbon $start1 Start of first range
     * @param Carbon $end1 End of first range
     * @param Carbon $start2 Start of second range
     * @param Carbon $end2 End of second range
     * @return bool True if the ranges overlap, false otherwise
     */
    public static function rangesOverlap(Carbon $start1, Carbon $end1, Carbon $start2, Carbon $end2): bool
    {
        return $start1->lt($end2) && $end1->gt($start2);
    }
}
