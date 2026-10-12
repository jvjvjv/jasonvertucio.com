import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import MenuItem from "@mui/material/MenuItem";
import TextField from "@mui/material/TextField";

interface JSONConfigEditorProps {
    data: {
        config: string;
        credentials: string;
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
    hasCredentials: boolean;
    clearCredentials: boolean;
    onClearCredentialsChange: (_value: boolean) => void;
    onConfigChange: (_value: string) => void;
    onCredentialsChange: (_value: string) => void;
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
    hasCredentials,
    clearCredentials,
    onClearCredentialsChange,
    onConfigChange,
    onCredentialsChange,
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

            <Box sx={{ mb: 2 }}>
                <TextField
                    label="Credentials (JSON)"
                    size="small"
                    fullWidth
                    multiline
                    rows={4}
                    value={data.credentials}
                    disabled={clearCredentials}
                    onChange={(e) => {
                        onCredentialsChange(e.target.value);
                        if (e.target.value) {
                            onClearCredentialsChange(false);
                        }
                    }}
                    error={!!errors.credentials}
                    placeholder={hasCredentials ? "Credentials set" : undefined}
                    helperText={
                        errors.credentials ??
                        (clearCredentials
                            ? "The stored credentials will be removed when you save."
                            : hasCredentials
                              ? "Credentials are set. Leave blank to keep them, or enter new JSON to replace them."
                              : "Optional encrypted credential payload for provider-specific keys")
                    }
                    slotProps={{ input: { sx: { fontFamily: "monospace" } } }}
                />
                {hasCredentials && (
                    <Button
                        size="small"
                        color={clearCredentials ? "inherit" : "error"}
                        onClick={() => {
                            onCredentialsChange("");
                            onClearCredentialsChange(!clearCredentials);
                        }}
                        sx={{ mt: 0.5 }}
                    >
                        {clearCredentials
                            ? "Keep stored credentials"
                            : "Remove credentials"}
                    </Button>
                )}
            </Box>
        </>
    );
}
