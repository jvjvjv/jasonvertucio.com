import EditIcon from "@mui/icons-material/Edit";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Dialog from "@mui/material/Dialog";
import DialogActions from "@mui/material/DialogActions";
import DialogContent from "@mui/material/DialogContent";
import DialogTitle from "@mui/material/DialogTitle";
import IconButton from "@mui/material/IconButton";
import MenuItem from "@mui/material/MenuItem";
import TextField from "@mui/material/TextField";
import Tooltip from "@mui/material/Tooltip";
import Typography from "@mui/material/Typography";
import { useState } from "react";

import FeatureDefaultsCheckboxes from "./FeatureDefaultsCheckboxes";
import JSONConfigEditor from "./JSONConfigEditor";
import ModelCapabilitiesCheckboxes from "./ModelCapabilitiesCheckboxes";
import ProviderModelSelector from "./ProviderModelSelector";

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
            <ProviderModelSelector
                data={data}
                errors={errors}
                providers={PROVIDERS}
                availableModels={availableModels}
                selectedModelCapabilities={selectedModelCapabilities}
                selectedModelLoaded={!!selectedModel?.loaded}
                fetchingModels={fetchingModels}
                fetchError={fetchError}
                isEdit={isEdit}
                apiKeyRequired={PROVIDERS_REQUIRING_API_KEY.has(data.provider)}
                apiKeyHelperText={apiKeyHelperText}
                modelPlaceholder={modelPlaceholder}
                baseUrlPlaceholder={baseUrlPlaceholder}
                apiVersionPlaceholder={apiVersionPlaceholder}
                supportsContextLength={supportsContextLength}
                onNameChange={(value) => {
                    setData("name", value);
                }}
                onProviderChange={handleProviderChange}
                onApiKeyChange={(value) => {
                    setData("api_key", value);
                }}
                onApiKeyBlur={() => {
                    void fetchModels();
                }}
                onModelChange={(value) => {
                    const nextModel = availableModels.find(
                        (model) => model.id === value,
                    );

                    setData("model", value);
                    setData(
                        "model_capabilities",
                        nextModel
                            ? {
                                  ...(nextModel.capabilities ?? {}),
                                  max_context_length:
                                      nextModel.max_context_length ?? null,
                              }
                            : null,
                    );
                }}
                onBaseUrlChange={(value) => {
                    setData("base_url", value);
                    fetchModelOnFieldChange();
                }}
                onApiVersionChange={(value) => {
                    setData("api_version", value);
                    fetchModelOnFieldChange();
                }}
                onMaxTokensChange={(value) => {
                    setData("max_tokens", parseInt(value) || 0);
                }}
                onContextLengthChange={(value) => {
                    const trimmedValue = value.trim();
                    setData(
                        "context_length",
                        trimmedValue === "" ? null : parseInt(value) || null,
                    );
                }}
                onTemperatureChange={(value) => {
                    setData("temperature", value);
                }}
            />

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

            <JSONConfigEditor
                data={data}
                errors={errors}
                authTypes={AUTH_TYPES}
                endpointTypes={ENDPOINT_TYPES}
                streamProtocols={STREAM_PROTOCOLS}
                systemPromptModes={SYSTEM_PROMPT_MODES}
                onConfigChange={(value) => {
                    setData("config", value);
                }}
                onCredentialsChange={(value) => {
                    setData("credentials", value);
                }}
                onPricingProfileChange={(value) => {
                    setData("pricing_profile", value);
                }}
                onAuthTypeChange={(value) => {
                    setData("auth_type", value);
                }}
                onEndpointTypeChange={(value) => {
                    setData("endpoint_type", value);
                }}
                onStreamProtocolChange={(value) => {
                    setData("stream_protocol", value);
                }}
                onSystemPromptModeChange={(value) => {
                    setData("system_prompt_mode", value);
                }}
            />

            <ModelCapabilitiesCheckboxes
                supportsTools={data.supports_tools}
                supportsJsonMode={data.supports_json_mode}
                enableThinking={data.enable_thinking}
                isLocalEndpoint={data.is_local_endpoint}
                isActive={data.is_active}
                onSupportsToolsChange={(checked) => {
                    setData("supports_tools", checked);
                }}
                onSupportsJsonModeChange={(checked) => {
                    setData("supports_json_mode", checked);
                }}
                onEnableThinkingChange={(checked) => {
                    setData("enable_thinking", checked);
                }}
                onIsLocalEndpointChange={(checked) => {
                    setData("is_local_endpoint", checked);
                }}
                onIsActiveChange={(checked) => {
                    setData("is_active", checked);
                }}
            />

            <FeatureDefaultsCheckboxes
                allFeatures={ALL_FEATURES}
                existingDefaults={existingDefaults}
                selectedDefaults={data.feature_defaults}
                onToggle={handleFeatureToggle}
            />
        </>
    );
}

export type { FormData };
