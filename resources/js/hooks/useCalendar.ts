import type {
    CategoryFormData,
    Event,
    EventCategory,
    EventFormData,
} from '@/types/calendar';
import axios from 'axios';
import { useCallback, useState } from 'react';

interface DateRange {
    start: string;
    end: string;
}

export function useCalendar(timezone: string) {
    const [events, setEvents] = useState<Event[]>([]);
    const [categories, setCategories] = useState<EventCategory[]>([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const fetchEvents = useCallback(
        async (dateRange: DateRange) => {
            setLoading(true);
            setError(null);
            try {
                const response = await axios.get('/events/list', {
                    params: {
                        start: dateRange.start,
                        end: dateRange.end,
                    },
                });
                setEvents(response.data.events);
            } catch (err: unknown) {
                const errorMessage =
                    err instanceof Error
                        ? err.message
                        : 'Failed to fetch events';
                setError(errorMessage);
                console.error('Error fetching events:', err);
            } finally {
                setLoading(false);
            }
        },
        [timezone],
    );

    const fetchCategories = useCallback(async () => {
        setLoading(true);
        setError(null);
        try {
            const response = await axios.get('/event-categories');
            setCategories(response.data.categories);
        } catch (err: unknown) {
            const errorMessage =
                err instanceof Error ? err.message : 'Failed to fetch categories';
            setError(errorMessage);
            console.error('Error fetching categories:', err);
        } finally {
            setLoading(false);
        }
    }, []);

    const createEvent = useCallback(
        async (eventData: EventFormData) => {
            setLoading(true);
            setError(null);
            try {
                const response = await axios.post('/events', eventData);
                const newEvent = response.data.event;
                setEvents((prev) => [...prev, newEvent]);
                return newEvent;
            } catch (err: unknown) {
                const errorMessage =
                    err instanceof Error ? err.message : 'Failed to create event';
                setError(errorMessage);
                console.error('Error creating event:', err);
                throw err;
            } finally {
                setLoading(false);
            }
        },
        [timezone],
    );

    const updateEvent = useCallback(
        async (eventId: number, eventData: Partial<EventFormData>) => {
            setLoading(true);
            setError(null);
            try {
                const response = await axios.patch(
                    `/events/${eventId}`,
                    eventData,
                );
                const updatedEvent = response.data.event;
                setEvents((prev) =>
                    prev.map((e) => (e.id === eventId ? updatedEvent : e)),
                );
                return updatedEvent;
            } catch (err: unknown) {
                const errorMessage =
                    err instanceof Error ? err.message : 'Failed to update event';
                setError(errorMessage);
                console.error('Error updating event:', err);
                throw err;
            } finally {
                setLoading(false);
            }
        },
        [timezone],
    );

    const deleteEvent = useCallback(async (eventId: number) => {
        setLoading(true);
        setError(null);
        try {
            await axios.delete(`/events/${eventId}`);
            setEvents((prev) => prev.filter((e) => e.id !== eventId));
        } catch (err: unknown) {
            const errorMessage =
                err instanceof Error ? err.message : 'Failed to delete event';
            setError(errorMessage);
            console.error('Error deleting event:', err);
            throw err;
        } finally {
            setLoading(false);
        }
    }, []);

    const createCategory = useCallback(async (categoryData: CategoryFormData) => {
        setLoading(true);
        setError(null);
        try {
            const response = await axios.post('/event-categories', categoryData);
            const newCategory = response.data.category;
            setCategories((prev) => [...prev, newCategory]);
            return newCategory;
        } catch (err: unknown) {
            const errorMessage =
                err instanceof Error
                    ? err.message
                    : 'Failed to create category';
            setError(errorMessage);
            console.error('Error creating category:', err);
            throw err;
        } finally {
            setLoading(false);
        }
    }, []);

    const updateCategory = useCallback(
        async (categoryId: number, categoryData: Partial<CategoryFormData>) => {
            setLoading(true);
            setError(null);
            try {
                const response = await axios.patch(
                    `/event-categories/${categoryId}`,
                    categoryData,
                );
                const updatedCategory = response.data.category;
                setCategories((prev) =>
                    prev.map((c) =>
                        c.id === categoryId ? updatedCategory : c,
                    ),
                );
                return updatedCategory;
            } catch (err: unknown) {
                const errorMessage =
                    err instanceof Error
                        ? err.message
                        : 'Failed to update category';
                setError(errorMessage);
                console.error('Error updating category:', err);
                throw err;
            } finally {
                setLoading(false);
            }
        },
        [],
    );

    const deleteCategory = useCallback(async (categoryId: number) => {
        setLoading(true);
        setError(null);
        try {
            await axios.delete(`/event-categories/${categoryId}`);
            setCategories((prev) => prev.filter((c) => c.id !== categoryId));
        } catch (err: unknown) {
            const errorMessage =
                err instanceof Error
                    ? err.message
                    : 'Failed to delete category';
            setError(errorMessage);
            console.error('Error deleting category:', err);
            throw err;
        } finally {
            setLoading(false);
        }
    }, []);

    return {
        events,
        categories,
        loading,
        error,
        fetchEvents,
        fetchCategories,
        createEvent,
        updateEvent,
        deleteEvent,
        createCategory,
        updateCategory,
        deleteCategory,
    };
}
