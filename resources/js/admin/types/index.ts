export interface AuthUser {
    id: number;
    name: string;
    email: string;
}

export interface NavLink {
    label: string;
    href: string;
    target?: string;
    can?: string;
    divider?: boolean;
}

export interface FlashMessages {
    success: string | null;
    error: string | null;
}

export interface SharedProps {
    auth: {
        user: AuthUser | null;
    };
    navLinks: NavLink[];
    flash: FlashMessages;
}
