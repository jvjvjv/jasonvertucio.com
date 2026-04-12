import type { PageProps } from '@inertiajs/core';

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

export interface SharedProps extends PageProps {
    [key: string]: unknown;
    auth: {
        user: AuthUser | null;
    };
    navLinks: NavLink[];
    flash: FlashMessages;
}

// AI Systems

export interface AiSystem {
    id: number;
    name: string;
    provider: string;
    api_key: string;
    model: string;
    base_url: string | null;
    api_version: string | null;
    max_tokens: number;
    temperature: number | null;
    config: Record<string, unknown> | null;
    is_active: boolean;
    interaction_logs_count: number;
    feature_defaults_list: string[];
}

export interface LogEntry {
    id: number;
    created_at_formatted: string;
    user_name: string;
    feature: string;
    status: string;
}

// AI Memories

export interface Memory {
    id: number;
    feature: string;
    category: string;
    key: string;
    confidence: number;
    is_active: boolean;
}

// Pagination

export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

export interface PaginatedResponse<T> {
    data: T[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
}

// Resume / Targeted

export interface TargetedResume {
    id: number;
    company_name: string;
    position: string;
    fit_score: number | null;
    status: string;
    resume_version?: string | null;
    docx_path?: boolean;
    pdf_path?: boolean;
    tailored_content?: string | null;
    tailored_title?: string | null;
}

export interface Conversation {
    id: number;
    status: string;
    title?: string | null;
    updated_at?: string;
    messages_count?: number;
    context: Record<string, string> | null;
    targeted_resume?: TargetedResume | null;
    ai_system_name?: string | null;
}

export interface Message {
    role: string;
    content: string;
}

export interface CoverLetter {
    id: number;
    company_name?: string | null;
    position?: string | null;
    docx_path?: boolean;
    pdf_path?: boolean;
}
