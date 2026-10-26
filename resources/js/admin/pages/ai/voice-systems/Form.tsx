import HearingIcon from "@mui/icons-material/Hearing";
import RecordVoiceOverIcon from "@mui/icons-material/RecordVoiceOver";
import Box from "@mui/material/Box";
import Checkbox from "@mui/material/Checkbox";
import FormControlLabel from "@mui/material/FormControlLabel";
import FormHelperText from "@mui/material/FormHelperText";
import TextField from "@mui/material/TextField";
import ToggleButton from "@mui/material/ToggleButton";
import ToggleButtonGroup from "@mui/material/ToggleButtonGroup";
import Typography from "@mui/material/Typography";
import { useState } from "react";

import ProviderModelSelector from "../systems/ProviderModelSelector";
import {
    PROVIDER_DEFAULTS,
    PROVIDERS_REQUIRING_API_KEY,
    providersFor,
} from "../systems/providers";

import type { ReactNode } from "react";

import { api } from "@/api";

export type VoiceRole = "transcription" | "audio";

export interface VoiceFormData {
    name: string;
    role: VoiceRole;
    provider: string;
    api_key: string;
    clear_api_key: boolean;
    model: string;
    base_url: string;
    default_voice: string;
    is_active: boolean;
    feature_defaults: string[];
}

interface ModelOption {
    id: string;
    name: string;
}

interface VoiceSystemFormProps {
    data: VoiceFormData;
    setData: <K extends keyof VoiceFormData>(
        key: K,
        value: VoiceFormData[K],
    ) => void;
    errors: Partial<{ [key: string]: string }>;
    /** Voice defaults another system already holds. */
    existingDefaults: string[];
    isEdit?: boolean;
    pendingFirstEdit?: boolean;
    aiSystemId?: number;
    hasApiKey?: boolean;
}

/** What each role is for, in the words the rest of the site uses. */
export const VOICE_ROLES: {
    [role in VoiceRole]: {
        title: string;
        detail: string;
        feature: string;
        icon: ReactNode;
    };
} = {
    transcription: {
        title: "Dictation",
        detail: "Speech-to-text: turns what you say into text in a chat box.",
        feature: "voice:dictation",
        icon: <HearingIcon />,
    },
    audio: {
        title: "Read aloud",
        detail: "Text-to-speech: reads replies, cover letters and resumes.",
        feature: "voice:speech",
        icon: <RecordVoiceOverIcon />,
    },
};

