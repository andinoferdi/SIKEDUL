export interface User {
    id: number;
    name: string;
    email: string;
    username: string;
    phone?: string;
    timezone: string;
    role: 'user' | 'admin';
    is_disabled: boolean;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
}
