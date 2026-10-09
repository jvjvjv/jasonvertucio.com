import type { MessageBlock } from "./code-talker";
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
    external?: boolean;
}

export interface AppBarItem {
    href: string;
    label: string;
    external?: boolean;
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

export interface SessionInfo {
    expiresAt: string;
}

export interface SharedProps extends PageProps {
    [key: string]: unknown;
    auth: {
        user: AuthUser | null;
    };
    adminNav: AppBarItem[];
    navLinks: NavLink[];
    session: SessionInfo;
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
        reasoning?: boolean | null;
        vision?: boolean;
        tools?: boolean | null;
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
    web_tool_policy?: {
        allowed_domains?: string[];
        credentials?: { [host: string]: { [header: string]: string } };
    } | null;
    supports_json_mode?: boolean;
    enable_thinking?: boolean | null;
    is_local_endpoint?: boolean;
    /** @deprecated No longer editable through the admin UI; slated for removal. */
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
    required_permission?: string | null;
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
    source?: "internal" | "remote";
    server_slug?: string | null;
}

// External MCP servers (code-talker AiMcpServerManager::list() shape — never
// carries the server's auth, only its type)

export type McpServerAuthType =
    "none" | "bearer" | "headers" | "client_credentials";

export interface McpServerTool {
    exposed_name: string | null;
    remote_name: string;
    title: string | null;
    description: string | null;
    available: boolean;
    unavailable_reason: string | null;
    synced_at: string | null;
}

export interface McpServer {
    id: number;
    slug: string;
    name: string;
    transport: string;
    url: string;
    auth_type: McpServerAuthType;
    timeout_seconds: number | null;
    enabled: boolean;
    last_synced_at: string | null;
    last_sync_error: string | null;
    tools: McpServerTool[];
    tool_count: number;
}

/** The per-server catalog the AI system tool picker groups remote tools by. */
export interface McpServerCatalogEntry {
    id: number;
    slug: string;
    name: string;
    enabled: boolean;
    tools: {
        exposed_name: string | null;
        description: string | null;
        available: boolean;
        unavailable_reason: string | null;
    }[];
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
    /** Non-nullable column, never hidden on the model — always serialized. */
    content: string;
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

// Resume / Applications

export interface StatusUpdate {
    id: number;
    status: string;
    notes?: string | null;
    occurred_at: string;
}

/** A dated entry in an application's pipeline history. */
export type ApplicationStatusUpdate = StatusUpdate;

export interface ResumeVersionOption {
    id: number;
    version: string;
    is_current: boolean;
}

/**
 * The tailored document only. Everything about the job it was written for —
 * company, position, fit, status, history — lives on the `Application`.
 */
export interface TargetedResume {
    id: number;
    /** Professional headline substituted into the document's letterhead. */
    title: string | null;
    tailored_content: string | null;
    /** Label of the main resume version this document was tailored from. */
    resume_version: string | null;
    docx_path: boolean;
    pdf_path: boolean;
}

/** A tracked job, as the Application Discussion page receives it. */
export interface Application {
    id: number;
    /** One of the nine `ApplicationStatus` values. */
    status: string;
    company_name: string;
    position: string;
    location: string | null;
    job_description: string;
    job_url: string | null;
    fit_score: number | null;
    fit_summary: string | null;
    /** The main resume version recorded for the application. */
    resume_version: { id: number; version: string } | null;
    status_updates: ApplicationStatusUpdate[];
    allowed_next_statuses: string[];
    /** An `applied` history entry exists: the resume is the record of what was sent. */
    has_applied: boolean;
}

/** A row of the Applications list. */
export interface ApplicationListItem {
    id: number;
    company_name: string;
    position: string;
    location: string | null;
    status: string;
    fit_score: number | null;
    resume_version: string | null;
    targeted_resume_id: number | null;
    has_conversation: boolean;
    messages_count: number | null;
    usage: ConversationUsage | null;
    latest_status_update: { status: string; occurred_at: string } | null;
    /** Already humanised by the server ("3 days ago"). */
    last_activity_at: string | null;
}

/** The AI session attached to an application, when it has one. */
export interface ApplicationConversation {
    id: number;
    status: string;
    title: string | null;
    context: { [key: string]: unknown } | null;
    ai_system_id: number | null;
    ai_system_name: string | null;
    usage: ConversationUsage | null;
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
    ai_system_id?: number | null;
    title?: string | null;
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
    ai_system_name?: string | null;
    usage?: ConversationUsage | null;
}

export interface Message {
    id?: number;
    role: string;
    content: string;
    reasoning_content?: string | null;
    blocks?: MessageBlock[] | null;
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
