export interface ConversationItem {
    title: string;
    updated_at: string | null;
    updated_at_human: string;
}

export interface BotItem {
    name: string;
    description: string | null;
    new_chat_url: string;
    conversations: ConversationItem[];
}
