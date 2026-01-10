import CategoryBadge from '@/components/calendar/CategoryBadge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import InputError from '@/components/ui/input-error';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { formatForInput } from '@/lib/timezone';
import type { Event, EventCategory, EventFormData } from '@/types/calendar';
import { Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';

interface EventDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    event: Event | null;
    initialStart?: Date;
    initialEnd?: Date;
    timezone: string;
    categories: EventCategory[];
    onSave: (data: EventFormData) => Promise<void>;
    onUpdate: (eventId: number, data: Partial<EventFormData>) => Promise<void>;
    onDelete: (eventId: number) => Promise<void>;
}

export default function EventDialog({
    open,
    onOpenChange,
    event,
    initialStart,
    initialEnd,
    timezone,
    categories,
    onSave,
    onUpdate,
    onDelete,
}: EventDialogProps) {
    const [formData, setFormData] = useState<EventFormData>({
        title: '',
        description: '',
        start_at: '',
        end_at: '',
        category_id: undefined,
        status: 'planned',
    });
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [loading, setLoading] = useState(false);

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

    const getStatusLabel = (status: string) => {
        switch (status) {
            case 'done':
                return 'Done';
            case 'canceled':
                return 'Canceled';
            default:
                return 'Planned';
        }
    };

    useEffect(() => {
        if (event) {
            setFormData({
                title: event.title,
                description: event.description || '',
                start_at: formatForInput(event.start, timezone),
                end_at: formatForInput(event.end, timezone),
                category_id: event.category_id,
                status: event.status,
            });
        } else if (initialStart && initialEnd) {
            setFormData({
                title: '',
                description: '',
                start_at: formatForInput(initialStart, timezone),
                end_at: formatForInput(initialEnd, timezone),
                category_id: undefined,
                status: 'planned',
            });
        }
        setErrors({});
    }, [event, initialStart, initialEnd, timezone, open]);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setErrors({});
        setLoading(true);

        try {
            if (event) {
                await onUpdate(event.id, formData);
            } else {
                await onSave(formData);
            }
            onOpenChange(false);
        } catch (err: unknown) {
            if (err && typeof err === 'object' && 'response' in err) {
                const response = (err as { response: { data: { errors?: Record<string, string[]> } } }).response;
                if (response?.data?.errors) {
                    const formattedErrors: Record<string, string> = {};
                    Object.entries(response.data.errors).forEach(
                        ([key, messages]) => {
                            formattedErrors[key] = messages[0];
                        },
                    );
                    setErrors(formattedErrors);
                } else {
                    setErrors({ submit: 'Failed to save event' });
                }
            } else {
                setErrors({ submit: 'Failed to save event' });
            }
        } finally {
            setLoading(false);
        }
    };

    const handleDelete = async () => {
        if (!event) return;
        if (!confirm('Are you sure you want to delete this event?')) return;

        setLoading(true);
        try {
            await onDelete(event.id);
            onOpenChange(false);
        } catch (err: unknown) {
            alert('Failed to delete event');
        } finally {
            setLoading(false);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-xl">
                <DialogHeader>
                    <DialogTitle>
                        {event ? 'Edit Event' : 'Create New Event'}
                    </DialogTitle>
                    <DialogDescription>
                        {event
                            ? 'Update event details'
                            : 'Add a new event to your calendar'}
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit}>
                    <div className="grid gap-4 py-4">
                        <div className="grid gap-2">
                            <Label htmlFor="title">
                                Title <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id="title"
                                value={formData.title}
                                onChange={(e) =>
                                    setFormData({
                                        ...formData,
                                        title: e.target.value,
                                    })
                                }
                                placeholder="Event title"
                                required
                            />
                            {errors.title && (
                                <InputError message={errors.title} />
                            )}
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="description">Description</Label>
                            <Textarea
                                id="description"
                                value={formData.description}
                                onChange={(e) =>
                                    setFormData({
                                        ...formData,
                                        description: e.target.value,
                                    })
                                }
                                placeholder="Event description"
                                rows={3}
                            />
                            {errors.description && (
                                <InputError message={errors.description} />
                            )}
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="start_at">
                                    Start <span className="text-destructive">*</span>
                                </Label>
                                <Input
                                    id="start_at"
                                    type="datetime-local"
                                    value={formData.start_at}
                                    onChange={(e) =>
                                        setFormData({
                                            ...formData,
                                            start_at: e.target.value,
                                        })
                                    }
                                    required
                                />
                                {errors.start_at && (
                                    <InputError message={errors.start_at} />
                                )}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="end_at">
                                    End <span className="text-destructive">*</span>
                                </Label>
                                <Input
                                    id="end_at"
                                    type="datetime-local"
                                    value={formData.end_at}
                                    onChange={(e) =>
                                        setFormData({
                                            ...formData,
                                            end_at: e.target.value,
                                        })
                                    }
                                    required
                                />
                                {errors.end_at && (
                                    <InputError message={errors.end_at} />
                                )}
                            </div>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="category">Category</Label>
                            <Select
                                value={
                                    formData.category_id
                                        ? formData.category_id.toString()
                                        : ''
                                }
                                onValueChange={(value) =>
                                    setFormData({
                                        ...formData,
                                        category_id: value ? parseInt(value) : undefined,
                                    })
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select a category" />
                                </SelectTrigger>
                                <SelectContent>
                                    {categories.map((category) => (
                                        <SelectItem
                                            key={category.id}
                                            value={category.id.toString()}
                                        >
                                            <div className="flex items-center gap-2">
                                                <CategoryBadge
                                                    category={category}
                                                />
                                            </div>
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors.category_id && (
                                <InputError message={errors.category_id} />
                            )}
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="status">Status</Label>
                            <Select
                                value={formData.status}
                                onValueChange={(value) =>
                                    setFormData({
                                        ...formData,
                                        status: value as 'planned' | 'done' | 'canceled',
                                    })
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue>
                                        <Badge variant={getStatusVariant(formData.status)}>
                                            {getStatusLabel(formData.status)}
                                        </Badge>
                                    </SelectValue>
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="planned">
                                        <Badge variant="info">Planned</Badge>
                                    </SelectItem>
                                    <SelectItem value="done">
                                        <Badge variant="success">Done</Badge>
                                    </SelectItem>
                                    <SelectItem value="canceled">
                                        <Badge variant="destructive">Canceled</Badge>
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            {errors.status && (
                                <InputError message={errors.status} />
                            )}
                        </div>

                        {errors.submit && (
                            <InputError message={errors.submit} />
                        )}
                    </div>

                    <DialogFooter>
                        <div className="flex w-full items-center justify-between">
                            <div>
                                {event && (
                                    <Button
                                        type="button"
                                        variant="destructive"
                                        onClick={handleDelete}
                                        disabled={loading}
                                    >
                                        <Trash2 className="mr-2 h-4 w-4" />
                                        Delete
                                    </Button>
                                )}
                            </div>
                            <div className="flex gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => onOpenChange(false)}
                                    disabled={loading}
                                >
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={loading}>
                                    {loading
                                        ? 'Saving...'
                                        : event
                                          ? 'Update Event'
                                          : 'Create Event'}
                                </Button>
                            </div>
                        </div>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
