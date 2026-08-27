import HandymanIcon from "@mui/icons-material/Handyman";
import PsychologyAltIcon from "@mui/icons-material/PsychologyAlt";
import VisibilityIcon from "@mui/icons-material/Visibility";
import Box from "@mui/material/Box";
import Chip from "@mui/material/Chip";
import CircularProgress from "@mui/material/CircularProgress";
import MenuItem from "@mui/material/MenuItem";
import TextField from "@mui/material/TextField";
import Typography from "@mui/material/Typography";

interface ModelCapabilities {
    reasoning?: boolean | null;
    vision?: boolean;
    tools?: boolean | null;
    max_context_length?: number | null;
}

interface ModelOption {
    id: string;
    name: string;
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
    availableModels: ModelOption[];
    selectedModelCapabilities: ModelCapabilities | null;
    selectedModelLoaded: boolean;
    fetchingModels: boolean;
    fetchError: string;
    isEdit: boolean;
    pendingFirstEdit: boolean;
    apiKeyRequired: boolean;
    apiKeyHelperText: string;
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
    availableModels,
    selectedModelCapabilities,
    selectedModelLoaded,
    fetchingModels,
    fetchError,
    isEdit,
    pendingFirstEdit,
    apiKeyRequired,
    apiKeyHelperText,
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
                        onNameChange(e.target.value);
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
                    required={apiKeyRequired}
                    size="small"
                    value={data.api_key}
                    onChange={(e) => {
                        onApiKeyChange(e.target.value);
                    }}
                    disabled={fieldsLocked}
                    onBlur={onApiKeyBlur}
                    error={!!errors.api_key}
                    helperText={
                        fieldsLocked
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
                            onModelChange(e.target.value);
                        }}
                        disabled={fieldsLocked}
                        error={!!errors.model}
                        placeholder={modelPlaceholder}
                        helperText={
                            (errors.model ?? fetchError) ||
                            (fieldsLocked
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
                        {availableModels.map((model) => (
                            <MenuItem key={model.id} value={model.id}>
                                {model.name} ({model.id})
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
                        onBaseUrlChange(e.target.value);
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
                        onApiVersionChange(e.target.value);
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
                        onTemperatureChange(e.target.value);
                    }}
                    error={!!errors.temperature}
                    helperText={errors.temperature}
                    slotProps={{ htmlInput: { min: 0, max: 1, step: 0.01 } }}
                />
            </Box>
        </>
    );
}
