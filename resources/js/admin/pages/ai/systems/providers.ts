import type { AiSystemRole } from "@/types";

// Provider metadata shared by the chat and voice system forms.

const VOICE_ROLES: AiSystemRole[] = ["audio", "transcription"];

// Which roles each provider can serve mirrors code-talker's
// AiProvider::supportsRole(); the server rejects any other pairing.
export const PROVIDERS: {
    value: string;
    label: string;
    roles: AiSystemRole[];
}[] = [
    { value: "anthropic", label: "Anthropic", roles: ["chat"] },
    { value: "openai", label: "OpenAI", roles: ["chat", ...VOICE_ROLES] },
    {
        value: "gemini",
        label: "Google Gemini",
        roles: ["chat", ...VOICE_ROLES],
    },
    { value: "grok", label: "xAI Grok", roles: ["chat"] },
    { value: "lm-studio", label: "LM Studio (Native API)", roles: ["chat"] },
    {
        value: "openai-compatible",
        label: "OpenAI-Compatible (OpenRouter, Together, Groq, etc.)",
        roles: ["chat"],
    },
    { value: "elevenlabs", label: "ElevenLabs", roles: VOICE_ROLES },
];

export function providersFor(role: AiSystemRole) {
    return PROVIDERS.filter((provider) => provider.roles.includes(role));
}

export const PROVIDERS_REQUIRING_API_KEY = new Set([
    "anthropic",
    "openai",
    "gemini",
    "grok",
    "elevenlabs",
]);

export const PROVIDER_DEFAULTS: {
    [key: string]: {
        model: string;
        baseUrl: string;
        apiVersion: string;
        authType: string;
        endpointType: string;
        streamProtocol: string;
        systemPromptMode: string;
        isLocalEndpoint: boolean;
    };
} = {
    anthropic: {
        model: "claude-sonnet-4-6",
        baseUrl: "https://api.anthropic.com/v1",
        apiVersion: "2023-06-01",
        authType: "x-api-key",
        endpointType: "managed",
        streamProtocol: "sse",
        systemPromptMode: "top-level",
        isLocalEndpoint: false,
    },
    openai: {
        model: "gpt-4o-mini",
        baseUrl: "https://api.openai.com/v1",
        apiVersion: "",
        authType: "bearer",
        endpointType: "managed",
        streamProtocol: "chunked-json",
        systemPromptMode: "messages",
        isLocalEndpoint: false,
    },
    gemini: {
        model: "gemini-2.5-flash",
        baseUrl: "https://generativelanguage.googleapis.com/v1beta",
        apiVersion: "v1beta",
        authType: "x-api-key",
        endpointType: "managed",
        streamProtocol: "sse",
        systemPromptMode: "top-level",
        isLocalEndpoint: false,
    },
    grok: {
        model: "grok-3-mini",
        baseUrl: "https://api.x.ai/v1",
        apiVersion: "",
        authType: "bearer",
        endpointType: "managed",
        streamProtocol: "chunked-json",
        systemPromptMode: "messages",
        isLocalEndpoint: false,
    },
    "openai-compatible": {
        model: "",
        baseUrl: "http://10.10.0.2:1234/v1",
        apiVersion: "",
        authType: "none",
        endpointType: "openai-compatible",
        streamProtocol: "chunked-json",
        systemPromptMode: "messages",
        isLocalEndpoint: true,
    },
    elevenlabs: {
        model: "",
        baseUrl: "https://api.elevenlabs.io/v1",
        apiVersion: "",
        authType: "x-api-key",
        endpointType: "managed",
        streamProtocol: "sse",
        systemPromptMode: "messages",
        isLocalEndpoint: false,
    },
    "lm-studio": {
        model: "",
        baseUrl: "http://10.10.0.2:1234",
        apiVersion: "",
        authType: "none",
        endpointType: "local",
        streamProtocol: "chunked-json",
        systemPromptMode: "messages",
        isLocalEndpoint: true,
    },
};
