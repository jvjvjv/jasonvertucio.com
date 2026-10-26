import HandymanIcon from "@mui/icons-material/Handyman";
import PsychologyAltIcon from "@mui/icons-material/PsychologyAlt";
import VisibilityIcon from "@mui/icons-material/Visibility";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Chip from "@mui/material/Chip";
import CircularProgress from "@mui/material/CircularProgress";
import MenuItem from "@mui/material/MenuItem";
import TextField from "@mui/material/TextField";
import Typography from "@mui/material/Typography";

import type { ReactNode } from "react";

interface ModelCapabilities {
    reasoning?: boolean | null;
    vision?: boolean;
    tools?: boolean | null;
    max_context_length?: number | null;
    size_bytes?: number | null;
}

interface ModelOption {
    id: string;
    name: string;
    size_bytes?: number | null;
}

// Only providers that expose local model files on disk (e.g. LM Studio's
// native API) report a size; everything else leaves `size_bytes` null.
function formatFileSize(sizeBytes: number): string {
    const units = ["B", "KB", "MB", "GB", "TB"];
    let value = sizeBytes;
    let unitIndex = 0;

    while (value >= 1024 && unitIndex < units.length - 1) {
        value /= 1024;
        unitIndex += 1;
    }

    return `${value.toFixed(value >= 10 || unitIndex === 0 ? 0 : 1)} ${units[unitIndex]}`;
}

interface ProviderOption {
    value: string;
    label: string;
}

interface ProviderModelSelectorProps {
    data: {
        name: string;
        provider: string;
        api_key: string;
        model: string;
        base_url: string;
        api_version: string;
        max_tokens: number;
        context_length: number | null;
        temperature: string;
    };
    errors: Partial<{ [key: string]: string }>;
    providers: ProviderOption[];
    // API version, max tokens, context length and temperature only shape a
    // chat turn; a voice system leaves them out, and its model is optional.
    showChatSettings?: boolean;
    modelRequired?: boolean;
    modelHelperText?: string;
    baseUrlHelperText?: string;
    /** Rendered between the name and the provider row (the voice form's role choice). */
    afterName?: ReactNode;
    availableModels: ModelOption[];
    selectedModelCapabilities: ModelCapabilities | null;
    selectedModelLoaded: boolean;
    fetchingModels: boolean;
    fetchError: string;
    isEdit: boolean;
    pendingFirstEdit: boolean;
    apiKeyRequired: boolean;
    apiKeyHelperText: string;
    hasApiKey: boolean;
    clearApiKey: boolean;
    onClearApiKeyChange: (_value: boolean) => void;
    modelPlaceholder: string;
    baseUrlPlaceholder: string;
    apiVersionPlaceholder: string;
    supportsContextLength: boolean;
    onNameChange: (_value: string) => void;
    onProviderChange: (_value: string) => void;
    onApiKeyChange: (_value: string) => void;
    onApiKeyBlur: () => void;
    onModelChange: (_value: string) => void;
    onBaseUrlChange: (_value: string) => void;
    onApiVersionChange: (_value: string) => void;
    onMaxTokensChange: (_value: string) => void;
    onContextLengthChange: (_value: string) => void;
    onTemperatureChange: (_value: string) => void;
}

