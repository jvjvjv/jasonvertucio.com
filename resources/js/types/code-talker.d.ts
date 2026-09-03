/**
 * Type declarations for the jvjvjv/code-talker frontend contract.
 *
 * The transcript and stream-event halves track the package — they describe what
 * our own endpoint emits when it passes a turn through `SseFrameEncoder`, and
 * are public API covered by the package's semantic version.
 *
 * The page-prop half below is OURS. The package stopped shipping routes and
 * pages in 0.11.0 and dropped those declarations with them, so re-publishing
 * this file wholesale would delete types that `chat/pages/ai/ChatBot.tsx`,
 * `ChatBotsIndex.tsx` and `chat/pages/ai/types.ts` still import. On upgrade,
 * hand-merge the package's version of the two sections it still owns rather
 * than running:
 *   php artisan vendor:publish --tag=code-talker-types --force
 */

// ---------------------------------------------------------------------------
// Transcript (tracks the package)
// ---------------------------------------------------------------------------

/** One contiguous run of assistant output of a single kind. */
export interface MessageBlock {
    type: "text" | "reasoning";
    content: string;
}

/**
 * One visible message, matching what `ChatBotPresenter::transcript()` returns.
 * The system prompt is never part of a transcript.
 */
export interface ChatMessage {
    role: "user" | "assistant";
    content: string;
    /** Populated for reasoning models. */
    reasoning_content: string | null;
    /** Ordered content runs; null on messages stored before blocks existed. */
    blocks: MessageBlock[] | null;
    /**
     * The reply was never finished — the browser hung up, or the server's
     * duration guard cut it off. `content` may be empty or stop mid-sentence;
     * render it as interrupted rather than as an answer.
     */
    incomplete: boolean;
}

// ---------------------------------------------------------------------------
// Page props (ours — see the file header)
// ---------------------------------------------------------------------------

/** One entry in the conversation switcher. */
export interface ChatHistoryEntry {
    /** Opaque id to POST to `switchUrl`. */
    handle: string;
    /** The conversation title, or 'New chat'. */
    label: string;
    is_current: boolean;
    /** No activity for 7 days. */
    is_stale: boolean;
    /** Human-readable, e.g. '3 hours ago'. */
    updated_at: string;
    cost_usd: number | null;
}

export interface ChatBotSummary {
    name: string;
    description: string | null;
    /** Whether anonymous visitors are asked for a name and email. */
    require_visitor_identity: boolean;
    /** Lifetime spend across every conversation with this bot. */
    total_cost_usd: number;
}

/** Props for the component configured as `inertia.components.chat_bot`. */
export interface ChatBotPageProps {
    bot: ChatBotSummary;
    messages: ChatMessage[];
    history: ChatHistoryEntry[];
    /** POST here to send a message; the response is an SSE stream. */
    messageUrl: string;
    resetUrl: string;
    switchUrl: string;
    statusUrl: string;
    warmupUrl: string;
    /** Shareable link, null until the conversation has a hash. */
    chatUrl: string | null;
    /** e.g. '/chat/my-bot/', for building links client-side. */
    chatUrlBase: string;
    showIdentityForm: boolean;
}

/**
 * Props when the page is reached through a shareable hash link.
 *
 * `chatHash` is present only on this route, and `showIdentityForm` is derived
 * differently here: the conversation exists by definition, so the form shows
 * when it has no non-system messages yet.
 */
export interface ChatBotHashPageProps extends ChatBotPageProps {
    chatHash: string | null;
}

// ---------------------------------------------------------------------------
// Index page props
// ---------------------------------------------------------------------------

export interface ChatBotListConversation {
    title: string;
    /** ISO 8601. */
    updated_at: string | null;
    /** Human-readable, e.g. '3 hours ago'. */
    updated_at_human: string;
    is_stale: boolean;
}

