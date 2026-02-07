import AppLayout from '@/pages/dashboard/layout';
import type { BreadcrumbItem } from '@/types';
import type { TodoItem, TodoItemFormData, TodoList, TodoListFormData } from '@/types/todo';
import { Head } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { useTodo } from '@/hooks/useTodo';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { Badge } from '@/components/ui/badge';
import InputError from '@/components/ui/input-error';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { ChevronDown, ChevronUp, Pencil, Plus, Trash2 } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Todo', href: '/todo' },
];

function progressStats(list: TodoList) {
    const total = list.items.length;
    const done = list.items.filter((item) => item.is_done).length;
    const percent = total === 0 ? 0 : Math.round((done / total) * 100);
    return { total, done, percent };
}

export default function TodoPage() {
    const {
        lists,
        loading,
        error,
        fetchLists,
        createList,
        updateList,
        deleteList,
        createItem,
        updateItem,
        toggleItem,
        deleteItem,
        reorderItems,
    } = useTodo();

    const [selectedListId, setSelectedListId] = useState<number | null>(null);
    const [listForm, setListForm] = useState<TodoListFormData>({
        title: '',
        start_date: '',
        end_date: '',
    });
    const [listFormError, setListFormError] = useState<string | null>(null);
    const [listEdit, setListEdit] = useState<TodoListFormData>({
        title: '',
        start_date: '',
        end_date: '',
    });

    const [itemForm, setItemForm] = useState<TodoItemFormData>({
        title: '',
        due_date: '',
        start_date: '',
        end_date: '',
    });
    const [itemFormMode, setItemFormMode] = useState<'due' | 'range'>('due');
    const [itemFormError, setItemFormError] = useState<string | null>(null);
    const [editingItem, setEditingItem] = useState<TodoItem | null>(null);

    useEffect(() => {
        fetchLists();
    }, [fetchLists]);

    useEffect(() => {
        if (lists.length > 0 && selectedListId === null) {
            setSelectedListId(lists[0].id);
        }
    }, [lists, selectedListId]);

    const selectedList = useMemo(
        () => lists.find((list) => list.id === selectedListId) || null,
        [lists, selectedListId],
    );

    useEffect(() => {
        if (selectedList) {
            setListEdit({
                title: selectedList.title,
                start_date: selectedList.start_date || '',
                end_date: selectedList.end_date || '',
            });
        }
    }, [selectedList]);

    const resetItemForm = () => {
        setItemForm({
            title: '',
            due_date: '',
            start_date: '',
            end_date: '',
        });
        setItemFormMode('due');
        setEditingItem(null);
    };

    const handleCreateList = async (e: React.FormEvent) => {
        e.preventDefault();
        setListFormError(null);

        try {
            await createList({
                title: listForm.title,
                start_date: listForm.start_date || null,
                end_date: listForm.end_date || null,
            });
            setListForm({ title: '', start_date: '', end_date: '' });
        } catch (err: unknown) {
            if (err && typeof err === 'object' && 'response' in err) {
                const response = (err as { response?: { data?: { errors?: Record<string, string[]> } } }).response;
                if (response?.data?.errors) {
                    setListFormError(response.data.errors.title?.[0] || 'Failed to create list');
                    return;
                }
            }
            setListFormError('Failed to create list');
        }
    };

    const handleUpdateList = async () => {
        if (!selectedList) return;
        setListFormError(null);
        try {
            await updateList(selectedList.id, {
                title: listEdit.title,
                start_date: listEdit.start_date || null,
                end_date: listEdit.end_date || null,
            });
        } catch (err: unknown) {
            setListFormError('Failed to update list');
        }
    };

    const handleDeleteList = async () => {
        if (!selectedList) return;
        if (!confirm('Delete this list and all its items?')) return;
        await deleteList(selectedList.id);
        setSelectedListId(null);
    };

    const handleCreateOrUpdateItem = async (e: React.FormEvent) => {
        e.preventDefault();
        setItemFormError(null);
        if (!selectedList) return;

        const payload: TodoItemFormData = {
            title: itemForm.title,
            due_date: itemFormMode === 'due' ? itemForm.due_date || null : null,
            start_date: itemFormMode === 'range' ? itemForm.start_date || null : null,
            end_date: itemFormMode === 'range' ? itemForm.end_date || null : null,
        };

        try {
            if (editingItem) {
                await updateItem(editingItem.id, payload);
            } else {
                await createItem(selectedList.id, payload);
            }
            resetItemForm();
        } catch (err: unknown) {
            if (err && typeof err === 'object' && 'response' in err) {
                const response = (err as { response?: { data?: { errors?: Record<string, string[]> } } }).response;
                if (response?.data?.errors) {
                    const first = Object.values(response.data.errors)[0]?.[0];
                    setItemFormError(first || 'Failed to save item');
                    return;
                }
            }
            setItemFormError('Failed to save item');
        }
    };

    const handleEditItem = (item: TodoItem) => {
        setEditingItem(item);
        if (item.due_date) {
            setItemFormMode('due');
            setItemForm({
                title: item.title,
                due_date: item.due_date,
                start_date: '',
                end_date: '',
            });
        } else {
            setItemFormMode('range');
            setItemForm({
                title: item.title,
                due_date: '',
                start_date: item.start_date || '',
                end_date: item.end_date || '',
            });
        }
    };

    const handleMoveItem = async (direction: 'up' | 'down', itemId: number) => {
        if (!selectedList) return;
        const items = [...selectedList.items];
        const index = items.findIndex((item) => item.id === itemId);
        const swapWith = direction === 'up' ? index - 1 : index + 1;
        if (index < 0 || swapWith < 0 || swapWith >= items.length) return;
        [items[index], items[swapWith]] = [items[swapWith], items[index]];
        const orderedIds = items.map((item) => item.id);

        await reorderItems(selectedList.id, orderedIds);
        await fetchLists();
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Todo" />

            <div className="flex flex-1 flex-col gap-6 p-6">
                <div>
                    <h1 className="text-3xl font-bold tracking-tight">Todo</h1>
                    <p className="text-muted-foreground mt-1">
                        Kelola list dan item tugas Anda dengan cepat.
                    </p>
                </div>

                {error && (
                    <div className="rounded-md border border-destructive bg-destructive/10 p-4 text-destructive">
                        {error}
                    </div>
                )}

                <div className="grid gap-6 lg:grid-cols-[320px_1fr]">
                    <div className="rounded-xl border bg-card p-4">
                        <h2 className="text-lg font-semibold">Todo Lists</h2>
                        <p className="text-sm text-muted-foreground">
                            Buat dan pilih list untuk dikelola.
                        </p>

                        <form onSubmit={handleCreateList} className="mt-4 space-y-3">
                            <div className="grid gap-2">
                                <Label htmlFor="list-title">Title</Label>
                                <Input
                                    id="list-title"
                                    value={listForm.title}
                                    onChange={(e) =>
                                        setListForm((prev) => ({
                                            ...prev,
                                            title: e.target.value,
                                        }))
                                    }
                                    placeholder="Misal: Minggu ini"
                                    required
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="list-start">Start date (optional)</Label>
                                <Input
                                    id="list-start"
                                    type="date"
                                    value={listForm.start_date || ''}
                                    onChange={(e) =>
                                        setListForm((prev) => ({
                                            ...prev,
                                            start_date: e.target.value,
                                        }))
                                    }
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="list-end">End date (optional)</Label>
                                <Input
                                    id="list-end"
                                    type="date"
                                    value={listForm.end_date || ''}
                                    onChange={(e) =>
                                        setListForm((prev) => ({
                                            ...prev,
                                            end_date: e.target.value,
                                        }))
                                    }
                                />
                            </div>
                            {listFormError && <InputError message={listFormError} />}
                            <Button type="submit" className="w-full" disabled={loading}>
                                <Plus className="mr-2 h-4 w-4" />
                                Create List
                            </Button>
                        </form>

                        <div className="mt-6 space-y-2">
                            {lists.length === 0 && (
                                <div className="rounded-md border border-dashed p-4 text-center text-sm text-muted-foreground">
                                    Belum ada list.
                                </div>
                            )}
                            {lists.map((list) => {
                                const { total, done, percent } = progressStats(list);
                                const active = list.id === selectedListId;
                                return (
                                    <button
                                        key={list.id}
                                        className={`w-full rounded-lg border p-3 text-left transition ${
                                            active
                                                ? 'border-primary bg-primary/5'
                                                : 'hover:bg-muted/40'
                                        }`}
                                        onClick={() => setSelectedListId(list.id)}
                                    >
                                        <div className="flex items-center justify-between">
                                            <div className="font-medium">{list.title}</div>
                                            <Badge variant="secondary">{percent}%</Badge>
                                        </div>
                                        <div className="mt-2 h-2 w-full overflow-hidden rounded-full bg-muted">
                                            <div
                                                className="h-full rounded-full bg-primary transition-all"
                                                style={{ width: `${percent}%` }}
                                            />
                                        </div>
                                        <div className="mt-2 text-xs text-muted-foreground">
                                            {done}/{total} selesai
                                        </div>
                                    </button>
                                );
                            })}
                        </div>
                    </div>

                    <div className="rounded-xl border bg-card p-6">
                        {!selectedList ? (
                            <div className="flex h-full flex-col items-center justify-center gap-2 text-center">
                                <h3 className="text-lg font-semibold">Pilih list</h3>
                                <p className="text-muted-foreground">
                                    Pilih list di sebelah kiri untuk melihat item.
                                </p>
                            </div>
                        ) : (
                            <div className="space-y-6">
                                <div className="flex flex-wrap items-center justify-between gap-4">
                                    <div>
                                        <h2 className="text-2xl font-semibold">Detail List</h2>
                                        <p className="text-sm text-muted-foreground">
                                            Ubah judul atau periode list ini.
                                        </p>
                                    </div>
                                    <div className="flex gap-2">
                                        <Button
                                            variant="outline"
                                            onClick={handleUpdateList}
                                            disabled={loading}
                                        >
                                            <Pencil className="mr-2 h-4 w-4" />
                                            Save List
                                        </Button>
                                        <Button
                                            variant="destructive"
                                            onClick={handleDeleteList}
                                            disabled={loading}
                                        >
                                            <Trash2 className="mr-2 h-4 w-4" />
                                            Delete List
                                        </Button>
                                    </div>
                                </div>

                                <div className="grid gap-4 md:grid-cols-3">
                                    <div className="grid gap-2">
                                        <Label htmlFor="edit-title">Title</Label>
                                        <Input
                                            id="edit-title"
                                            value={listEdit.title}
                                            onChange={(e) =>
                                                setListEdit((prev) => ({
                                                    ...prev,
                                                    title: e.target.value,
                                                }))
                                            }
                                            required
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="edit-start">Start date</Label>
                                        <Input
                                            id="edit-start"
                                            type="date"
                                            value={listEdit.start_date || ''}
                                            onChange={(e) =>
                                                setListEdit((prev) => ({
                                                    ...prev,
                                                    start_date: e.target.value,
                                                }))
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="edit-end">End date</Label>
                                        <Input
                                            id="edit-end"
                                            type="date"
                                            value={listEdit.end_date || ''}
                                            onChange={(e) =>
                                                setListEdit((prev) => ({
                                                    ...prev,
                                                    end_date: e.target.value,
                                                }))
                                            }
                                        />
                                    </div>
                                </div>

                                <form onSubmit={handleCreateOrUpdateItem} className="space-y-4">
                                    <div className="grid gap-2">
                                        <Label htmlFor="item-title">Item</Label>
                                        <Input
                                            id="item-title"
                                            value={itemForm.title}
                                            onChange={(e) =>
                                                setItemForm((prev) => ({
                                                    ...prev,
                                                    title: e.target.value,
                                                }))
                                            }
                                            placeholder="Contoh: Revisi Bab 1"
                                            required
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label>Mode tanggal</Label>
                                        <Select
                                            value={itemFormMode}
                                            onValueChange={(value) =>
                                                setItemFormMode(value as 'due' | 'range')
                                            }
                                        >
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="due">Due date</SelectItem>
                                                <SelectItem value="range">Date range</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    {itemFormMode === 'due' ? (
                                        <div className="grid gap-2">
                                            <Label htmlFor="item-due">Due date</Label>
                                            <Input
                                                id="item-due"
                                                type="date"
                                                value={itemForm.due_date || ''}
                                                onChange={(e) =>
                                                    setItemForm((prev) => ({
                                                        ...prev,
                                                        due_date: e.target.value,
                                                    }))
                                                }
                                                required
                                            />
                                        </div>
                                    ) : (
                                        <div className="grid gap-4 md:grid-cols-2">
                                            <div className="grid gap-2">
                                                <Label htmlFor="item-start">Start date</Label>
                                                <Input
                                                    id="item-start"
                                                    type="date"
                                                    value={itemForm.start_date || ''}
                                                    onChange={(e) =>
                                                        setItemForm((prev) => ({
                                                            ...prev,
                                                            start_date: e.target.value,
                                                        }))
                                                    }
                                                    required
                                                />
                                            </div>
                                            <div className="grid gap-2">
                                                <Label htmlFor="item-end">End date</Label>
                                                <Input
                                                    id="item-end"
                                                    type="date"
                                                    value={itemForm.end_date || ''}
                                                    onChange={(e) =>
                                                        setItemForm((prev) => ({
                                                            ...prev,
                                                            end_date: e.target.value,
                                                        }))
                                                    }
                                                    required
                                                />
                                            </div>
                                        </div>
                                    )}

                                    {itemFormError && <InputError message={itemFormError} />}
                                    <div className="flex flex-wrap gap-2">
                                        <Button type="submit" disabled={loading}>
                                            {editingItem ? 'Update Item' : 'Add Item'}
                                        </Button>
                                        {editingItem && (
                                            <Button
                                                type="button"
                                                variant="outline"
                                                onClick={resetItemForm}
                                            >
                                                Cancel Edit
                                            </Button>
                                        )}
                                    </div>
                                </form>

                                <div className="space-y-3">
                                    {selectedList.items.length === 0 ? (
                                        <div className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                                            Belum ada item.
                                        </div>
                                    ) : (
                                        selectedList.items.map((item, index) => (
                                            <div
                                                key={item.id}
                                                className="flex flex-wrap items-center gap-3 rounded-lg border p-3"
                                            >
                                                <Checkbox
                                                    checked={item.is_done}
                                                    onCheckedChange={(checked) =>
                                                        toggleItem(item.id, Boolean(checked))
                                                    }
                                                />
                                                <div className="flex-1">
                                                    <div
                                                        className={`font-medium ${
                                                            item.is_done
                                                                ? 'line-through text-muted-foreground'
                                                                : ''
                                                        }`}
                                                    >
                                                        {item.title}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        {item.due_date
                                                            ? `Due: ${item.due_date}`
                                                            : `Range: ${item.start_date} - ${item.end_date}`}
                                                    </div>
                                                </div>
                                                <div className="flex items-center gap-1">
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        onClick={() =>
                                                            handleMoveItem('up', item.id)
                                                        }
                                                        disabled={index === 0}
                                                    >
                                                        <ChevronUp className="h-4 w-4" />
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        onClick={() =>
                                                            handleMoveItem('down', item.id)
                                                        }
                                                        disabled={index === selectedList.items.length - 1}
                                                    >
                                                        <ChevronDown className="h-4 w-4" />
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        onClick={() => handleEditItem(item)}
                                                    >
                                                        <Pencil className="h-4 w-4" />
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        onClick={() => deleteItem(item.id)}
                                                    >
                                                        <Trash2 className="h-4 w-4 text-destructive" />
                                                    </Button>
                                                </div>
                                            </div>
                                        ))
                                    )}
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
