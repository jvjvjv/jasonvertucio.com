import Box from "@mui/material/Box";
import Checkbox from "@mui/material/Checkbox";
import FormControlLabel from "@mui/material/FormControlLabel";

interface ModelCapabilitiesCheckboxesProps {
    supportsTools: boolean;
    supportsJsonMode: boolean;
    enableThinking: boolean;
    isLocalEndpoint: boolean;
    isActive: boolean;
    onSupportsToolsChange: (_checked: boolean) => void;
    onSupportsJsonModeChange: (_checked: boolean) => void;
    onEnableThinkingChange: (_checked: boolean) => void;
    onIsLocalEndpointChange: (_checked: boolean) => void;
    onIsActiveChange: (_checked: boolean) => void;
}

export default function ModelCapabilitiesCheckboxes({
    supportsTools,
    supportsJsonMode,
    enableThinking,
    isLocalEndpoint,
    isActive,
    onSupportsToolsChange,
    onSupportsJsonModeChange,
    onEnableThinkingChange,
    onIsLocalEndpointChange,
    onIsActiveChange,
}: ModelCapabilitiesCheckboxesProps) {
    return (
        <>
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
                            checked={supportsTools}
                            onChange={(e) => {
                                onSupportsToolsChange(e.target.checked);
                            }}
                        />
                    }
                    label="Supports Tools"
                />

                <FormControlLabel
                    control={
                        <Checkbox
                            checked={supportsJsonMode}
                            onChange={(e) => {
                                onSupportsJsonModeChange(e.target.checked);
                            }}
                        />
                    }
                    label="Supports JSON Mode"
                />

                <FormControlLabel
                    control={
                        <Checkbox
                            checked={enableThinking}
                            onChange={(e) => {
                                onEnableThinkingChange(e.target.checked);
                            }}
                        />
                    }
                    label="Enable Thinking (reasoning models)"
                />

                <FormControlLabel
                    control={
                        <Checkbox
                            checked={isLocalEndpoint}
                            onChange={(e) => {
                                onIsLocalEndpointChange(e.target.checked);
                            }}
                        />
                    }
                    label="Local Endpoint"
                />
            </Box>

            <FormControlLabel
                control={
                    <Checkbox
                        checked={isActive}
                        onChange={(e) => {
                            onIsActiveChange(e.target.checked);
                        }}
                    />
                }
                label="Active"
                sx={{ mb: 2 }}
            />
        </>
    );
}