export interface ChatBotListEntry {
    slug: string;
    name: string;
    description: string | null;
    new_chat_url: string;
    status_url: string;
    /** Empty for guests — only populated for an authenticated user. */
    conversations: ChatBotListConversation[];
}

/** Props for the component configured as `inertia.components.chat_bots_index`. */
export interface ChatBotsIndexProps {
    bots: ChatBotListEntry[];
}

// ---------------------------------------------------------------------------
// Stream events (tracks the package)
// ---------------------------------------------------------------------------

/**
 * Why the turn stopped. `incomplete` means the turn never finished — the
 * connection dropped, or the server's duration guard cut the generation off —
 * so whatever content arrived stops mid-answer.
 */
export type StopReason = "end_turn" | "max_tokens" | "tool_use" | "incomplete";

/**
 * Why the turn failed. Absent for a recoverable in-stream provider error and
 * for a transport-level failure.
 */
export type ChatStreamErrorReason = "max_stream_duration" | "provider_error";

/** Progress before any tokens arrive. */
export interface StatusEvent {
    type: "status";
    phase: "request_received" | "model_loading";
    message: string;
}

/** The turn has begun. Sent exactly once per turn. */
export interface MessageStartEvent {
    type: "message_start";
    message: { usage: { input_tokens: number | null } };
}

/** Append to the answer. */
export interface ContentBlockDeltaEvent {
    type: "content_block_delta";
    delta: { text: string };
}

/** Append to the reasoning trace. */
export interface ReasoningBlockDeltaEvent {
    type: "reasoning_block_delta";
    delta: { reasoning: string };
}

/** Terminal summary of the turn. */
export interface MessageDeltaEvent {
    type: "message_delta";
    delta: { stop_reason: StopReason };
    usage: { input_tokens: number | null; output_tokens: number | null };
}

/** The turn's content is complete. */
export interface MessageStopEvent {
    type: "message_stop";
}

/**
 * The turn failed. This is terminal on its own — the `[DONE]` sentinel does
 * NOT follow an error frame. Content already delivered is still valid.
 */
export interface ChatStreamErrorEvent {
    type: "error";
    message: string;
    reason?: ChatStreamErrorReason;
}

/**
 * The agent is calling a tool. `text` is always `""` — this is a progress
 * signal, not display text. `input`/`output`/`successful` are present only
 * when the host enabled tool payloads.
 */
export interface ToolUseProgressEvent {
    type: "tool_use_progress";
    text: string;
    tools: string[];
    input?: unknown;
    output?: unknown;
    successful?: boolean;
}

/** A tool changed server state; the page should reload. */
export interface PageReloadEvent {
    type: "page_reload";
}

/**
 * `heartbeat` is deliberately absent from this union. The server yields it as
 * a turn event, but `SseFrameEncoder` writes it as an SSE comment (`: ping`),
 * which never arrives as a message — so a wire consumer cannot receive one and
 * should not be made to handle it. A host consuming the events directly,
 * without the SSE encoding, will see `{ type: 'heartbeat' }`.
 *
 * A turn dispatched with `dispatchTurn()` frames each stored event with an SSE
 * `id:` line carrying its sequence — present only on that path, never for
 * `continueConversation()`. The published client's `ChatTurnCallbacks` reports
 * it through `onSequence?: (sequence: number) => void`: the sequence of the
 * last event received, to pass back as `after` when reconnecting so the turn
 * resumes rather than replays.
 */

/** Every event the message endpoint emits, discriminated on `type`. */
export type ChatStreamEvent =
    | StatusEvent
    | MessageStartEvent
    | ContentBlockDeltaEvent
    | ReasoningBlockDeltaEvent
    | MessageDeltaEvent
    | MessageStopEvent
    | ToolUseProgressEvent
    | PageReloadEvent
    | ChatStreamErrorEvent;

/** The literal frame that terminates a turn that finished normally. */
export type ChatStreamDoneSentinel = "[DONE]";
