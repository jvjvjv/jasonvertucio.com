import Alert from "@mui/material/Alert";
import Box from "@mui/material/Box";
import Stack from "@mui/material/Stack";

import type { ModelStatus } from "@/components/ChatInterface";

interface ModelStatusDisplayProps {
    isCheckingModelStatus: boolean;
    isWarmingModel: boolean;
    loadingMessage: string;
    modelStatus: ModelStatus | null;
    error: string;
}

export default function ModelStatusDisplay({
    isCheckingModelStatus,
    isWarmingModel,
    loadingMessage,
    modelStatus,
    error,
}: ModelStatusDisplayProps) {
    const showChecking = isCheckingModelStatus;
    const showWarming =
        isWarmingModel || (!!loadingMessage && !isCheckingModelStatus);
    const showReady =
        modelStatus?.state === "loaded" &&
        !isWarmingModel &&
        !isCheckingModelStatus;
    const showNotLoaded =
        modelStatus?.state === "not_loaded" && !isWarmingModel;
    const showError = !!error;

    if (
        !showChecking &&
        !showWarming &&
        !showReady &&
        !showNotLoaded &&
        !showError
    ) {
        return null;
    }

    return (
        <Box sx={{ px: { xs: 1.5, md: 3 }, pt: 1.5 }}>
            <Stack spacing={1}>
                {showChecking ? (
                    <Alert severity="info">Checking model status...</Alert>
                ) : null}
                {showWarming ? (
                    <Alert severity="info">
                        {loadingMessage ||
                            "Loading model. This can take a little while..."}
                    </Alert>
                ) : null}
                {showReady ? (
                    <Alert severity="success">Model is ready.</Alert>
                ) : null}
                {showNotLoaded ? (
                    <Alert severity="warning">{modelStatus.message}</Alert>
                ) : null}
                {showError ? <Alert severity="error">{error}</Alert> : null}
            </Stack>
        </Box>
    );
}
