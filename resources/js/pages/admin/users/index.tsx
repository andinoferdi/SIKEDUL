import AppLayout from '@/pages/dashboard/layout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import type { User, BreadcrumbItem, SharedData } from '@/types';
import { useEffect, useState } from 'react';
import ToggleStatusButton from '@/components/admin/toggle-status-button';
import CreateUserDialog from '@/components/admin/create-user-dialog';
import { Search } from 'lucide-react';

interface AdminUsersPageProps {
    users: {
        data: User[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        links: Array<{
            url: string | null;
            label: string;
            active: boolean;
        }>;
    };
    filters: {
        search?: string;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Users', href: '/admin/users' },
];

export default function AdminUsersIndex({ users, filters }: AdminUsersPageProps) {
    const { auth } = usePage<SharedData>().props;
    const [search, setSearch] = useState(filters.search || '');

    // Debounced search
    useEffect(() => {
        const timeout = setTimeout(() => {
            router.get(
                '/admin/users',
                { search },
                {
                    preserveState: true,
                    preserveScroll: true,
                },
            );
        }, 500);

        return () => clearTimeout(timeout);
    }, [search]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="User Management" />

            <div className="space-y-6 p-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">
                            User Management
                        </h1>
                        <p className="text-muted-foreground mt-1">
                            Manage user roles and account status
                        </p>
                    </div>
                </div>

                {/* Search Bar */}
                <div className="flex items-center justify-between">
                    <div className="relative flex-1 max-w-sm">
                        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            type="text"
                            placeholder="Search by email, username, or name..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="pl-9"
                        />
                    </div>
                    <CreateUserDialog />
                </div>

                {/* Users Table */}
                <div className="rounded-lg border bg-card">
                    <Table>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead className="w-[80px]">ID</TableHead>
                                <TableHead>Name</TableHead>
                                <TableHead>Email</TableHead>
                                <TableHead>Username</TableHead>
                                <TableHead className="w-[100px]">Role</TableHead>
                                <TableHead className="w-[100px]">Status</TableHead>
                                <TableHead className="w-[150px] text-right">
                                    Actions
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {users.data.length === 0 ? (
                                <TableRow>
                                    <TableCell
                                        colSpan={7}
                                        className="h-24 text-center text-muted-foreground"
                                    >
                                        No users found
                                    </TableCell>
                                </TableRow>
                            ) : (
                                users.data.map((user) => (
                                    <TableRow key={user.id}>
                                        <TableCell className="font-mono font-medium text-muted-foreground">
                                            {user.id}
                                        </TableCell>
                                        <TableCell className="font-medium">
                                            {user.name}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {user.email}
                                        </TableCell>
                                        <TableCell className="font-mono text-sm">
                                            {user.username}
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant={
                                                    user.role === 'admin'
                                                        ? 'default'
                                                        : 'secondary'
                                                }
                                                className="capitalize"
                                            >
                                                {user.role}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant={
                                                    user.is_disabled
                                                        ? 'destructive'
                                                        : 'default'
                                                }
                                                className={
                                                    !user.is_disabled
                                                        ? 'bg-green-600 hover:bg-green-700'
                                                        : ''
                                                }
                                            >
                                                {user.is_disabled
                                                    ? 'Disabled'
                                                    : 'Active'}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex items-center justify-end gap-2">
                                                <ToggleStatusButton
                                                    user={user}
                                                    currentUserId={auth.user.id}
                                                />
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </div>

                {/* Pagination and Info */}
                <div className="flex items-center justify-between">
                    {users.last_page > 1 ? (
                        <div className="flex items-center justify-center gap-1 flex-1">
                            {users.links.map((link, index) => (
                                <Link
                                    key={index}
                                    href={link.url || '#'}
                                    preserveState
                                    preserveScroll
                                    className={`px-3 py-2 text-sm rounded-md border transition-colors ${
                                        link.active
                                            ? 'bg-primary text-primary-foreground border-primary'
                                            : 'hover:bg-muted'
                                    } ${!link.url ? 'opacity-50 cursor-not-allowed' : ''}`}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ))}
                        </div>
                    ) : (
                        <div className="flex-1"></div>
                    )}
                    <div className="text-sm text-muted-foreground">
                        {users.total} user{users.total !== 1 ? 's' : ''} found
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
