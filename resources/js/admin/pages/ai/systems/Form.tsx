import Box from "@mui/material/Box";
import Checkbox from "@mui/material/Checkbox";
import CircularProgress from "@mui/material/CircularProgress";
import FormControlLabel from "@mui/material/FormControlLabel";
import MenuItem from "@mui/material/MenuItem";
import TextField from "@mui/material/TextField";
import Typography from "@mui/material/Typography";
import { useState } from "react";

interface ModelOption {
    id: string;
    name: string;
}

interface FetchModelsResponse {
    error?: string;
    models?: ModelOption[];
}

interface FormData {
    name: string;
    provider: string;
    api_key: string;
    model: string;
    base_url: string;
    api_version: string;
    max_tokens: number;
    temperature: string;
    system_prompt: string;
    config: string;
    credentials: string;
    auth_type: string;
    endpoint_type: string;
    stream_protocol: string;
    system_prompt_mode: string;
    supports_tools: boolean;
    supports_json_mode: boolean;
    is_local_endpoint: boolean;
    pricing_profile: string;
    is_active: boolean;
    feature_defaults: string[];
}

interface AiSystemFormProps {
    data: FormData;
    setData: (
        _key: keyof FormData,
        _value: string | number | boolean | string[],
    ) => void;
    errors: Partial<{ [key: string]: string }>;
    existingDefaults: string[];
    isEdit?: boolean;
}

const ALL_FEATURES = ["targeted-resume", "cover-letter"];
const PROVIDERS = [
    { value: "anthropic", label: "Anthropic" },
    { value: "openai", label: "OpenAI" },
    { value: "gemini", label: "Google Gemini" },
    { value: "grok", label: "xAI Grok" },
    {
        value: "openai-compatible",
        label: "OpenAI-Compatible (LM Studio, OpenRouter, Together, Groq)",
    },
];

const PROVIDERS_REQUIRING_API_KEY = new Set([
    "anthropic",
    "openai",
    "gemini",
    "grok",
]);
const AUTH_TYPES = ["bearer", "x-api-key", "none", "custom"];
const ENDPOINT_TYPES = ["managed", "openai-compatible", "local"];
const STREAM_PROTOCOLS = ["sse", "chunked-json", "json-lines"];
const SYSTEM_PROMPT_MODES = ["top-level", "messages"];

const PROVIDER_DEFAULTS: {
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
};

