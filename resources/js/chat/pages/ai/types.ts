export interface ConversationItem {
    title: string;
    updated_at: string | null;
    updated_at_human: string;
}

export interface ModelStatusItem {
    state: "loaded" | "not_loaded" | "unavailable";
    provider: string;
    model: string;
    message: string;
    checked_at: string;
}

export interface BotItem {
    slug: string;
    name: string;
    description: string | null;
    new_chat_url: string;
    status_url: string;
    conversations: ConversationItem[];
}
