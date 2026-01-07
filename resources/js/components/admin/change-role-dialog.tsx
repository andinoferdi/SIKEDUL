import { useState } from 'react';
import { router } from '@inertiajs/react';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Label } from '@/components/ui/label';
import type { User } from '@/types';
import { Shield } from 'lucide-react';

interface ChangeRoleDialogProps {
    user: User;
    currentUserId: number;
}

export default function ChangeRoleDialog({
    user,
    currentUserId,
}: ChangeRoleDialogProps) {
    const [open, setOpen] = useState(false);
    const [selectedRole, setSelectedRole] = useState<'user' | 'admin'>(
        user.role,
    );
    const [processing, setProcessing] = useState(false);

    const isCurrentUser = user.id === currentUserId;

    const handleSubmit = () => {
        setProcessing(true);

        router.patch(
            `/admin/users/${user.id}/role`,
            { role: selectedRole },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setOpen(false);
                    setProcessing(false);
                },
                onError: () => {
                    setProcessing(false);
                },
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    variant="outline"
                    size="sm"
                    disabled={isCurrentUser}
                    title={isCurrentUser ? 'You cannot change your own role' : ''}
                >
                    <Shield className="h-4 w-4 mr-1" />
                    Change Role
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Change User Role</DialogTitle>
                    <DialogDescription>
                        Update the role for {user.name} ({user.email})
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-4 py-4">
                    <div className="grid gap-2">
                        <Label htmlFor="role">Role</Label>
                        <Select
                            value={selectedRole}
                            onValueChange={(value) =>
                                setSelectedRole(value as 'user' | 'admin')
                            }
                        >
                            <SelectTrigger id="role">
                                <SelectValue placeholder="Select a role" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="user">User</SelectItem>
                                <SelectItem value="admin">Admin</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="rounded-lg bg-muted p-3 text-sm">
                        <p className="font-medium mb-1">Current role: {user.role}</p>
                        <p className="text-muted-foreground">
                            {selectedRole === 'admin'
                                ? 'Admins have full access to manage users and system settings.'
                                : 'Users have limited access to their own data only.'}
                        </p>
                    </div>
                </div>

                <DialogFooter>
                    <Button
                        variant="outline"
                        onClick={() => setOpen(false)}
                        disabled={processing}
                    >
                        Cancel
                    </Button>
                    <Button
                        onClick={handleSubmit}
                        disabled={processing || selectedRole === user.role}
                    >
                        {processing ? 'Updating...' : 'Update Role'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
