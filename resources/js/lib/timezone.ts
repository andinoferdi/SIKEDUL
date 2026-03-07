import { parseISO } from 'date-fns';
import { formatInTimeZone, fromZonedTime, toZonedTime } from 'date-fns-tz';

/**
 * Convert a date from a specific timezone to UTC
 */
export function toUTC(date: Date | string, timezone: string): string {
    const dateObj = typeof date === 'string' ? date : date;
    return fromZonedTime(dateObj, timezone).toISOString();
}

/**
 * Convert a UTC date to a specific timezone
 */
export function fromUTC(utcDate: Date | string, timezone: string): Date {
    const dateObj =
        typeof utcDate === 'string' ? parseISO(utcDate) : utcDate;
    return toZonedTime(dateObj, timezone);
}

/**
 * Format a date for datetime-local input (yyyy-MM-ddTHH:mm)
 */
export function formatForInput(
    date: Date | string,
    timezone: string,
): string {
    return formatInTimeZone(date, timezone, "yyyy-MM-dd'T'HH:mm");
}

/**
 * Parse a datetime-local input value and return formatted string
 */
export function parseFromInput(
    datetimeLocal: string,
    timezone: string,
): string {
    const zonedUtcDate = fromZonedTime(datetimeLocal, timezone);
    return formatInTimeZone(zonedUtcDate, timezone, "yyyy-MM-dd'T'HH:mm:ss");
}

/**
 * Format a date for display
 */
export function formatForDisplay(
    date: Date | string,
    timezone: string,
    formatStr: string = 'PPpp',
): string {
    return formatInTimeZone(date, timezone, formatStr);
}

/**
 * Format a date as YYYY-MM-DD in the given timezone.
 */
export function formatDateOnly(
    date: Date | string,
    timezone: string,
): string {
    return formatInTimeZone(date, timezone, 'yyyy-MM-dd');
}
