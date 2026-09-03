import Box from "@mui/material/Box";
import Checkbox from "@mui/material/Checkbox";
import FormControlLabel from "@mui/material/FormControlLabel";
import Tooltip from "@mui/material/Tooltip";

interface ModelCapabilitiesCheckboxesProps {
    supportsTools: boolean;
    disableSupportsTools?: boolean;
    supportsJsonMode: boolean;
    enableThinking: boolean;
    disableEnableThinking?: boolean;
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
    disableSupportsTools = false,
    supportsJsonMode,
    enableThinking,
    disableEnableThinking = false,
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
                <Tooltip
                    title={
                        disableSupportsTools
                            ? "The selected model does not report support for tools"
                            : ""
                    }
                >
                    <FormControlLabel
                        control={
                            <Checkbox
                                checked={supportsTools && !disableSupportsTools}
                                disabled={disableSupportsTools}
                                onChange={(e) => {
                                    onSupportsToolsChange(e.target.checked);
                                }}
                            />
                        }
                        label="Supports Tools"
                    />
                </Tooltip>

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

                <Tooltip
                    title={
                        disableEnableThinking
                            ? "The selected model does not report support for reasoning"
                            : ""
                    }
                >
                    <FormControlLabel
                        control={
                            <Checkbox
                                checked={
                                    enableThinking && !disableEnableThinking
                                }
                                disabled={disableEnableThinking}
                                onChange={(e) => {
                                    onEnableThinkingChange(e.target.checked);
                                }}
                            />
                        }
                        label="Enable Thinking (reasoning models)"
                    />
                </Tooltip>

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
