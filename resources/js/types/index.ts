import type { PageProps } from "@inertiajs/core";

export interface AuthUser {
    id: number;
    name: string;
    email: string;
    permissions: string[];
}

export interface AppBarItem {
    href: string;
    label: string;
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
    adminNav: AppBarItem[];
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
    config: { [key: string]: unknown } | null;
    credentials?: { [key: string]: unknown } | null;
    auth_type?: string | null;
    endpoint_type?: string | null;
    stream_protocol?: string | null;
    system_prompt?: string | null;
    system_prompt_mode?: string | null;
    supports_tools?: boolean;
    supports_json_mode?: boolean;
    is_local_endpoint?: boolean;
    pricing_profile?: { [key: string]: unknown } | null;
    is_active: boolean;
    interaction_logs_count: number;
    chat_bots_count: number;
    feature_defaults_list: string[];
}

export interface AiChatBot {
    id: number;
    name: string;
    slug: string;
    access_path: "chat" | "root";
    public_url?: string;
    description: string | null;
    prompt_template?: string;
    allowed_roles: string[];
    is_active: boolean;
    is_public: boolean;
    require_visitor_identity: boolean;
    conversations_count?: number;
    ai_system_id?: number;
    ai_system_name?: string | null;
    usage?: ConversationUsage | null;
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
    content?: string;
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
    applied_at?: string | null;
    docx_path?: boolean;
    pdf_path?: boolean;
    tailored_content?: string | null;
    tailored_title?: string | null;
}

export interface ConversationUsage {
    input_tokens: number | null;
    output_tokens: number | null;
    total_tokens: number | null;
    cost_usd: number | null;
    synced_at?: string | null;
}

export interface Conversation {
    id: number;
    status: string;
    title?: string | null;
    job_url?: string | null;
    last_message_at?: string;
    updated_at?: string;
    messages_count?: number;
    feature?: string;
    visitor_name?: string | null;
    visitor_email?: string | null;
    user_name?: string | null;
    user_email?: string | null;
    ai_chat_bot_name?: string | null;
    ai_chat_bot_slug?: string | null;
    context: { [key: string]: unknown } | null;
    targeted_resume?: TargetedResume | null;
    ai_system_name?: string | null;
    usage?: ConversationUsage | null;
}

export interface Message {
    id?: number;
    role: string;
    content: string;
    metadata?: { [key: string]: unknown } | null;
    created_at?: string | null;
}

export interface CoverLetter {
    id: number;
    company_name?: string | null;
    position?: string | null;
    docx_path?: boolean;
    pdf_path?: boolean;
}
