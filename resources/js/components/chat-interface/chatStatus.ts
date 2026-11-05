import type { ModelStatus } from "@/components/ChatInterface";
import type { MessageBlock } from "@/components/ChatMessageBubble";
import type { ToolPanel } from "@/components/ToolsPanel";

/**
 * How prominent a state is, which picks the rail's colour and the caption's
 * text colour. `working` is the only tone whose rail can move.
 */
export type ChatStatusTone = "ready" | "idle" | "working" | "warning" | "error";

export type ChatStatusKind =
    | "expired"
    | "recording"
    | "transcribing"
    | "dictation-problem"
    | "reply-failed"
    | "replying"
    | "unavailable"
    | "checking"
    | "waking"
    | "not-loaded"
    | "ready"
    | "idle";

/**
 * The one thing the chat is saying right now: drawn as the composer's rail
 * and spelled out in the caption line beneath it.
 *
 * For `ready`, `text` is only what the caption shows (the model's name, or
 * nothing); the caption supplies the word "Ready" for assistive technology.
 */
export interface ChatStatus {
    kind: ChatStatusKind;
    tone: ChatStatusTone;
    text: string;
    /** Whether the rail's segment travels. Only work under way moves. */
    moving: boolean;
}

export interface ChatStatusInput {
    isExpired: boolean;
    error: string;
    isStreaming: boolean;
    /** A server status frame while streaming; warm-up text otherwise. */
    loadingMessage: string;
    streamingBlocks: MessageBlock[];
    streamingToolPanels: ToolPanel[];
    modelStatus: ModelStatus | null;
    isCheckingModelStatus: boolean;
    isWarmingModel: boolean;
    isAuthenticated: boolean;
}

const ESC_STOPS = "Esc stops it.";

const DEFAULT_WAKING_TEXT =
    "Waking the model. The first reply can take a minute.";

/** Ends a sentence with a full stop unless it already has closing punctuation. */
function asSentence(text: string): string {
    const trimmed = text.trim();

    return /[.!?…]$/.test(trimmed) ? trimmed : `${trimmed}.`;
}

/** What the turn is doing, judged by the newest thing it has streamed. */
function replyProgressText(
    loadingMessage: string,
    streamingBlocks: MessageBlock[],
    streamingToolPanels: ToolPanel[],
): string {
    if (loadingMessage.trim() !== "") {
        return asSentence(loadingMessage);
    }

    // `useChatStream` clears the blocks whenever a tool call arrives, so
    // blocks present means text or reasoning came after the newest tool.
    const lastBlock = streamingBlocks[streamingBlocks.length - 1] as
        MessageBlock | undefined;

    if (lastBlock?.type === "text") {
        return "Writing the reply.";
    }

    if (lastBlock?.type === "reasoning") {
        return "Thinking.";
    }

    const lastPanel = streamingToolPanels[streamingToolPanels.length - 1] as
        ToolPanel | undefined;

    if (lastPanel && lastPanel.output === undefined) {
        const tools = lastPanel.tools.join(", ");

        return tools === "" ? "Running a tool." : `Running ${tools}.`;
    }

    return "Thinking.";
}

/**
 * Resolves the chat's single status from everything that can affect it,
 * in the order the `chat-status-readout` spec gives. Dictation (the spec's
 * second state) is reported by `DictationControl` and merged over this by
 * `ChatInputArea`, below expiry.
 */
export function resolveChatStatus({
    isExpired,
    error,
    isStreaming,
    loadingMessage,
    streamingBlocks,
    streamingToolPanels,
    modelStatus,
    isCheckingModelStatus,
    isWarmingModel,
    isAuthenticated,
}: ChatStatusInput): ChatStatus {
    if (isExpired) {
        return {
            kind: "expired",
            tone: "warning",
            text: "Your session has expired.",
            moving: false,
        };
    }

    if (error !== "") {
        return {
            kind: "reply-failed",
            tone: "error",
            text: error,
            moving: false,
        };
    }

    if (isStreaming) {
        return {
            kind: "replying",
            tone: "working",
            text: `${replyProgressText(loadingMessage, streamingBlocks, streamingToolPanels)} ${ESC_STOPS}`,
            moving: true,
        };
    }

    if (modelStatus?.state === "unavailable") {
        return {
            kind: "unavailable",
            tone: "error",
            text: "The provider is unavailable, so sending is off.",
            moving: false,
        };
    }

    if (isCheckingModelStatus) {
        return {
            kind: "checking",
            tone: "working",
            text: "Checking the model.",
            moving: false,
        };
    }

    if (isWarmingModel || loadingMessage.trim() !== "") {
        return {
            kind: "waking",
            tone: "working",
            text:
                loadingMessage.trim() !== ""
                    ? loadingMessage.trim()
                    : DEFAULT_WAKING_TEXT,
            moving: true,
        };
    }

    if (modelStatus?.state === "not_loaded") {
        return {
            kind: "not-loaded",
            tone: "warning",
            text: modelStatus.message,
            moving: false,
        };
    }

    if (modelStatus?.state === "loaded") {
        return {
            kind: "ready",
            tone: "ready",
            text: isAuthenticated && modelStatus.model ? modelStatus.model : "",
            moving: false,
        };
    }

    return { kind: "idle", tone: "idle", text: "", moving: false };
}
