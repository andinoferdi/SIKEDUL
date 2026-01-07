import { useState } from 'react';
import { router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import type { User } from '@/types';
import { Ban, CheckCircle } from 'lucide-react';

interface ToggleStatusButtonProps {
    user: User;
    currentUserId: number;
}

export default function ToggleStatusButton({
    user,
    currentUserId,
}: ToggleStatusButtonProps) {
    const [processing, setProcessing] = useState(false);
    const isCurrentUser = user.id === currentUserId;
    const willDisable = !user.is_disabled;

    const handleToggle = () => {
        setProcessing(true);

        router.patch(
            `/admin/users/${user.id}/toggle-status`,
            {},
            {
                preserveScroll: true,
                onFinish: () => {
                    setProcessing(false);
                },
            },
        );
    };

    return (
        <AlertDialog>
            <AlertDialogTrigger asChild>
                <Button
                    variant={user.is_disabled ? 'default' : 'destructive'}
                    size="sm"
                    disabled={isCurrentUser || processing}
                    title={
                        isCurrentUser
                            ? 'You cannot change your own status'
                            : ''
                    }
                >
                    {user.is_disabled ? (
                        <>
                            <CheckCircle className="h-4 w-4 mr-1" />
                            Enable
                        </>
                    ) : (
                        <>
                            <Ban className="h-4 w-4 mr-1" />
                            Disable
                        </>
                    )}
                </Button>
            </AlertDialogTrigger>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>
                        {willDisable ? 'Disable User Account' : 'Enable User Account'}
                    </AlertDialogTitle>
                    <AlertDialogDescription>
                        {willDisable ? (
                            <>
                                Are you sure you want to disable{' '}
                                <span className="font-semibold">{user.name}</span>'s
                                account?
                                <br />
                                <br />
                                They will not be able to login until you enable their
                                account again.
                            </>
                        ) : (
                            <>
                                Are you sure you want to enable{' '}
                                <span className="font-semibold">{user.name}</span>'s
                                account?
                                <br />
                                <br />
                                They will be able to login and access the system.
                            </>
                        )}
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel disabled={processing}>
                        Cancel
                    </AlertDialogCancel>
                    <AlertDialogAction
                        onClick={handleToggle}
                        disabled={processing}
                        className={
                            willDisable
                                ? 'bg-destructive hover:bg-destructive/90'
                                : ''
                        }
                    >
                        {processing
                            ? 'Processing...'
                            : willDisable
                              ? 'Disable User'
                              : 'Enable User'}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
