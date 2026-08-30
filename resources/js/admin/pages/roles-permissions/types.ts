export interface Role {
    id: number;
    name: string;
    title: string | null;
    description: string | null;
    users_count: number;
    permissions: string[];
}

export interface Permission {
    id: number;
    name: string;
    title: string | null;
    description: string | null;
    roles_count: number;
}
