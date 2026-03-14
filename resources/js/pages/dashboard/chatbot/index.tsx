import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/pages/dashboard/layout';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import { MessageCircle, Plus, Send } from 'lucide-react';
import { FormEvent, useMemo, useState } from 'react';

interface ThreadSummary {
    id: number;
    title: string | null;
    created_at: string;
    updated_at: string;
}

interface DraftPayload {
    preview?: Record<string, unknown> | null;
    payload?: Record<string, unknown> | null;
    original_command?: string;
}

interface DraftData {
    id: number;
    action_type: string;
    status: 'needs_confirm' | 'confirmed' | 'canceled' | 'executed' | 'failed';
    payload_json: DraftPayload;
}

interface MessageData {
    id: number;
    sender: 'user' | 'assistant';
    content: string;
    created_at: string;
    draft?: DraftData | null;
}

interface ThreadDetail {
    id: number;
    title: string | null;
    created_at: string;
    updated_at: string;
    messages: MessageData[];
}

interface ChatbotPageProps {
    threads: ThreadSummary[];
    activeThread: ThreadDetail | null;
    timezone: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Chatbot', href: '/chatbot' },
];

const commandSamples = [
    'jadwalkan meeting dosen besok jam 09.00 sampai 10.00 dan ingatkan 15 menit',
    'pindahkan event rapat skripsi jadi hari sabtu jam 10.00 sampai 11.00',
    'hapus event rapat skripsi minggu depan',
    'buat daftar todo minggu ini: revisi bab 1, cari jurnal, bikin diagram',
];

