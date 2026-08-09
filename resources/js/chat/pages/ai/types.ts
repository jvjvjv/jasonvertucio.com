import type {
    ChatBotListConversation,
    ChatBotListEntry,
} from "@/types/code-talker";

/**
 * Aliases of the package's index-page contract, kept under the local names the
 * app already imports. These were byte-identical redeclarations; sourcing them
 * from the package means a contract change lands as a type error here.
 */
export type ConversationItem = ChatBotListConversation;
export type BotItem = ChatBotListEntry;

/**
 * The readiness payload from `statusUrl` / `warmupUrl`. Still declared locally
 * — the package documents this shape in its README but does not publish a
 * declaration for it.
 */
export interface ModelStatusItem {
    state: "loaded" | "not_loaded" | "unavailable";
    provider: string;
    model: string;
    message: string;
    checked_at: string;
}
