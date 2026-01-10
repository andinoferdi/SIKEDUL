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
import { Badge } from '@/components/ui/badge';
import { useMemo, useRef, useState } from 'react';
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
                classNames:
                    event.status === 'canceled'
                        ? ['opacity-50', 'line-through']
                        : event.status === 'done'
                          ? ['opacity-75']
                          : [],
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
        const date = arg.view.currentStart;
        setSelectedYear(date.getFullYear());
        setSelectedMonth(date.getMonth());
    };

    const handleTitleClick = () => {
        setMonthPickerOpen(true);
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

    const getStatusVariant = (status: string) => {
        switch (status) {
            case 'done':
                return 'success';
            case 'canceled':
                return 'destructive';
            default:
                return 'info';
        }
    };

    const renderEventContent = (eventInfo: EventContentArg) => {
        const event = eventInfo.event.extendedProps.event as Event;
        return (
            <Badge
                variant={getStatusVariant(event.status)}
                className="w-full justify-start gap-1 rounded-sm px-1 py-0.5"
            >
                <span className="truncate text-[11px]">{eventInfo.timeText}</span>
                <span className="truncate font-medium text-[11px]">{eventInfo.event.title}</span>
            </Badge>
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
                        left: 'prev,next',
                        center: 'title',
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
                    customButtons={{
                        title: {
                            text: 'title',
                            click: handleTitleClick,
                        },
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