export default function AiSystemForm({
    data,
    setData,
    errors,
    existingDefaults,
    isEdit = false,
}: AiSystemFormProps) {
    const [availableModels, setAvailableModels] = useState<ModelOption[]>([]);
    const [fetchingModels, setFetchingModels] = useState(false);
    const [fetchError, setFetchError] = useState("");

    const fetchModels = async () => {
        if (!data.provider) return;

        if (PROVIDERS_REQUIRING_API_KEY.has(data.provider) && !data.api_key) {
            return;
        }

        setFetchingModels(true);
        setFetchError("");

        try {
            const response = await fetch("/admin/ai/systems/fetch-models", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN":
                        document.querySelector<HTMLMetaElement>(
                            'meta[name="csrf-token"]',
                        )?.content ?? "",
                },
                body: JSON.stringify({
                    provider: data.provider,
                    api_key: data.api_key,
                    base_url: data.base_url || null,
                }),
            });

            const result = (await response.json()) as FetchModelsResponse;

            if (result.error) {
                setFetchError(result.error);
                setAvailableModels([]);
            } else {
                setAvailableModels(result.models ?? []);
            }
        } catch {
            setFetchError("Failed to fetch models. Check your API key.");
            setAvailableModels([]);
        } finally {
            setFetchingModels(false);
        }
    };

    const handleProviderChange = (value: string) => {
        const defaults = PROVIDER_DEFAULTS[value];

        setData("provider", value);
        setData("model", "");
        setAvailableModels([]);
        setFetchError("");

        setData("base_url", defaults.baseUrl);
        setData("api_version", defaults.apiVersion);
        setData("auth_type", defaults.authType);
        setData("endpoint_type", defaults.endpointType);
        setData("stream_protocol", defaults.streamProtocol);
        setData("system_prompt_mode", defaults.systemPromptMode);
        setData("is_local_endpoint", defaults.isLocalEndpoint);
    };

    const fetchModelOnFieldChange = () => {
        if (data.provider && data.api_key) {
            void fetchModels();
        }
    };

    const apiKeyHelperText = PROVIDERS_REQUIRING_API_KEY.has(data.provider)
        ? (errors.api_key ?? "Models will be fetched when you leave this field")
        : (errors.api_key ?? "Optional for local/self-hosted endpoints");

    const providerDefaults =
        PROVIDER_DEFAULTS[data.provider] ?? PROVIDER_DEFAULTS.anthropic;
    const baseUrlPlaceholder = providerDefaults.baseUrl;
    const apiVersionPlaceholder = providerDefaults.apiVersion || "Optional";
    const modelPlaceholder =
        providerDefaults.model || "Fetch models or enter model id";

    const handleFeatureToggle = (feature: string) => {
        const current = data.feature_defaults;
        if (current.includes(feature)) {
            setData(
                "feature_defaults",
                current.filter((f) => f !== feature),
            );
        } else {
            setData("feature_defaults", [...current, feature]);
        }
    };

    return (
        <>
            <Box
                sx={{
                    display: "grid",
                    gap: 2,
                    gridTemplateColumns: { xs: "1fr", md: "1fr 1fr" },
                    mb: 2,
                }}
            >
                <TextField
                    label="Name"
                    required
                    size="small"
                    value={data.name}
                    onChange={(e) => {
                        setData("name", e.target.value);
                    }}
                    error={!!errors.name}
                    helperText={errors.name}
                    placeholder="My Claude System"
                />
                <TextField
                    label="Provider"
                    select
                    required
                    size="small"
                    value={data.provider}
                    onChange={(e) => {
                        handleProviderChange(e.target.value);
                    }}
                    disabled={isEdit}
                    error={!!errors.provider}
                    helperText={
                        errors.provider ??
                        (isEdit
                            ? "Provider cannot be changed after creation."
                            : undefined)
                    }
                >
                    {PROVIDERS.map((p) => (
                        <MenuItem key={p.value} value={p.value}>
                            {p.label}
                        </MenuItem>
                    ))}
                </TextField>
            </Box>

            <Box
                sx={{
                    display: "grid",
                    gap: 2,
                    gridTemplateColumns: { xs: "1fr", md: "1fr 1fr" },
                    mb: 2,
                }}
            >
                <TextField
                    label="API Key"
                    type="password"
                    required={PROVIDERS_REQUIRING_API_KEY.has(data.provider)}
                    size="small"
                    value={data.api_key}
                    onChange={(e) => {
                        setData("api_key", e.target.value);
                    }}
                    disabled={isEdit}
                    onBlur={fetchModels}
                    error={!!errors.api_key}
                    helperText={
                        isEdit
                            ? "API key is stored securely and can only be changed by duplicating this system."
                            : apiKeyHelperText
                    }
                />

                <Box>
                    <TextField
                        label="Model"
                        select={availableModels.length > 0}
                        required
                        size="small"
                        fullWidth
                        value={data.model}
                        onChange={(e) => {
                            setData("model", e.target.value);
                        }}
                        disabled={isEdit}
                        error={!!errors.model}
                        placeholder={modelPlaceholder}
                        helperText={
                            (errors.model ?? fetchError) ||
                            (isEdit
                                ? "Model cannot be changed after creation."
                                : undefined)
                        }
                        slotProps={{
                            input: {
                                endAdornment: fetchingModels ? (
                                    <CircularProgress size={20} />
                                ) : undefined,
                            },
                        }}
                    >
                        {availableModels.map((m) => (
                            <MenuItem key={m.id} value={m.id}>
                                {m.name} ({m.id})
                            </MenuItem>
                        ))}
                    </TextField>
                </Box>
            </Box>

            <Typography variant="subtitle2" sx={{ mt: 2, mb: 1 }}>
                Provider Settings
            </Typography>

            <Box
                sx={{
                    display: "grid",
                    gap: 2,
                    gridTemplateColumns: { xs: "1fr", md: "1fr 1fr" },
                    mb: 2,
                }}
            >
                <TextField
                    label="Base URL"
                    size="small"
                    value={data.base_url}
                    onChange={(e) => {
                        setData("base_url", e.target.value);
                        fetchModelOnFieldChange();
                    }}
                    error={!!errors.base_url}
                    helperText={errors.base_url}
                    placeholder={baseUrlPlaceholder}
                />

                <TextField
                    label="API Version"
                    size="small"
                    value={data.api_version}
                    onChange={(e) => {
                        setData("api_version", e.target.value);
                        fetchModelOnFieldChange();
                    }}
                    error={!!errors.api_version}
                    helperText={errors.api_version}
                    placeholder={apiVersionPlaceholder}
                />
            </Box>

            <Box
                sx={{
                    display: "grid",
                    gap: 2,
                    gridTemplateColumns: { xs: "1fr", md: "1fr 1fr" },
                    mb: 2,
                }}
            >
                <TextField
                    label="Max Tokens"
                    type="number"
                    required
                    size="small"
                    value={data.max_tokens}
                    onChange={(e) => {
                        setData("max_tokens", parseInt(e.target.value) || 0);
                    }}
                    error={!!errors.max_tokens}
                    helperText={errors.max_tokens}
                    slotProps={{ htmlInput: { min: 1, max: 200000 } }}
                />
                <TextField
                    label="Temperature"
                    type="number"
                    size="small"
                    value={data.temperature}
                    onChange={(e) => {
                        setData("temperature", e.target.value);
                    }}
                    error={!!errors.temperature}
                    helperText={errors.temperature}
                    slotProps={{ htmlInput: { min: 0, max: 1, step: 0.01 } }}
                />
            </Box>

            <TextField
                label="System Prompt"
                size="small"
                fullWidth
                multiline
                rows={4}
                value={data.system_prompt}
                onChange={(e) => {
                    setData("system_prompt", e.target.value);
                }}
                error={!!errors.system_prompt}
                helperText={
                    errors.system_prompt ??
                    "Optional default system prompt for this AI system"
                }
                sx={{ mb: 2 }}
            />

            <TextField
                label="Config (JSON)"
                size="small"
                fullWidth
                multiline
                rows={4}
                value={data.config}
                onChange={(e) => {
                    setData("config", e.target.value);
                }}
                error={!!errors.config}
                helperText={errors.config ?? "Optional JSON configuration"}
                slotProps={{ input: { sx: { fontFamily: "monospace" } } }}
                sx={{ mb: 2 }}
            />

            <Box
                sx={{
                    display: "grid",
                    gap: 2,
                    gridTemplateColumns: { xs: "1fr", md: "1fr 1fr" },
                    mb: 2,
                }}
            >
                <TextField
                    label="Auth Type"
                    select
                    size="small"
                    value={data.auth_type}
                    onChange={(e) => {
                        setData("auth_type", e.target.value);
                    }}
                    error={!!errors.auth_type}
                    helperText={
                        errors.auth_type ?? "Optional provider auth mode"
                    }
                >
                    <MenuItem value="">Default</MenuItem>
                    {AUTH_TYPES.map((value) => (
                        <MenuItem key={value} value={value}>
                            {value}
                        </MenuItem>
                    ))}
                </TextField>

                <TextField
                    label="Endpoint Type"
                    select
                    size="small"
                    value={data.endpoint_type}
                    onChange={(e) => {
                        setData("endpoint_type", e.target.value);
                    }}
                    error={!!errors.endpoint_type}
                    helperText={
                        errors.endpoint_type ??
                        "Optional endpoint classification"
                    }
                >
                    <MenuItem value="">Default</MenuItem>
                    {ENDPOINT_TYPES.map((value) => (
                        <MenuItem key={value} value={value}>
                            {value}
                        </MenuItem>
                    ))}
                </TextField>
            </Box>

            <Box
                sx={{
                    display: "grid",
                    gap: 2,
                    gridTemplateColumns: { xs: "1fr", md: "1fr 1fr" },
                    mb: 2,
                }}
            >
                <TextField
                    label="Stream Protocol"
                    select
                    size="small"
                    value={data.stream_protocol}
                    onChange={(e) => {
                        setData("stream_protocol", e.target.value);
                    }}
                    error={!!errors.stream_protocol}
                    helperText={
                        errors.stream_protocol ?? "Optional stream parser hint"
                    }
                >
                    <MenuItem value="">Default</MenuItem>
                    {STREAM_PROTOCOLS.map((value) => (
                        <MenuItem key={value} value={value}>
                            {value}
                        </MenuItem>
                    ))}
                </TextField>

                <TextField
                    label="System Prompt Mode"
                    select
                    size="small"
                    value={data.system_prompt_mode}
                    onChange={(e) => {
                        setData("system_prompt_mode", e.target.value);
                    }}
                    error={!!errors.system_prompt_mode}
                    helperText={
                        errors.system_prompt_mode ??
                        "How provider expects system prompts"
                    }
                >
                    <MenuItem value="">Default</MenuItem>
                    {SYSTEM_PROMPT_MODES.map((value) => (
                        <MenuItem key={value} value={value}>
                            {value}
                        </MenuItem>
                    ))}
                </TextField>
            </Box>

            <TextField
                label="Credentials (JSON)"
                size="small"
                fullWidth
                multiline
                rows={4}
                value={data.credentials}
                onChange={(e) => {
                    setData("credentials", e.target.value);
                }}
                error={!!errors.credentials}
                helperText={
                    errors.credentials ??
                    "Optional encrypted credential payload for provider-specific keys"
                }
                slotProps={{ input: { sx: { fontFamily: "monospace" } } }}
                sx={{ mb: 2 }}
            />

            <TextField
                label="Pricing Profile (JSON)"
                size="small"
                fullWidth
                multiline
                rows={4}
                value={data.pricing_profile}
                onChange={(e) => {
                    setData("pricing_profile", e.target.value);
                }}
                error={!!errors.pricing_profile}
                helperText={
                    errors.pricing_profile ??
                    "Optional per-system pricing override"
                }
                slotProps={{ input: { sx: { fontFamily: "monospace" } } }}
                sx={{ mb: 2 }}
            />

            <Box
                sx={{
                    display: "grid",
                    gap: 1,
                    gridTemplateColumns: { xs: "1fr", md: "1fr 1fr" },
                    mb: 2,
                }}
            >
                <FormControlLabel
                    control={
                        <Checkbox
                            checked={data.supports_tools}
                            onChange={(e) => {
                                setData("supports_tools", e.target.checked);
                            }}
                        />
                    }
                    label="Supports Tools"
                />

                <FormControlLabel
                    control={
                        <Checkbox
                            checked={data.supports_json_mode}
                            onChange={(e) => {
                                setData("supports_json_mode", e.target.checked);
                            }}
                        />
                    }
                    label="Supports JSON Mode"
                />

                <FormControlLabel
                    control={
                        <Checkbox
                            checked={data.is_local_endpoint}
                            onChange={(e) => {
                                setData("is_local_endpoint", e.target.checked);
                            }}
                        />
                    }
                    label="Local Endpoint"
                />
            </Box>

            <FormControlLabel
                control={
                    <Checkbox
                        checked={data.is_active}
                        onChange={(e) => {
                            setData("is_active", e.target.checked);
                        }}
                    />
                }
                label="Active"
                sx={{ mb: 2 }}
            />

            <Box sx={{ mb: 2 }}>
                <Typography variant="subtitle2" sx={{ mb: 1 }}>
                    Feature Defaults
                </Typography>
                <Typography
                    variant="caption"
                    color="text.secondary"
                    display="block"
                    sx={{ mb: 1 }}
                >
                    Select features this system should be the default for.
                    Greyed-out features are already assigned to another system.
                </Typography>
                {ALL_FEATURES.map((feature) => {
                    const takenByOther = existingDefaults.includes(feature);
                    return (
                        <FormControlLabel
                            key={feature}
                            control={
                                <Checkbox
                                    checked={data.feature_defaults.includes(
                                        feature,
                                    )}
                                    onChange={() => {
                                        handleFeatureToggle(feature);
                                    }}
                                    disabled={takenByOther}
                                />
                            }
                            label={feature}
                        />
                    );
                })}
            </Box>
        </>
    );
}

export type { FormData };
