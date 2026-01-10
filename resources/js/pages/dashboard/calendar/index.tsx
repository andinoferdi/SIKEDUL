import CalendarView from '@/components/calendar/CalendarView';
import EventCategoryManager from '@/components/calendar/EventCategoryManager';
import EventDialog from '@/components/calendar/EventDialog';
import { Button } from '@/components/ui/button';
import { useCalendar } from '@/hooks/useCalendar';
import { parseFromInput } from '@/lib/timezone';
import AppLayout from '@/pages/dashboard/layout';
import type { BreadcrumbItem } from '@/types';
import type { Event } from '@/types/calendar';
import { Head } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useEffect, useState } from 'react';

interface CalendarPageProps {
    timezone: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Calendar', href: '/calendar' },
];

export default function CalendarPage({ timezone }: CalendarPageProps) {
    const {
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
        deleteCategory,
    } = useCalendar(timezone);

    const [eventDialogOpen, setEventDialogOpen] = useState(false);
    const [selectedEvent, setSelectedEvent] = useState<Event | null>(null);
    const [initialStart, setInitialStart] = useState<Date | undefined>();
    const [initialEnd, setInitialEnd] = useState<Date | undefined>();

    useEffect(() => {
        fetchCategories();
    }, [fetchCategories]);

    const handleDatesSet = (start: string, end: string) => {
        fetchEvents({ start, end });
    };

    const handleDateSelect = (start: Date, end: Date) => {
        setSelectedEvent(null);
        setInitialStart(start);
        setInitialEnd(end);
        setEventDialogOpen(true);
    };

    const handleEventClick = (event: Event) => {
        setSelectedEvent(event);
        setInitialStart(undefined);
        setInitialEnd(undefined);
        setEventDialogOpen(true);
    };

    const handleCreateEvent = async () => {
        setSelectedEvent(null);
        const now = new Date();
        const start = new Date(
            now.getFullYear(),
            now.getMonth(),
            now.getDate(),
            now.getHours() + 1,
            0,
            0,
        );
        const end = new Date(start.getTime() + 60 * 60 * 1000);
        setInitialStart(start);
        setInitialEnd(end);
        setEventDialogOpen(true);
    };

    const handleSaveEvent = async (data: {
        title: string;
        description?: string;
        start_at: string;
        end_at: string;
        category_id?: number;
        status?: 'planned' | 'done' | 'canceled';
    }) => {
        const formattedData = {
            ...data,
            start_at: parseFromInput(data.start_at, timezone),
            end_at: parseFromInput(data.end_at, timezone),
        };
        await createEvent(formattedData);
    };

    const handleUpdateEvent = async (
        eventId: number,
        data: Partial<{
            title: string;
            description?: string;
            start_at: string;
            end_at: string;
            category_id?: number;
            status?: 'planned' | 'done' | 'canceled';
        }>,
    ) => {
        const formattedData = { ...data };
        if (data.start_at) {
            formattedData.start_at = parseFromInput(data.start_at, timezone);
        }
        if (data.end_at) {
            formattedData.end_at = parseFromInput(data.end_at, timezone);
        }
        await updateEvent(eventId, formattedData);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Calendar" />

            <div className="space-y-6 p-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">
                            Calendar
                        </h1>
                        <p className="text-muted-foreground mt-1">
                            Manage your events and schedule
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <EventCategoryManager
                            categories={categories}
                            onCreateCategory={createCategory}
                            onDeleteCategory={deleteCategory}
                        />
                        <Button onClick={handleCreateEvent}>
                            <Plus className="mr-2 h-4 w-4" />
                            New Event
                        </Button>
                    </div>
                </div>

                {/* Error Display */}
                {error && (
                    <div className="rounded-md border border-destructive bg-destructive/10 p-4 text-destructive">
                        {error}
                    </div>
                )}

                {/* Loading State */}
                {loading && events.length === 0 && (
                    <div className="flex items-center justify-center p-12">
                        <div className="text-muted-foreground">
                            Loading calendar...
                        </div>
                    </div>
                )}

                {/* Calendar View */}
                <CalendarView
                    events={events}
                    timezone={timezone}
                    onDateSelect={handleDateSelect}
                    onEventClick={handleEventClick}
                    onDatesSet={handleDatesSet}
                />

                {/* Event Dialog */}
                <EventDialog
                    open={eventDialogOpen}
                    onOpenChange={setEventDialogOpen}
                    event={selectedEvent}
                    initialStart={initialStart}
                    initialEnd={initialEnd}
                    timezone={timezone}
                    categories={categories}
                    onSave={handleSaveEvent}
                    onUpdate={handleUpdateEvent}
                    onDelete={deleteEvent}
                />
            </div>
        </AppLayout>
    );
}
