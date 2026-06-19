import Box from "@mui/material/Box";
import MenuItem from "@mui/material/MenuItem";
import TextField from "@mui/material/TextField";

interface JSONConfigEditorProps {
    data: {
        config: string;
        credentials: string;
        pricing_profile: string;
        auth_type: string;
        endpoint_type: string;
        stream_protocol: string;
        system_prompt_mode: string;
    };
    errors: Partial<{ [key: string]: string }>;
    authTypes: string[];
    endpointTypes: string[];
    streamProtocols: string[];
    systemPromptModes: string[];
    onConfigChange: (_value: string) => void;
    onCredentialsChange: (_value: string) => void;
    onPricingProfileChange: (_value: string) => void;
    onAuthTypeChange: (_value: string) => void;
    onEndpointTypeChange: (_value: string) => void;
    onStreamProtocolChange: (_value: string) => void;
    onSystemPromptModeChange: (_value: string) => void;
}

export default function JSONConfigEditor({
    data,
    errors,
    authTypes,
    endpointTypes,
    streamProtocols,
    systemPromptModes,
    onConfigChange,
    onCredentialsChange,
    onPricingProfileChange,
    onAuthTypeChange,
    onEndpointTypeChange,
    onStreamProtocolChange,
    onSystemPromptModeChange,
}: JSONConfigEditorProps) {
    return (
        <>
            <TextField
                label="Config (JSON)"
                size="small"
                fullWidth
                multiline
                rows={4}
                value={data.config}
                onChange={(e) => {
                    onConfigChange(e.target.value);
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
                        onAuthTypeChange(e.target.value);
                    }}
                    error={!!errors.auth_type}
                    helperText={
                        errors.auth_type ?? "Optional provider auth mode"
                    }
                >
                    <MenuItem value="">Default</MenuItem>
                    {authTypes.map((value) => (
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
                        onEndpointTypeChange(e.target.value);
                    }}
                    error={!!errors.endpoint_type}
                    helperText={
                        errors.endpoint_type ??
                        "Optional endpoint classification"
                    }
                >
                    <MenuItem value="">Default</MenuItem>
                    {endpointTypes.map((value) => (
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
                        onStreamProtocolChange(e.target.value);
                    }}
                    error={!!errors.stream_protocol}
                    helperText={
                        errors.stream_protocol ?? "Optional stream parser hint"
                    }
                >
                    <MenuItem value="">Default</MenuItem>
                    {streamProtocols.map((value) => (
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
                        onSystemPromptModeChange(e.target.value);
                    }}
                    error={!!errors.system_prompt_mode}
                    helperText={
                        errors.system_prompt_mode ??
                        "How provider expects system prompts"
                    }
                >
                    <MenuItem value="">Default</MenuItem>
                    {systemPromptModes.map((value) => (
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
                    onCredentialsChange(e.target.value);
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
                    onPricingProfileChange(e.target.value);
                }}
                error={!!errors.pricing_profile}
                helperText={
                    errors.pricing_profile ??
                    "Optional per-system pricing override"
                }
                slotProps={{ input: { sx: { fontFamily: "monospace" } } }}
                sx={{ mb: 2 }}
            />
        </>
    );
}
