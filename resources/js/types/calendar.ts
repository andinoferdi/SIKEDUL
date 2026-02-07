export interface EventCategory {
    id: number;
    name: string;
    color: string;
    events_count?: number;
    created_at: string;
    updated_at: string;
}

export interface Event {
    id: number;
    title: string;
    description?: string;
    start: string; // ISO 8601 in user timezone
    end: string;
    start_at_utc: string;
    end_at_utc: string;
    status: 'planned' | 'done' | 'canceled';
    reminder_minutes?: number | null;
    category?: EventCategory;
    category_id?: number;
    created_at: string;
    updated_at: string;
}

export interface TodoCalendarEntry {
    id: number;
    title: string;
    start: string;
    end: string;
    allDay: true;
    type: 'todo';
    is_done: boolean;
    todo_list_id: number;
    todo_status?: 'upcoming' | 'ongoing' | 'done';
    due_date?: string | null;
    start_date?: string | null;
    end_date?: string | null;
}

export interface EventCalendarEntry extends Event {
    type: 'event';
    allDay: false;
}

export type CalendarEntry = EventCalendarEntry | TodoCalendarEntry;

export interface EventFormData {
    title: string;
    description?: string;
    start_at: string;
    end_at: string;
    category_id?: number;
    status?: 'planned' | 'done' | 'canceled';
    reminder_minutes?: number | null;
}

export interface CategoryFormData {
    name: string;
    color?: string;
}
