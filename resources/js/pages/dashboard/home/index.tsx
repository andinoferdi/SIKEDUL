import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { formatDateOnly, formatForInput, parseFromInput } from '@/lib/timezone';
import AppLayout from '@/pages/dashboard/layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem, type SharedData } from '@/types';
import type { TodoItem, TodoList } from '@/types/todo';
import { Head, Link, usePage } from '@inertiajs/react';
import axios from 'axios';
import { Calendar, CheckCircle2, ListTodo, PlusCircle } from 'lucide-react';
import { FormEvent, useEffect, useMemo, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

interface EventEntry {
    id: number;
    type: 'event';
    title: string;
    start: string;
    end: string;
    status: 'planned' | 'done' | 'canceled';
}

export default function Dashboard() {
    const { auth } = usePage<SharedData>().props;
    const timezone = auth.user.timezone ?? 'Asia/Jakarta';
    const today = formatDateOnly(new Date(), timezone);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [actionError, setActionError] = useState<string | null>(null);
    const [submitting, setSubmitting] = useState(false);
    const [events, setEvents] = useState<EventEntry[]>([]);
    const [todoLists, setTodoLists] = useState<TodoList[]>([]);
    const [eventForm, setEventForm] = useState({
        title: '',
        start_at: formatForInput(new Date(), timezone),
        end_at: formatForInput(new Date(Date.now() + 60 * 60 * 1000), timezone),
    });
    const [quickTodoTitle, setQuickTodoTitle] = useState('');
    const [selectedTodoListId, setSelectedTodoListId] = useState<number | null>(null);

    const loadDashboardData = async () => {
        setLoading(true);
        setError(null);
        try {
            const rangeEnd = formatDateOnly(
                new Date(Date.now() + 30 * 24 * 60 * 60 * 1000),
                timezone,
            );
            const [eventRes, todoRes] = await Promise.all([
                axios.get('/events/list', {
                    params: {
                        start: today,
                        end: rangeEnd,
                        include_todos: 0,
                    },
                }),
                axios.get('/todo-lists'),
            ]);

            const eventEntries = (eventRes.data.entries as Array<EventEntry | { type: string }>)
                .filter((entry): entry is EventEntry => entry.type === 'event')
                .sort((a, b) => a.start.localeCompare(b.start));

            setEvents(eventEntries);
            setTodoLists(todoRes.data.lists as TodoList[]);
        } catch {
            setError('Gagal memuat data dashboard.');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        void loadDashboardData();
    }, []);

    const todayEvents = useMemo(
        () => events.filter((event) => event.start.slice(0, 10) === today),
        [events, today],
    );

    const pendingTodos = useMemo(
        () =>
            todoLists.flatMap((list) =>
                list.items
                    .filter((item) => !item.is_done)
                    .map((item) => ({ ...item, listTitle: list.title })),
            ),
        [todoLists],
    );

    const nearestEvents = useMemo(() => events.slice(0, 5), [events]);
    const topPendingTodos = useMemo(() => pendingTodos.slice(0, 7), [pendingTodos]);

    const onQuickAddEvent = async (e: FormEvent) => {
        e.preventDefault();
        setSubmitting(true);
        setActionError(null);
        try {
            await axios.post('/events', {
                title: eventForm.title,
                start_at: parseFromInput(eventForm.start_at, timezone),
                end_at: parseFromInput(eventForm.end_at, timezone),
                reminder_minutes: 15,
            });
            setEventForm((prev) => ({ ...prev, title: '' }));
            await loadDashboardData();
        } catch {
            setActionError('Quick add event gagal. Cek input waktu atau konflik jadwal.');
        } finally {
            setSubmitting(false);
        }
    };

    const onQuickAddTodo = async (e: FormEvent) => {
        e.preventDefault();
        setSubmitting(true);
        setActionError(null);
        try {
            let listId = selectedTodoListId;
            if (!listId) {
                const created = await axios.post('/todo-lists', {
                    title: 'Quick Tasks',
                    start_date: null,
                    end_date: null,
                });
                listId = created.data.list.id as number;
            }

            await axios.post(`/todo-items/${listId}`, {
                title: quickTodoTitle,
                due_date: today,
            });

            setQuickTodoTitle('');
            await loadDashboardData();
        } catch {
            setActionError('Quick add todo gagal.');
        } finally {
            setSubmitting(false);
        }
    };

    const getTodoDateText = (item: TodoItem & { listTitle: string }) => {
        if (item.due_date) {
            return `Due ${item.due_date}`;
        }
        return `Range ${item.start_date} - ${item.end_date}`;
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />

            <div className="space-y-6 p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Dashboard</h1>
                        <p className="text-muted-foreground mt-1">
                            Summary of your activities today 
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button asChild variant="outline">
                            <Link href="/calendar">Buka Kalender</Link>
                        </Button>
                        <Button asChild variant="outline">
                            <Link href="/todo">Buka Todo</Link>
                        </Button>
                    </div>
                </div>

                {(error || actionError) && (
                    <div className="rounded-md border border-destructive bg-destructive/10 p-3 text-sm text-destructive">
                        {error ?? actionError}
                    </div>
                )}

                <div className="grid gap-4 md:grid-cols-3">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Hari Ini</CardDescription>
                            <CardTitle className="flex items-center gap-2 text-2xl">
                                <Calendar className="h-5 w-5" />
                                {todayEvents.length} Event
                            </CardTitle>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Todo Belum Selesai</CardDescription>
                            <CardTitle className="flex items-center gap-2 text-2xl">
                                <ListTodo className="h-5 w-5" />
                                {pendingTodos.length} Item
                            </CardTitle>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>List Aktif</CardDescription>
                            <CardTitle className="flex items-center gap-2 text-2xl">
                                <CheckCircle2 className="h-5 w-5" />
                                {todoLists.length} List
                            </CardTitle>
                        </CardHeader>
                    </Card>
                </div>

                <div className="grid gap-6 xl:grid-cols-[1.2fr_1fr]">
                    <Card>
                        <CardHeader>
                            <CardTitle>Event Terdekat</CardTitle>
                            <CardDescription>5 event terdepan dari jadwal Anda.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {loading && <p className="text-muted-foreground text-sm">Memuat event...</p>}
                            {!loading && nearestEvents.length === 0 && (
                                <p className="text-muted-foreground text-sm">Belum ada event.</p>
                            )}
                            {!loading &&
                                nearestEvents.map((event) => (
                                    <div key={event.id} className="flex items-center justify-between rounded-lg border p-3">
                                        <div>
                                            <p className="font-medium">{event.title}</p>
                                            <p className="text-muted-foreground text-xs">
                                                {event.start.slice(0, 10)} {event.start.slice(11, 16)} - {event.end.slice(11, 16)}
                                            </p>
                                        </div>
                                        <Badge
                                            variant={
                                                event.status === 'done'
                                                    ? 'success'
                                                    : event.status === 'canceled'
                                                      ? 'destructive'
                                                      : 'info'
                                            }
                                        >
                                            {event.status}
                                        </Badge>
                                    </div>
                                ))}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Todo Belum Selesai</CardTitle>
                            <CardDescription>Prioritas checklist yang masih pending.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {loading && <p className="text-muted-foreground text-sm">Memuat todo...</p>}
                            {!loading && topPendingTodos.length === 0 && (
                                <p className="text-muted-foreground text-sm">Semua todo sudah selesai.</p>
                            )}
                            {!loading &&
                                topPendingTodos.map((item) => (
                                    <div key={item.id} className="rounded-lg border p-3">
                                        <p className="font-medium">{item.title}</p>
                                        <p className="text-muted-foreground text-xs">{getTodoDateText(item)}</p>
                                        <p className="text-muted-foreground text-xs">List: {item.listTitle}</p>
                                    </div>
                                ))}
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <PlusCircle className="h-4 w-4" />
                                Quick Add Event
                            </CardTitle>
                            <CardDescription>Tambah event cepat dari dashboard.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={onQuickAddEvent} className="space-y-3">
                                <Input
                                    value={eventForm.title}
                                    onChange={(e) =>
                                        setEventForm((prev) => ({ ...prev, title: e.target.value }))
                                    }
                                    placeholder="Judul event"
                                    required
                                />
                                <div className="grid gap-3 md:grid-cols-2">
                                    <Input
                                        type="datetime-local"
                                        value={eventForm.start_at}
                                        onChange={(e) =>
                                            setEventForm((prev) => ({ ...prev, start_at: e.target.value }))
                                        }
                                        required
                                    />
                                    <Input
                                        type="datetime-local"
                                        value={eventForm.end_at}
                                        onChange={(e) =>
                                            setEventForm((prev) => ({ ...prev, end_at: e.target.value }))
                                        }
                                        required
                                    />
                                </div>
                                <Button type="submit" disabled={submitting}>
                                    Tambah Event
                                </Button>
                            </form>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <PlusCircle className="h-4 w-4" />
                                Quick Add Todo
                            </CardTitle>
                            <CardDescription>Tambah item todo cepat dengan due hari ini.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={onQuickAddTodo} className="space-y-3">
                                <Input
                                    value={quickTodoTitle}
                                    onChange={(e) => setQuickTodoTitle(e.target.value)}
                                    placeholder="Judul todo"
                                    required
                                />
                                <select
                                    className="border-input bg-background w-full rounded-md border px-3 py-2 text-sm"
                                    value={selectedTodoListId ?? ''}
                                    onChange={(e) =>
                                        setSelectedTodoListId(
                                            e.target.value ? Number(e.target.value) : null,
                                        )
                                    }
                                >
                                    <option value="">Auto: Quick Tasks</option>
                                    {todoLists.map((list) => (
                                        <option key={list.id} value={list.id}>
                                            {list.title}
                                        </option>
                                    ))}
                                </select>
                                <Button type="submit" disabled={submitting}>
                                    Tambah Todo
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
