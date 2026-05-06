import { useState } from 'react';
import Box from '@mui/material/Box';
import Checkbox from '@mui/material/Checkbox';
import CircularProgress from '@mui/material/CircularProgress';
import FormControlLabel from '@mui/material/FormControlLabel';
import MenuItem from '@mui/material/MenuItem';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';

interface ModelOption {
    id: string;
    name: string;
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
    config: string;
    is_active: boolean;
    feature_defaults: string[];
}

interface AiSystemFormProps {
    data: FormData;
    setData: (key: keyof FormData, value: string | number | boolean | string[]) => void;
    errors: Partial<Record<keyof FormData, string>>;
    existingDefaults: string[];
    isEdit?: boolean;
}

const ALL_FEATURES = ['targeted-resume', 'cover-letter'];
const PROVIDERS = [
    { value: "anthropic", label: "Anthropic" },
    { value: "openai", label: "OpenAI" },
    {
        value: "openai-compatible",
        label: "OpenAI-Compatible (LM Studio, OpenRouter, Together, Groq)",
    },
];

const PROVIDERS_REQUIRING_API_KEY = new Set(["anthropic", "openai"]);

export default function AiSystemForm({ data, setData, errors, existingDefaults, isEdit = false }: AiSystemFormProps) {
    const [availableModels, setAvailableModels] = useState<ModelOption[]>([]);
    const [fetchingModels, setFetchingModels] = useState(false);
    const [fetchError, setFetchError] = useState('');

    const fetchModels = async () => {
        if (!data.provider) return;

        if (PROVIDERS_REQUIRING_API_KEY.has(data.provider) && !data.api_key) {
            return;
        }

        setFetchingModels(true);
        setFetchError('');

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

            const result = await response.json();

            if (result.error) {
                setFetchError(result.error);
                setAvailableModels([]);
            } else {
                setAvailableModels(result.models ?? []);
            }
        } catch {
            setFetchError('Failed to fetch models. Check your API key.');
            setAvailableModels([]);
        } finally {
            setFetchingModels(false);
        }
    };

    const handleProviderChange = (value: string) => {
        setData('provider', value);
        setData("model", "");
        setAvailableModels([]);
        setFetchError('');
    };

    const apiKeyHelperText = PROVIDERS_REQUIRING_API_KEY.has(data.provider)
        ? errors.api_key || "Models will be fetched when you leave this field"
        : errors.api_key || "Optional for local/self-hosted endpoints";

    const baseUrlPlaceholder =
        data.provider === "openai"
            ? "https://api.openai.com/v1"
            : data.provider === "openai-compatible"
              ? "http://127.0.0.1:1234/v1"
              : "https://api.anthropic.com/v1";

    const apiVersionPlaceholder =
        data.provider === "anthropic" ? "2023-06-01" : "Optional";

    const handleFeatureToggle = (feature: string) => {
        const current = data.feature_defaults;
        if (current.includes(feature)) {
            setData('feature_defaults', current.filter((f) => f !== feature));
        } else {
            setData('feature_defaults', [...current, feature]);
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
                    onChange={(e) => setData("name", e.target.value)}
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
                    onChange={(e) => handleProviderChange(e.target.value)}
                    disabled={isEdit}
                    error={!!errors.provider}
                    helperText={
                        errors.provider ||
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
                    required
                    size="small"
                    value={data.api_key}
                    onChange={(e) => setData("api_key", e.target.value)}
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
                        onChange={(e) => setData("model", e.target.value)}
                        disabled={isEdit}
                        error={!!errors.model}
                        helperText={
                            errors.model ||
                            fetchError ||
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
                    onChange={(e) => setData("base_url", e.target.value)}
                    error={!!errors.base_url}
                    helperText={errors.base_url}
                    placeholder={baseUrlPlaceholder}
                />
                <TextField
                    label="API Version"
                    size="small"
                    value={data.api_version}
                    onChange={(e) => setData("api_version", e.target.value)}
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
                    onChange={(e) =>
                        setData("max_tokens", parseInt(e.target.value) || 0)
                    }
                    error={!!errors.max_tokens}
                    helperText={errors.max_tokens}
                    slotProps={{ htmlInput: { min: 1, max: 200000 } }}
                />
                <TextField
                    label="Temperature"
                    type="number"
                    size="small"
                    value={data.temperature}
                    onChange={(e) => setData("temperature", e.target.value)}
                    error={!!errors.temperature}
                    helperText={errors.temperature}
                    slotProps={{ htmlInput: { min: 0, max: 1, step: 0.01 } }}
                />
            </Box>

            <TextField
                label="Config (JSON)"
                size="small"
                fullWidth
                multiline
                rows={4}
                value={data.config}
                onChange={(e) => setData("config", e.target.value)}
                error={!!errors.config}
                helperText={errors.config || "Optional JSON configuration"}
                slotProps={{ input: { sx: { fontFamily: "monospace" } } }}
                sx={{ mb: 2 }}
            />

            <FormControlLabel
                control={
                    <Checkbox
                        checked={data.is_active}
                        onChange={(e) => setData("is_active", e.target.checked)}
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
                                    onChange={() =>
                                        handleFeatureToggle(feature)
                                    }
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
