import type { Event } from '@/types/calendar';
import FullCalendar from '@fullcalendar/react';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import interactionPlugin, { type DateClickArg } from '@fullcalendar/interaction';
import type {
    DatesSetArg,
    EventClickArg,
    EventInput,
    DateSelectArg,
    EventContentArg,
} from '@fullcalendar/core';
import { useEffect, useMemo, useRef, useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

interface CalendarViewProps {
    events: Event[];
    timezone: string;
    onDateSelect: (start: Date, end: Date) => void;
    onEventClick: (event: Event) => void;
    onDatesSet: (start: string, end: string) => void;
}

export default function CalendarView({
    events,
    timezone,
    onDateSelect,
    onEventClick,
    onDatesSet,
}: CalendarViewProps) {
    const calendarRef = useRef<FullCalendar>(null);
    const [monthPickerOpen, setMonthPickerOpen] = useState(false);
    const [selectedYear, setSelectedYear] = useState(
        new Date().getFullYear(),
    );
    const [selectedMonth, setSelectedMonth] = useState(new Date().getMonth());

    // Add click handler to calendar title for opening month picker
    useEffect(() => {
        // Wait for next tick to ensure FullCalendar has rendered
        const timer = setTimeout(() => {
            const calendarEl = calendarRef.current?.elRef?.current;
            if (!calendarEl) return;

            const titleEl = calendarEl.querySelector('.fc-toolbar-title');
            if (!titleEl) return;

            const handleClick = () => setMonthPickerOpen(true);
            titleEl.addEventListener('click', handleClick);
        }, 0);

        return () => clearTimeout(timer);
    }, []);

    const calendarEvents: EventInput[] = useMemo(
        () =>
            events.map((event) => ({
                id: event.id.toString(),
                title: event.title,
                start: event.start,
                end: event.end,
                backgroundColor: event.category?.color || '#6B7280',
                borderColor: event.category?.color || '#6B7280',
                extendedProps: {
                    event,
                },
            })),
        [events],
    );

    const handleDateClick = (arg: DateClickArg) => {
        const start = arg.date;
        const end = new Date(start.getTime() + 60 * 60 * 1000);
        onDateSelect(start, end);
    };

    const handleDateSelect = (arg: DateSelectArg) => {
        const start = arg.start;
        const end = arg.end;
        onDateSelect(start, end);
    };

    const handleEventClick = (arg: EventClickArg) => {
        const event = arg.event.extendedProps.event as Event;
        onEventClick(event);
    };

    const handleDatesSet = (arg: DatesSetArg) => {
        onDatesSet(arg.startStr, arg.endStr);
        // Add 15 days to currentStart to ensure we're in the displayed month
        // currentStart may be in the previous month (e.g., last Sunday of Dec for Jan view)
        const viewStart = arg.view.currentStart;
        const middleOfMonth = new Date(viewStart.getTime() + 15 * 24 * 60 * 60 * 1000);
        setSelectedYear(middleOfMonth.getFullYear());
        setSelectedMonth(middleOfMonth.getMonth());
    };

    const handleMonthYearChange = () => {
        const calendarApi = calendarRef.current?.getApi();
        if (calendarApi) {
            calendarApi.gotoDate(new Date(selectedYear, selectedMonth, 1));
        }
        setMonthPickerOpen(false);
    };

    const months = [
        'January',
        'February',
        'March',
        'April',
        'May',
        'June',
        'July',
        'August',
        'September',
        'October',
        'November',
        'December',
    ];

    const years = Array.from(
        { length: 10 },
        (_, i) => new Date().getFullYear() - 5 + i,
    );

    const getStatusColor = (status: string, isTimeGrid: boolean = false) => {
        switch (status) {
            case 'done':
                return 'text-green-400';
            case 'canceled':
                return 'text-red-400 line-through';
            default:
                return isTimeGrid ? 'text-white' : 'text-blue-600';
        }
    };

    const formatEventTime = (dateStr: string) => {
        const date = new Date(dateStr);
        return date.toLocaleTimeString('id-ID', {
            hour: '2-digit',
            minute: '2-digit',
            hour12: false,
        });
    };

    const renderEventContent = (eventInfo: EventContentArg) => {
        const event = eventInfo.event.extendedProps.event as Event;
        const timeText = formatEventTime(event.start);
        const isTimeGrid = eventInfo.view.type.includes('timeGrid');

        if (isTimeGrid) {
            // Week view - event height is determined by duration, fill the space
            const statusColor = getStatusColor(event.status, true);
            return (
                <div className="w-full h-full flex flex-col gap-0 px-1 py-0.5 overflow-hidden">
                    <span className={`flex items-center gap-1 text-[11px] font-medium leading-tight ${statusColor}`}>
                        {event.category?.color && (
                            <span
                                className="inline-block size-2 shrink-0 rounded-full border border-white/30"
                                style={{ backgroundColor: event.category.color }}
                            />
                        )}
                        <span className="truncate">{eventInfo.event.title}</span>
                    </span>
                    <span className={`text-[10px] ${event.status === 'canceled' ? 'text-red-400/80' : 'text-white/80'}`}>{timeText}</span>
                </div>
            );
        }

        // Month view - compact display
        return (
            <div className="w-full flex flex-col gap-0 px-1.5 py-1 overflow-hidden">
                <span className="text-[10px] text-muted-foreground">{timeText}</span>
                <span className={`flex items-center gap-1 text-[11px] font-medium whitespace-normal break-words w-full ${getStatusColor(event.status)}`}>
                    {event.category?.color && (
                        <span
                            className="inline-block size-2 shrink-0 rounded-full"
                            style={{ backgroundColor: event.category.color }}
                        />
                    )}
                    {eventInfo.event.title}
                </span>
            </div>
        );
    };

    return (
        <>
            <div className="rounded-lg border bg-card p-4">
                <FullCalendar
                    ref={calendarRef}
                    plugins={[dayGridPlugin, timeGridPlugin, interactionPlugin]}
                    initialView="dayGridMonth"
                    headerToolbar={{
                        left: '',
                        center: 'prev title next',
                        right: 'dayGridMonth,timeGridWeek',
                    }}
                    titleFormat={{ year: 'numeric', month: 'long' }}
                    dayHeaderFormat={{ weekday: 'long' }}
                    timeZone={timezone}
                    events={calendarEvents}
                    editable={false}
                    selectable={true}
                    selectMirror={true}
                    dayMaxEvents={true}
                    weekends={true}
                    dateClick={handleDateClick}
                    select={handleDateSelect}
                    eventClick={handleEventClick}
                    eventContent={renderEventContent}
                    datesSet={handleDatesSet}
                    height="auto"
                    slotMinTime="06:00:00"
                    slotMaxTime="22:00:00"
                    allDaySlot={false}
                    nowIndicator={true}
                    eventTimeFormat={{
                        hour: '2-digit',
                        minute: '2-digit',
                        hour12: false,
                    }}
                    slotLabelFormat={{
                        hour: '2-digit',
                        minute: '2-digit',
                        hour12: false,
                    }}
                />
            </div>

            <Dialog open={monthPickerOpen} onOpenChange={setMonthPickerOpen}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>Select Month and Year</DialogTitle>
                    </DialogHeader>
                    <div className="grid gap-4 py-4">
                        <div className="grid gap-2">
                            <label htmlFor="month" className="text-sm font-medium">
                                Month
                            </label>
                            <Select
                                value={selectedMonth.toString()}
                                onValueChange={(value) =>
                                    setSelectedMonth(parseInt(value))
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {months.map((month, index) => (
                                        <SelectItem
                                            key={index}
                                            value={index.toString()}
                                        >
                                            {month}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="grid gap-2">
                            <label htmlFor="year" className="text-sm font-medium">
                                Year
                            </label>
                            <Select
                                value={selectedYear.toString()}
                                onValueChange={(value) =>
                                    setSelectedYear(parseInt(value))
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {years.map((year) => (
                                        <SelectItem
                                            key={year}
                                            value={year.toString()}
                                        >
                                            {year}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    <div className="flex justify-end gap-2">
                        <Button
                            variant="outline"
                            onClick={() => setMonthPickerOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button onClick={handleMonthYearChange}>Apply</Button>
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}
