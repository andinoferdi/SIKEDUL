import { format, parseISO } from 'date-fns';
import { formatInTimeZone, toZonedTime } from 'date-fns-tz';

/**
 * Convert a date from a specific timezone to UTC
 */
export function toUTC(date: Date | string, timezone: string): string {
    const dateObj = typeof date === 'string' ? parseISO(date) : date;
    // In date-fns-tz v3, we interpret the date as being in the given timezone
    // and convert to UTC
    const utcDate = toZonedTime(dateObj, 'UTC');
    return utcDate.toISOString();
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
    const [datePart, timePart] = datetimeLocal.split('T');
    const [year, month, day] = datePart.split('-').map(Number);
    const [hour, minute] = timePart.split(':').map(Number);
    const date = new Date(year, month - 1, day, hour, minute);
    return format(date, "yyyy-MM-dd'T'HH:mm:ss");
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
