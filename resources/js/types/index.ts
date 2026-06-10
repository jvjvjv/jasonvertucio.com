import type { PageProps } from "@inertiajs/core";

export interface AuthUser {
    id: number;
    name: string;
    email: string;
    permissions: string[];
}

export interface AppBarNavChild {
    href: string;
    label: string;
}

export interface AppBarItem {
    href: string;
    label: string;
    children: AppBarNavChild[];
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

// AI System Prompts

export interface AiSystemPrompt {
    id: number;
    title: string;
    description: string;
    content: string;
}

// AI Systems

export interface AiSystem {
    id: number;
    name: string;
    provider: string;
    api_key: string;
    model: string;
    model_capabilities?: {
        reasoning?: boolean;
        vision?: boolean;
        tools?: boolean;
        max_context_length?: number | null;
    } | null;
    base_url: string | null;
    api_version: string | null;
    max_tokens: number;
    context_length: number | null;
    temperature: number | null;
    config: { [key: string]: unknown } | null;
    credentials?: { [key: string]: unknown } | null;
    auth_type?: string | null;
    endpoint_type?: string | null;
    stream_protocol?: string | null;
    system_prompt_id?: number | null;
    system_prompt?: AiSystemPrompt | null;
    system_prompt_mode?: string | null;
    supports_tools?: boolean;
    allowed_tools?: string[] | null;
    supports_json_mode?: boolean;
    enable_thinking?: boolean | null;
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
    context_length?: number | null;
    temperature?: number | null;
    prompt_template?: string;
    allowed_roles: string[];
    is_active: boolean;
    require_visitor_identity: boolean;
    tools_enabled: boolean;
    conversations_count?: number;
    ai_system: AiSystem;
    usage?: ConversationUsage | null;
    conversations?: Conversation[];
}

export interface McpToolSummary {
    name: string;
    description: string;
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

export interface StatusUpdate {
    id: number;
    status: string;
    notes?: string | null;
    occurred_at: string;
}

export interface TargetedResume {
    id: number;
    company_name: string;
    position: string;
    fit_score: number | null;
    status: string;
    resume_version?: string | null;
    latest_status_update?: { status: string; occurred_at: string } | null;
    docx_path?: boolean;
    pdf_path?: boolean;
    tailored_content?: string | null;
    tailored_title?: string | null;
    status_updates?: StatusUpdate[];
    allowed_next_statuses?: string[];
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
    chat_hash?: string | null;
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
