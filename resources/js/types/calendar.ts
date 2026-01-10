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
    category?: EventCategory;
    category_id?: number;
    created_at: string;
    updated_at: string;
}

export interface EventFormData {
    title: string;
    description?: string;
    start_at: string;
    end_at: string;
    category_id?: number;
    status?: 'planned' | 'done' | 'canceled';
}

export interface CategoryFormData {
    name: string;
    color?: string;
}
