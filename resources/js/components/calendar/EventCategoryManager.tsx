import CategoryBadge from '@/components/calendar/CategoryBadge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import InputError from '@/components/ui/input-error';
import { Label } from '@/components/ui/label';
import type { CategoryFormData, EventCategory } from '@/types/calendar';
import { Folder, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';

interface EventCategoryManagerProps {
    categories: EventCategory[];
    onCreateCategory: (data: CategoryFormData) => Promise<void>;
    onDeleteCategory: (categoryId: number) => Promise<void>;
}

export default function EventCategoryManager({
    categories,
    onCreateCategory,
    onDeleteCategory,
}: EventCategoryManagerProps) {
    const [open, setOpen] = useState(false);
    const [name, setName] = useState('');
    const [color, setColor] = useState('#6366F1');
    const [error, setError] = useState<string | null>(null);
    const [loading, setLoading] = useState(false);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setError(null);
        setLoading(true);

        try {
            await onCreateCategory({ name, color });
            setName('');
            setColor('#6366F1');
        } catch (err: unknown) {
            if (err && typeof err === 'object' && 'response' in err) {
                const response = (err as { response: { data: { errors?: Record<string, string[]> } } }).response;
                if (response?.data?.errors) {
                    setError(response.data.errors.name?.[0] || 'Failed to create category');
                } else {
                    setError('Failed to create category');
                }
            } else {
                setError('Failed to create category');
            }
        } finally {
            setLoading(false);
        }
    };

    const handleDelete = async (categoryId: number) => {
        if (!confirm('Are you sure you want to delete this category?')) {
            return;
        }

        try {
            await onDeleteCategory(categoryId);
        } catch (err: unknown) {
            alert('Failed to delete category');
        }
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline">
                    <Folder className="mr-2 h-4 w-4" />
                    Manage Categories
                </Button>
            </DialogTrigger>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle>Manage Event Categories</DialogTitle>
                    <DialogDescription>
                        Create and manage categories to organize your events.
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4">
                    {/* Create Category Form */}
                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div className="grid gap-2">
                            <Label htmlFor="name">
                                Category Name{' '}
                                <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id="name"
                                value={name}
                                onChange={(e) => setName(e.target.value)}
                                placeholder="e.g., Work, Personal, Health"
                                required
                            />
                            {error && <InputError message={error} />}
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="color">Color</Label>
                            <div className="flex items-center gap-2">
                                <Input
                                    id="color"
                                    type="color"
                                    value={color}
                                    onChange={(e) => setColor(e.target.value)}
                                    className="h-10 w-20"
                                />
                                <Input
                                    type="text"
                                    value={color}
                                    onChange={(e) => setColor(e.target.value)}
                                    placeholder="#6366F1"
                                    className="flex-1"
                                />
                            </div>
                        </div>

                        <Button type="submit" disabled={loading} className="w-full">
                            <Plus className="mr-2 h-4 w-4" />
                            {loading ? 'Creating...' : 'Create Category'}
                        </Button>
                    </form>

                    {/* Category List */}
                    {categories.length > 0 && (
                        <div className="space-y-2 border-t pt-4">
                            <Label>Existing Categories</Label>
                            <div className="space-y-2">
                                {categories.map((category) => (
                                    <div
                                        key={category.id}
                                        className="flex items-center justify-between rounded-md border p-2"
                                    >
                                        <CategoryBadge category={category} />
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() =>
                                                handleDelete(category.id)
                                            }
                                        >
                                            <Trash2 className="h-4 w-4 text-destructive" />
                                        </Button>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => setOpen(false)}
                    >
                        Close
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
