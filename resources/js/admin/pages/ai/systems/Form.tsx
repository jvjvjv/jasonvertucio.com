import EditIcon from "@mui/icons-material/Edit";
import HandymanIcon from "@mui/icons-material/Handyman";
import PsychologyAltIcon from "@mui/icons-material/PsychologyAlt";
import VisibilityIcon from "@mui/icons-material/Visibility";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Checkbox from "@mui/material/Checkbox";
import Chip from "@mui/material/Chip";
import CircularProgress from "@mui/material/CircularProgress";
import Dialog from "@mui/material/Dialog";
import DialogActions from "@mui/material/DialogActions";
import DialogContent from "@mui/material/DialogContent";
import DialogTitle from "@mui/material/DialogTitle";
import FormControlLabel from "@mui/material/FormControlLabel";
import IconButton from "@mui/material/IconButton";
import MenuItem from "@mui/material/MenuItem";
import TextField from "@mui/material/TextField";
import Tooltip from "@mui/material/Tooltip";
import Typography from "@mui/material/Typography";
import { useState } from "react";

import type { AiSystemPrompt } from "@/types";

import { api } from "@/api";

interface ModelCapabilities {
    reasoning?: boolean;
    vision?: boolean;
    tools?: boolean;
    max_context_length?: number | null;
}

interface ModelOption {
    id: string;
    name: string;
    loaded?: boolean;
    max_context_length?: number | null;
    capabilities?: ModelCapabilities;
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
    model_capabilities: ModelCapabilities | null;
    base_url: string;
    api_version: string;
    max_tokens: number;
    context_length: number | null;
    temperature: string;
    system_prompt_id: number | null;
    custom_system_prompt: string;
    config: string;
    credentials: string;
    auth_type: string;
    endpoint_type: string;
    stream_protocol: string;
    system_prompt_mode: string;
    supports_tools: boolean;
    allowed_tools: string[];
    supports_json_mode: boolean;
    enable_thinking: boolean;
    is_local_endpoint: boolean;
    pricing_profile: string;
    is_active: boolean;
    feature_defaults: string[];
}

interface AiSystemFormProps {
    data: FormData;
    setData: (
        _key: keyof FormData,
        _value: string | number | boolean | string[] | ModelCapabilities | null,
    ) => void;
    errors: Partial<{ [key: string]: string }>;
    existingDefaults: string[];
    systemPrompts: AiSystemPrompt[];
    isEdit?: boolean;
}

