import Alert from "@mui/material/Alert";
import MenuItem from "@mui/material/MenuItem";
import TextField from "@mui/material/TextField";

import type { AiSystem } from "@/types";

interface AISystemWarmupDetectorProps {
    systems: Pick<AiSystem, "id" | "name" | "model">[];
    aiSystemId: number | "";
    modelState: "idle" | "checking" | "warming" | "ready" | "unavailable";
    onAiSystemChange: (_value: number) => void;
}

export default function AISystemWarmupDetector({
    systems,
    aiSystemId,
    modelState,
    onAiSystemChange,
}: AISystemWarmupDetectorProps) {
    return (
        <>
            <TextField
                label="AI System"
                select
                required
                size="small"
                fullWidth
                value={aiSystemId}
                onChange={(e) => {
                    onAiSystemChange(Number(e.target.value));
                }}
                sx={{ mb: modelState === "idle" ? 3 : 1 }}
            >
                {systems.map((system) => (
                    <MenuItem key={system.id} value={system.id}>
                        {system.name} ({system.model})
                    </MenuItem>
                ))}
            </TextField>

            {modelState === "checking" && (
                <Alert severity="info" sx={{ mb: 3 }}>
                    Checking model status...
                </Alert>
            )}
            {modelState === "warming" && (
                <Alert severity="info" sx={{ mb: 3 }}>
                    Loading model in the background. This may take a moment.
                </Alert>
            )}
            {modelState === "ready" && (
                <Alert severity="success" sx={{ mb: 3 }}>
                    Model is ready.
                </Alert>
            )}
            {modelState === "unavailable" && (
                <Alert severity="warning" sx={{ mb: 3 }}>
                    Model may not be available. You can still start the session.
                </Alert>
            )}
        </>
    );
}