export default function ChatbotPage({ threads: initialThreads, activeThread: initialThread }: ChatbotPageProps) {
    const [threads, setThreads] = useState<ThreadSummary[]>(initialThreads);
    const [activeThread, setActiveThread] = useState<ThreadDetail | null>(initialThread);
    const [input, setInput] = useState('');
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [inputError, setInputError] = useState<string | null>(null);

    const activeThreadId = activeThread?.id ?? null;

    const sortedThreads = useMemo(
        () => [...threads].sort((a, b) => b.updated_at.localeCompare(a.updated_at)),
        [threads],
    );

    const upsertThreadSummary = (thread: ThreadDetail) => {
        setThreads((prev) => {
            const summary: ThreadSummary = {
                id: thread.id,
                title: thread.title,
                created_at: thread.created_at,
                updated_at: thread.updated_at,
            };
            const exists = prev.some((t) => t.id === thread.id);
            if (!exists) {
                return [summary, ...prev];
            }
            return prev.map((t) => (t.id === thread.id ? summary : t));
        });
    };

    const createThread = async (): Promise<number | null> => {
        try {
            const response = await axios.post('/chatbot/threads', {});
            const thread = response.data.thread as ThreadSummary;
            setThreads((prev) => [thread, ...prev]);
            setActiveThread({
                ...thread,
                messages: [],
            });
            return thread.id;
        } catch {
            setError('Gagal membuat thread baru.');
            return null;
        }
    };

    const loadThread = async (threadId: number) => {
        setLoading(true);
        setError(null);
        try {
            const response = await axios.get(`/chatbot/threads/${threadId}`);
            const thread = response.data.thread as ThreadDetail;
            setActiveThread(thread);
            upsertThreadSummary(thread);
        } catch {
            setError('Gagal memuat thread.');
        } finally {
            setLoading(false);
        }
    };

    const sendMessage = async (content: string) => {
        const trimmed = content.trim();
        if (!trimmed) {
            setInputError('Pesan tidak boleh kosong.');
            return;
        }

        setLoading(true);
        setError(null);
        setInputError(null);
        try {
            let threadId = activeThreadId;
            if (!threadId) {
                threadId = await createThread();
            }

            if (!threadId) {
                return;
            }

            const response = await axios.post(`/chatbot/threads/${threadId}/messages`, {
                content: trimmed,
            });
            const thread = response.data.thread as ThreadDetail;
            setActiveThread(thread);
            upsertThreadSummary(thread);
            setInput('');
        } catch {
            setError('Gagal mengirim pesan.');
        } finally {
            setLoading(false);
        }
    };

    const handleSubmit = async (e: FormEvent) => {
        e.preventDefault();
        if (loading) {
            setError('Tunggu respons sebelumnya selesai.');
            return;
        }

        await sendMessage(input);
    };

    const handleConfirmDraft = async (draftId: number) => {
        setLoading(true);
        setError(null);
        try {
            const response = await axios.post(`/chatbot/drafts/${draftId}/confirm`);
            const thread = response.data.thread as ThreadDetail;
            setActiveThread(thread);
            upsertThreadSummary(thread);
        } catch {
            setError('Konfirmasi draft gagal.');
        } finally {
            setLoading(false);
        }
    };

    const handleCancelDraft = async (draftId: number) => {
        setLoading(true);
        setError(null);
        try {
            const response = await axios.post(`/chatbot/drafts/${draftId}/cancel`);
            const thread = response.data.thread as ThreadDetail;
            setActiveThread(thread);
            upsertThreadSummary(thread);
        } catch {
            setError('Pembatalan draft gagal.');
        } finally {
            setLoading(false);
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Chatbot" />

            <div className="space-y-6 p-6">
                <div>
                    <h1 className="text-3xl font-bold tracking-tight">Chatbot Command</h1>
                    <p className="text-muted-foreground mt-1">
                        Gunakan bahasa natural. Sistem akan menyiapkan draft aksi sebelum dieksekusi.
                    </p>
                </div>

                {error && (
                    <div className="rounded-md border border-destructive bg-destructive/10 p-3 text-sm text-destructive">
                        {error}
                    </div>
                )}

                <div className="grid gap-6 lg:grid-cols-[300px_1fr]">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center justify-between">
                                Threads
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() => void createThread()}
                                >
                                    <Plus className="h-4 w-4" />
                                </Button>
                            </CardTitle>
                            <CardDescription>Percakapan milik user login.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-2">
                            {sortedThreads.length === 0 && (
                                <p className="text-muted-foreground text-sm">Belum ada thread.</p>
                            )}
                            {sortedThreads.map((thread) => (
                                <button
                                    key={thread.id}
                                    onClick={() => void loadThread(thread.id)}
                                    className={`w-full rounded-md border p-3 text-left text-sm ${
                                        activeThreadId === thread.id ? 'border-primary bg-primary/5' : ''
                                    }`}
                                >
                                    <p className="line-clamp-1 font-medium">
                                        {thread.title || `Thread #${thread.id}`}
                                    </p>
                                    <p className="text-muted-foreground mt-1 text-xs">
                                        {new Date(thread.updated_at).toLocaleString()}
                                    </p>
                                </button>
                            ))}
                        </CardContent>
                    </Card>

                    <Card className="min-h-[560px]">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <MessageCircle className="h-5 w-5" />
                                {activeThread?.title || 'Chatbot'}
                            </CardTitle>
                            <CardDescription>
                                Semua aksi disimpan sebagai draft dan perlu konfirmasi.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="space-y-3">
                                {activeThread?.messages.length ? (
                                    activeThread.messages.map((message) => (
                                        <div key={message.id} className="space-y-2">
                                            <div
                                                className={`max-w-[85%] rounded-xl p-3 text-sm ${
                                                    message.sender === 'user'
                                                        ? 'ml-auto bg-primary text-primary-foreground'
                                                        : 'bg-muted'
                                                }`}
                                            >
                                                {message.content}
                                            </div>

                                            {message.draft && (
                                                <div className="ml-0 max-w-[85%] rounded-lg border p-3 text-sm">
                                                    <div className="mb-2 flex items-center gap-2">
                                                        <Badge variant="outline">{message.draft.action_type}</Badge>
                                                        <Badge
                                                            variant={
                                                                message.draft.status === 'needs_confirm'
                                                                    ? 'info'
                                                                    : message.draft.status === 'executed'
                                                                      ? 'success'
                                                                      : message.draft.status === 'failed'
                                                                        ? 'destructive'
                                                                        : 'secondary'
                                                            }
                                                        >
                                                            {message.draft.status}
                                                        </Badge>
                                                    </div>

                                                    {message.draft.payload_json?.preview && (
                                                        <div className="space-y-1 text-xs">
                                                            {Object.entries(message.draft.payload_json.preview).map(
                                                                ([key, value]) => (
                                                                    <div key={key} className="flex gap-2">
                                                                        <span className="text-muted-foreground w-28 capitalize">
                                                                            {key}
                                                                        </span>
                                                                        <span>
                                                                            {Array.isArray(value)
                                                                                ? value.join(', ')
                                                                                : String(value)}
                                                                        </span>
                                                                    </div>
                                                                ),
                                                            )}
                                                        </div>
                                                    )}

                                                    {message.draft.status === 'needs_confirm' && (
                                                        <div className="mt-3 flex gap-2">
                                                            <Button
                                                                size="sm"
                                                                onClick={() =>
                                                                    void handleConfirmDraft(message.draft!.id)
                                                                }
                                                                disabled={loading}
                                                            >
                                                                Konfirmasi
                                                            </Button>
                                                            <Button
                                                                size="sm"
                                                                variant="outline"
                                                                onClick={() =>
                                                                    void handleCancelDraft(message.draft!.id)
                                                                }
                                                                disabled={loading}
                                                            >
                                                                Batal
                                                            </Button>
                                                        </div>
                                                    )}
                                                </div>
                                            )}
                                        </div>
                                    ))
                                ) : (
                                    <p className="text-muted-foreground text-sm">
                                        Belum ada pesan. Pilih contoh perintah di bawah.
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2 border-t pt-4">
                                <p className="text-muted-foreground text-xs font-medium">
                                    Contoh perintah
                                </p>
                                <div className="flex flex-wrap gap-2">
                                    {commandSamples.map((sample) => (
                                        <Button
                                            key={sample}
                                            size="sm"
                                            variant="outline"
                                            onClick={() => setInput(sample)}
                                        >
                                            Gunakan contoh
                                        </Button>
                                    ))}
                                </div>
                            </div>

                            <form onSubmit={handleSubmit} className="flex gap-2 border-t pt-4">
                                <Input
                                    value={input}
                                    onChange={(e) => {
                                        setInput(e.target.value);
                                        if (inputError) {
                                            setInputError(null);
                                        }
                                    }}
                                    placeholder="Tulis instruksi natural, misalnya: jadwalkan rapat besok jam 9..."
                                    disabled={loading}
                                />
                                <Button type="submit" disabled={loading}>
                                    <Send className="h-4 w-4" />
                                </Button>
                            </form>
                            {loading && (
                                <p className="text-muted-foreground text-xs">Sedang memproses...</p>
                            )}
                            {inputError && (
                                <p className="text-destructive text-xs">{inputError}</p>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