const ALL_FEATURES = ["targeted-resume", "cover-letter"];
const PROVIDERS = [
    { value: "anthropic", label: "Anthropic" },
    { value: "openai", label: "OpenAI" },
    { value: "gemini", label: "Google Gemini" },
    { value: "grok", label: "xAI Grok" },
    {
        value: "lm-studio",
        label: "LM Studio (Native API)",
    },
    {
        value: "openai-compatible",
        label: "OpenAI-Compatible (OpenRouter, Together, Groq, etc.)",
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
const PROVIDERS_SUPPORTING_CONTEXT_LENGTH = new Set(["lm-studio"]);

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

interface EditPromptFormData {
    title: string;
    description: string;
    content: string;
}

export default function AiSystemForm({
    data,
    setData,
    errors,
    existingDefaults,
    systemPrompts: initialSystemPrompts,
    isEdit = false,
}: AiSystemFormProps) {
    const [availableModels, setAvailableModels] = useState<ModelOption[]>([]);
    const [fetchingModels, setFetchingModels] = useState(false);
    const [fetchError, setFetchError] = useState("");
    const [systemPrompts, setSystemPrompts] =
        useState<AiSystemPrompt[]>(initialSystemPrompts);
    const [editModalOpen, setEditModalOpen] = useState(false);
    const [editPromptForm, setEditPromptForm] = useState<EditPromptFormData>({
        title: "",
        description: "",
        content: "",
    });
    const [editPromptSaving, setEditPromptSaving] = useState(false);

    const fetchModels = async () => {
        if (!data.provider) {
            return;
        }

        if (PROVIDERS_REQUIRING_API_KEY.has(data.provider) && !data.api_key) {
            return;
        }

        setFetchingModels(true);
        setFetchError("");

        try {
            const result = await api.post<FetchModelsResponse>(
                "/api/admin/ai/systems/fetch-models",
                {
                    provider: data.provider,
                    api_key: data.api_key,
                    base_url: data.base_url || null,
                },
            );

            if (result.error) {
                setFetchError(result.error);
                setAvailableModels([]);
            } else {
                const models = result.models ?? [];
                setAvailableModels(models);

                const selectedModel = models.find(
                    (model) => model.id === data.model,
                );

                if (selectedModel) {
                    setData("model_capabilities", {
                        ...(selectedModel.capabilities ?? {}),
                        max_context_length:
                            selectedModel.max_context_length ?? null,
                    });
                }
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
        setData("model_capabilities", null);
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
        if (
            data.provider &&
            (!PROVIDERS_REQUIRING_API_KEY.has(data.provider) || data.api_key)
        ) {
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
    const supportsContextLength = PROVIDERS_SUPPORTING_CONTEXT_LENGTH.has(
        data.provider,
    );
    const selectedModel =
        availableModels.find((model) => model.id === data.model) ?? null;
    const selectedModelCapabilities = selectedModel
        ? {
              ...(selectedModel.capabilities ?? {}),
              max_context_length: selectedModel.max_context_length ?? null,
          }
        : data.model_capabilities;

    // Prompt id locked by feature defaults: targeted-resume locks to 4, cover-letter locks to 5
    const featureLockedPromptId: number | null = data.feature_defaults.includes(
        "targeted-resume",
    )
        ? 4
        : data.feature_defaults.includes("cover-letter")
          ? 5
          : null;
    const isPromptLocked = featureLockedPromptId !== null;
    const effectivePromptId = isPromptLocked
        ? featureLockedPromptId
        : data.system_prompt_id;
    const selectedPrompt =
        systemPrompts.find((p) => p.id === effectivePromptId) ?? null;
    const isCustomPrompt = effectivePromptId === null;

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

    const handlePromptSelect = (value: string) => {
        const id = value === "" ? null : Number(value);
        setData("system_prompt_id", id);
        setData("custom_system_prompt", "");
    };

    const openEditModal = () => {
        if (!selectedPrompt) {
            return;
        }
        setEditPromptForm({
            title: selectedPrompt.title,
            description: selectedPrompt.description,
            content: selectedPrompt.content,
        });
        setEditModalOpen(true);
    };

    const saveEditPrompt = async () => {
        if (!selectedPrompt) {
            return;
        }
        setEditPromptSaving(true);
        try {
            const updated = await api.put<{ prompt: AiSystemPrompt }>(
                `/api/admin/ai/system-prompts/${selectedPrompt.id}`,
                editPromptForm,
            );
            setSystemPrompts((prev) =>
                prev.map((p) =>
                    p.id === updated.prompt.id ? updated.prompt : p,
                ),
            );
            setEditModalOpen(false);
        } finally {
            setEditPromptSaving(false);
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
                            const nextModel = availableModels.find(
                                (model) => model.id === e.target.value,
                            );

                            setData("model", e.target.value);
                            setData(
                                "model_capabilities",
                                nextModel
                                    ? {
                                          ...(nextModel.capabilities ?? {}),
                                          max_context_length:
                                              nextModel.max_context_length ??
                                              null,
                                      }
                                    : null,
                            );
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

                    {selectedModelCapabilities && (
                        <Box
                            sx={{
                                display: "flex",
                                flexWrap: "wrap",
                                gap: 1,
                                mt: 1,
                            }}
                        >
                            {selectedModelCapabilities.reasoning && (
                                <Chip
                                    size="small"
                                    icon={<PsychologyAltIcon />}
                                    label="Reasoning"
                                    variant="outlined"
                                />
                            )}
                            {selectedModelCapabilities.vision && (
                                <Chip
                                    size="small"
                                    icon={<VisibilityIcon />}
                                    label="Vision"
                                    variant="outlined"
                                />
                            )}
                            {selectedModelCapabilities.tools && (
                                <Chip
                                    size="small"
                                    icon={<HandymanIcon />}
                                    label="Tool Use"
                                    variant="outlined"
                                />
                            )}
                            {selectedModelCapabilities.max_context_length && (
                                <Chip
                                    size="small"
                                    label={`Max context ${selectedModelCapabilities.max_context_length.toLocaleString()}`}
                                    variant="outlined"
                                />
                            )}
                            {selectedModel?.loaded && (
                                <Chip
                                    size="small"
                                    color="success"
                                    label="Loaded"
                                    variant="outlined"
                                />
                            )}
                        </Box>
                    )}
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
                    label="Context Length"
                    type="number"
                    size="small"
                    value={data.context_length ?? ""}
                    onChange={(e) => {
                        const value = e.target.value.trim();
                        setData(
                            "context_length",
                            value === "" ? null : parseInt(value) || null,
                        );
                    }}
                    disabled={!supportsContextLength}
                    error={!!errors.context_length}
                    slotProps={{ htmlInput: { min: 1, max: 200000 } }}
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

            {/* System Prompt selector */}
            <Box sx={{ mb: 2 }}>
                <Box sx={{ display: "flex", alignItems: "flex-start", gap: 1 }}>
                    <TextField
                        label="System Prompt"
                        select
                        size="small"
                        fullWidth
                        value={
                            isPromptLocked
                                ? String(featureLockedPromptId)
                                : data.system_prompt_id !== null
                                  ? String(data.system_prompt_id)
                                  : ""
                        }
                        onChange={(e) => {
                            handlePromptSelect(e.target.value);
                        }}
                        disabled={isPromptLocked}
                        error={!!errors.system_prompt_id}
                        helperText={
                            errors.system_prompt_id ??
                            (isPromptLocked
                                ? "Prompt is automatically assigned by the selected feature default."
                                : "Select a reusable prompt or enter a custom one below.")
                        }
                    >
                        <MenuItem value="">Custom prompt</MenuItem>
                        {systemPrompts.map((p) => (
                            <MenuItem key={p.id} value={String(p.id)}>
                                {p.title}
                            </MenuItem>
                        ))}
                    </TextField>
                    <Tooltip
                        title={
                            selectedPrompt
                                ? "Edit this prompt"
                                : "Select a prompt to edit"
                        }
                    >
                        <span>
                            <IconButton
                                size="small"
                                onClick={openEditModal}
                                disabled={!selectedPrompt || isPromptLocked}
                                sx={{ mt: 0.5 }}
                            >
                                <EditIcon fontSize="small" />
                            </IconButton>
                        </span>
                    </Tooltip>
                </Box>
                <TextField
                    label={
                        isCustomPrompt
                            ? "Custom System Prompt"
                            : "Prompt Content"
                    }
                    size="small"
                    fullWidth
                    multiline
                    rows={6}
                    disabled={!isCustomPrompt}
                    value={
                        isCustomPrompt
                            ? data.custom_system_prompt
                            : (selectedPrompt?.content ?? "")
                    }
                    onChange={(e) => {
                        setData("custom_system_prompt", e.target.value);
                    }}
                    slotProps={{
                        input: {
                            sx: {
                                fontFamily: "monospace",
                                fontSize: "0.82rem",
                            },
                        },
                    }}
                    error={!!errors.custom_system_prompt}
                    helperText={errors.custom_system_prompt}
                    sx={{ mt: 1 }}
                />
            </Box>

            {/* Edit Prompt modal */}
            <Dialog
                open={editModalOpen}
                onClose={() => {
                    setEditModalOpen(false);
                }}
                maxWidth="md"
                fullWidth
            >
                <DialogTitle>Edit System Prompt</DialogTitle>
                <DialogContent>
                    <Typography
                        variant="body2"
                        color="warning.main"
                        sx={{ mb: 2, mt: 0.5 }}
                    >
                        Editing this prompt will affect all AI systems that
                        reference it.
                    </Typography>
                    <TextField
                        label="Title"
                        size="small"
                        fullWidth
                        value={editPromptForm.title}
                        onChange={(e) => {
                            setEditPromptForm((prev) => ({
                                ...prev,
                                title: e.target.value,
                            }));
                        }}
                        slotProps={{ htmlInput: { maxLength: 64 } }}
                        sx={{ mb: 2 }}
                    />
                    <TextField
                        label="Description"
                        size="small"
                        fullWidth
                        value={editPromptForm.description}
                        onChange={(e) => {
                            setEditPromptForm((prev) => ({
                                ...prev,
                                description: e.target.value,
                            }));
                        }}
                        slotProps={{ htmlInput: { maxLength: 200 } }}
                        sx={{ mb: 2 }}
                    />
                    <TextField
                        label="Content"
                        size="small"
                        fullWidth
                        multiline
                        rows={16}
                        value={editPromptForm.content}
                        onChange={(e) => {
                            setEditPromptForm((prev) => ({
                                ...prev,
                                content: e.target.value,
                            }));
                        }}
                        slotProps={{
                            input: {
                                sx: {
                                    fontFamily: "monospace",
                                    fontSize: "0.82rem",
                                },
                            },
                        }}
                    />
                </DialogContent>
                <DialogActions>
                    <Button
                        onClick={() => {
                            setEditModalOpen(false);
                        }}
                        color="inherit"
                    >
                        Cancel
                    </Button>
                    <Button
                        onClick={saveEditPrompt}
                        variant="contained"
                        disabled={editPromptSaving}
                    >
                        Save Changes
                    </Button>
                </DialogActions>
            </Dialog>

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
                            checked={data.enable_thinking}
                            onChange={(e) => {
                                setData("enable_thinking", e.target.checked);
                            }}
                        />
                    }
                    label="Enable Thinking (reasoning models)"
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