export default function ProviderModelSelector({
    data,
    errors,
    providers,
    showChatSettings = true,
    modelRequired = true,
    modelHelperText,
    baseUrlHelperText,
    afterName,
    availableModels,
    selectedModelCapabilities,
    selectedModelLoaded,
    fetchingModels,
    fetchError,
    isEdit,
    pendingFirstEdit,
    apiKeyRequired,
    apiKeyHelperText,
    hasApiKey,
    clearApiKey,
    onClearApiKeyChange,
    modelPlaceholder,
    baseUrlPlaceholder,
    apiVersionPlaceholder,
    supportsContextLength,
    onNameChange,
    onProviderChange,
    onApiKeyChange,
    onApiKeyBlur,
    onModelChange,
    onBaseUrlChange,
    onApiVersionChange,
    onMaxTokensChange,
    onContextLengthChange,
    onTemperatureChange,
}: ProviderModelSelectorProps) {
    // A freshly duplicated system hasn't been saved yet, so its first edit
    // still allows changing Provider, Model, and API Key, like Create.
    const fieldsLocked = isEdit && !pendingFirstEdit;

    return (
        <>
            <TextField
                label="Name"
                required
                size="small"
                fullWidth
                value={data.name}
                onChange={(e) => {
                    onNameChange(e.target.value);
                }}
                error={!!errors.name}
                helperText={errors.name}
                placeholder="My Claude System"
                sx={{ mb: 2 }}
            />

            {afterName}

            {/* Provider, key and model belong together: one row when there is
                room, one field per line when there is not. */}
            <Box
                sx={{
                    display: "grid",
                    gap: 2,
                    gridTemplateColumns: {
                        xs: "minmax(0, 1fr)",
                        md: "repeat(3, minmax(0, 1fr))",
                    },
                    alignItems: "start",
                    mb: 2,
                }}
            >
                <TextField
                    label="Provider"
                    select
                    required
                    size="small"
                    fullWidth
                    value={data.provider}
                    onChange={(e) => {
                        onProviderChange(e.target.value);
                    }}
                    disabled={fieldsLocked}
                    error={!!errors.provider}
                    helperText={
                        errors.provider ??
                        (fieldsLocked
                            ? "Provider cannot be changed after creation."
                            : undefined)
                    }
                >
                    {providers.map((provider) => (
                        <MenuItem key={provider.value} value={provider.value}>
                            {provider.label}
                        </MenuItem>
                    ))}
                </TextField>

                <Box>
                    <TextField
                        label="API Key"
                        type="password"
                        required={apiKeyRequired}
                        size="small"
                        fullWidth
                        value={data.api_key}
                        onChange={(e) => {
                            onApiKeyChange(e.target.value);
                            if (e.target.value) {
                                onClearApiKeyChange(false);
                            }
                        }}
                        disabled={clearApiKey}
                        onBlur={onApiKeyBlur}
                        error={!!errors.api_key}
                        placeholder={hasApiKey ? "Key set" : undefined}
                        autoComplete="off"
                        helperText={apiKeyHelperText}
                    />
                    {hasApiKey && (
                        <Button
                            size="small"
                            color={clearApiKey ? "inherit" : "error"}
                            onClick={() => {
                                onApiKeyChange("");
                                onClearApiKeyChange(!clearApiKey);
                            }}
                            sx={{ mt: 0.5 }}
                        >
                            {clearApiKey ? "Keep stored key" : "Remove key"}
                        </Button>
                    )}
                </Box>

                <Box>
                    <TextField
                        label="Model"
                        select={availableModels.length > 0}
                        required={modelRequired}
                        size="small"
                        fullWidth
                        value={data.model}
                        onChange={(e) => {
                            onModelChange(e.target.value);
                        }}
                        disabled={fieldsLocked}
                        error={!!errors.model}
                        placeholder={modelPlaceholder}
                        helperText={
                            (errors.model ?? fetchError) ||
                            (fieldsLocked
                                ? "Model cannot be changed after creation."
                                : modelHelperText)
                        }
                        slotProps={{
                            input: {
                                endAdornment: fetchingModels ? (
                                    <CircularProgress size={20} />
                                ) : undefined,
                            },
                        }}
                    >
                        {availableModels.map((model) => (
                            <MenuItem key={model.id} value={model.id}>
                                {model.name} ({model.id})
                                {model.size_bytes
                                    ? ` — ${formatFileSize(model.size_bytes)}`
                                    : ""}
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
                            {!!selectedModelCapabilities.size_bytes && (
                                <Chip
                                    size="small"
                                    label={formatFileSize(
                                        selectedModelCapabilities.size_bytes,
                                    )}
                                    variant="outlined"
                                />
                            )}
                            {selectedModelLoaded && (
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

            <TextField
                label="Base URL"
                size="small"
                fullWidth
                value={data.base_url}
                onChange={(e) => {
                    onBaseUrlChange(e.target.value);
                }}
                error={!!errors.base_url}
                helperText={errors.base_url ?? baseUrlHelperText}
                placeholder={baseUrlPlaceholder}
                sx={{ mb: 2 }}
            />

            {showChatSettings && (
                // Four short numeric-ish fields: two columns at every width,
                // phone included.
                <Box
                    sx={{
                        display: "grid",
                        gap: 2,
                        gridTemplateColumns: "repeat(2, minmax(0, 1fr))",
                        mb: 2,
                    }}
                >
                    <TextField
                        label="API Version"
                        size="small"
                        value={data.api_version}
                        onChange={(e) => {
                            onApiVersionChange(e.target.value);
                        }}
                        error={!!errors.api_version}
                        helperText={errors.api_version}
                        placeholder={apiVersionPlaceholder}
                    />
                    <TextField
                        label="Max Tokens"
                        type="number"
                        required
                        size="small"
                        value={data.max_tokens}
                        onChange={(e) => {
                            onMaxTokensChange(e.target.value);
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
                            onContextLengthChange(e.target.value);
                        }}
                        disabled={!supportsContextLength}
                        error={!!errors.context_length}
                        helperText={errors.context_length}
                        slotProps={{ htmlInput: { min: 1, max: 200000 } }}
                    />
                    <TextField
                        label="Temperature"
                        type="number"
                        size="small"
                        value={data.temperature}
                        onChange={(e) => {
                            onTemperatureChange(e.target.value);
                        }}
                        error={!!errors.temperature}
                        helperText={errors.temperature}
                        slotProps={{
                            htmlInput: { min: 0, max: 1, step: 0.01 },
                        }}
                    />
                </Box>
            )}
        </>
    );
}