export default function VoiceSystemForm({
    data,
    setData,
    errors,
    existingDefaults,
    isEdit = false,
    pendingFirstEdit = false,
    aiSystemId,
    hasApiKey = false,
}: VoiceSystemFormProps) {
    const [availableModels, setAvailableModels] = useState<ModelOption[]>([]);
    const [fetchingModels, setFetchingModels] = useState(false);
    const [fetchError, setFetchError] = useState("");

    const roleLocked = isEdit && !pendingFirstEdit;
    const providers = providersFor(data.role);
    const role = VOICE_ROLES[data.role];
    const takenByOther = existingDefaults.includes(role.feature);
    const isDefault = data.feature_defaults.includes(role.feature);

    const fetchModels = async () => {
        const canUseStoredKey =
            aiSystemId !== undefined && hasApiKey && !data.clear_api_key;

        if (!data.api_key && !canUseStoredKey) {
            return;
        }

        setFetchingModels(true);
        setFetchError("");

        try {
            const result = await api.post<{
                error?: string;
                models?: ModelOption[];
            }>("/api/admin/ai/systems/fetch-models", {
                provider: data.provider,
                api_key: data.api_key,
                ai_system_id: data.api_key ? undefined : aiSystemId,
                base_url: data.base_url || null,
            });

            setAvailableModels(result.error ? [] : (result.models ?? []));
            // A list that does not hold the saved model would hide it.
            if (
                result.models &&
                data.model &&
                !result.models.some((model) => model.id === data.model)
            ) {
                setAvailableModels([]);
            }
            setFetchError(result.error ?? "");
        } catch {
            // Model lookup is a convenience; the field still takes any id.
            setAvailableModels([]);
        } finally {
            setFetchingModels(false);
        }
    };

    const handleRoleChange = (next: VoiceRole | null) => {
        if (next === null || next === data.role) {
            return;
        }

        setData("role", next);
        setData("feature_defaults", []);

        if (!providersFor(next).some((p) => p.value === data.provider)) {
            setData("provider", providersFor(next)[0].value);
        }
    };

    const roleChoice = (
        <Box sx={{ mb: 2.5 }}>
            <Typography variant="subtitle2" sx={{ mb: 1 }}>
                Used for
            </Typography>
            <ToggleButtonGroup
                exclusive
                fullWidth
                value={data.role}
                onChange={(_event, value: VoiceRole | null) => {
                    handleRoleChange(value);
                }}
                disabled={roleLocked}
                aria-label="Used for"
                sx={{
                    display: "grid",
                    gridTemplateColumns: {
                        xs: "minmax(0, 1fr)",
                        sm: "repeat(2, minmax(0, 1fr))",
                    },
                    gap: 1.5,
                    // The group draws joined buttons by default; these are
                    // two separate choices, so each keeps its own border.
                    "& .MuiToggleButtonGroup-grouped": {
                        border: 1,
                        borderColor: "divider",
                        borderRadius: 1,
                        m: 0,
                    },
                    "& .MuiToggleButtonGroup-grouped.Mui-selected": {
                        borderColor: "primary.main",
                        color: "primary.main",
                    },
                    "& .MuiToggleButtonGroup-grouped.Mui-disabled.Mui-selected":
                        {
                            borderColor: "divider",
                            color: "text.secondary",
                        },
                }}
            >
                {/* Once saved, the role is fixed, so only the chosen one shows. */}
                {(Object.keys(VOICE_ROLES) as VoiceRole[])
                    .filter((value) => !roleLocked || value === data.role)
                    .map((value) => (
                        <ToggleButton
                            key={value}
                            value={value}
                            sx={{
                                justifyContent: "flex-start",
                                textAlign: "left",
                                textTransform: "none",
                                gap: 1.5,
                                px: 2,
                                py: 1.5,
                            }}
                        >
                            {VOICE_ROLES[value].icon}
                            <Box>
                                <Typography
                                    component="span"
                                    display="block"
                                    fontWeight={600}
                                    color="text.primary"
                                >
                                    {VOICE_ROLES[value].title}
                                </Typography>
                                <Typography
                                    component="span"
                                    display="block"
                                    variant="body2"
                                    color="text.secondary"
                                >
                                    {VOICE_ROLES[value].detail}
                                </Typography>
                            </Box>
                        </ToggleButton>
                    ))}
            </ToggleButtonGroup>
            {roleLocked ? (
                <FormHelperText>
                    This cannot be changed after the system is created.
                </FormHelperText>
            ) : null}
            {errors.role ? (
                <FormHelperText error>{errors.role}</FormHelperText>
            ) : null}
        </Box>
    );

    return (
        <>
            <ProviderModelSelector
                data={{
                    ...data,
                    api_version: "",
                    max_tokens: 0,
                    context_length: null,
                    temperature: "",
                }}
                errors={errors}
                providers={providers}
                showChatSettings={false}
                modelRequired={false}
                modelHelperText="The model id on your speech server, e.g. Systran/faster-whisper-medium or speaches-ai/Kokoro-82M-v1.0-ONNX. Blank uses a cloud provider's default."
                baseUrlHelperText="For a speech server on your network (such as Speaches), choose OpenAI and enter its address, e.g. http://10.10.0.2:18820/v1. Blank uses the provider's own service."
                afterName={roleChoice}
                availableModels={availableModels}
                selectedModelCapabilities={null}
                selectedModelLoaded={false}
                fetchingModels={fetchingModels}
                fetchError={fetchError}
                isEdit={isEdit}
                pendingFirstEdit={pendingFirstEdit}
                apiKeyRequired={
                    PROVIDERS_REQUIRING_API_KEY.has(data.provider) &&
                    (!isEdit || !hasApiKey)
                }
                hasApiKey={hasApiKey}
                clearApiKey={data.clear_api_key}
                onClearApiKeyChange={(value) => {
                    setData("clear_api_key", value);
                }}
                apiKeyHelperText={
                    errors.api_key ??
                    (data.clear_api_key
                        ? "The stored key will be removed when you save."
                        : hasApiKey
                          ? "A key is set. Leave this blank to keep it."
                          : "A local speech server ignores the key, so any text will do.")
                }
                modelPlaceholder="Model id"
                baseUrlPlaceholder={
                    data.provider in PROVIDER_DEFAULTS
                        ? PROVIDER_DEFAULTS[data.provider].baseUrl
                        : ""
                }
                apiVersionPlaceholder=""
                supportsContextLength={false}
                onNameChange={(value) => {
                    setData("name", value);
                }}
                onProviderChange={(value) => {
                    setData("provider", value);
                    setAvailableModels([]);
                }}
                onApiKeyChange={(value) => {
                    setData("api_key", value);
                }}
                onApiKeyBlur={() => {
                    void fetchModels();
                }}
                onModelChange={(value) => {
                    setData("model", value);
                }}
                onBaseUrlChange={(value) => {
                    setData("base_url", value);
                }}
                onApiVersionChange={() => undefined}
                onMaxTokensChange={() => undefined}
                onContextLengthChange={() => undefined}
                onTemperatureChange={() => undefined}
            />

            {data.role === "audio" ? (
                <TextField
                    label="Default voice"
                    size="small"
                    fullWidth
                    value={data.default_voice}
                    onChange={(e) => {
                        setData("default_voice", e.target.value);
                    }}
                    error={!!errors.default_voice}
                    helperText={
                        errors.default_voice ??
                        "The voice to read in, e.g. af_heart for Kokoro. Blank uses the server's default."
                    }
                    placeholder="af_heart"
                    sx={{ mb: 2 }}
                />
            ) : null}

            <Box sx={{ display: "flex", flexDirection: "column", gap: 0.5 }}>
                <FormControlLabel
                    control={
                        <Checkbox
                            checked={isDefault}
                            disabled={takenByOther}
                            onChange={(e) => {
                                setData(
                                    "feature_defaults",
                                    e.target.checked ? [role.feature] : [],
                                );
                            }}
                        />
                    }
                    label={`Use this system for ${role.title.toLowerCase()}`}
                />
                <FormHelperText sx={{ mt: -0.5, ml: 4 }}>
                    {takenByOther
                        ? `Another system is used for ${role.title.toLowerCase()}. Untick it there first.`
                        : `Voice controls use the one system ticked here for ${role.title.toLowerCase()}.`}
                </FormHelperText>
                {errors["feature_defaults.0"] ? (
                    <FormHelperText error sx={{ ml: 4 }}>
                        {errors["feature_defaults.0"]}
                    </FormHelperText>
                ) : null}

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
                />
            </Box>
        </>
    );
}
