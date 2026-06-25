export type User = {
    id: number;
    full_name: string;
    email: string;
    role: 'admin' | 'guest';
    is_active: boolean;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};