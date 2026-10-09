import { router } from "@inertiajs/react";
import NotStartedIcon from "@mui/icons-material/NotStarted";
import Alert from "@mui/material/Alert";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Card from "@mui/material/Card";
import CardContent from "@mui/material/CardContent";
import Typography from "@mui/material/Typography";
import { useState } from "react";

import AISystemWarmupDetector from "./AISystemWarmupDetector";

import type { AiSystem } from "@/types";

import { api, apiErrorMessage, networkErrorMessage } from "@/api";

interface BeginAnalysisResponse {
    success?: boolean;
    conversation_id?: number;
    redirect?: string;
    message?: string;
}

interface BeginAnalysisPanelProps {
    applicationId: number;
    systems: Pick<AiSystem, "id" | "name" | "model">[];
    defaultSystemId: number | null;
}

/**
 * Shown in place of the transcript for an application that has no AI session
 * (one recorded through "I Applied"). Attaches a session to the same
 * application; its status and history are left as they are.
 */
export default function BeginAnalysisPanel({
    applicationId,
    systems,
    defaultSystemId,
}: BeginAnalysisPanelProps) {
    const [aiSystemId, setAiSystemId] = useState<number | "">(
        defaultSystemId ?? "",
    );
    const [isStarting, setIsStarting] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const handleBegin = async () => {
        setIsStarting(true);
        setError(null);

        try {
            const data = await api.post<BeginAnalysisResponse>(
                `/api/admin/resume/applications/${applicationId}/analysis`,
                { ai_system_id: aiSystemId === "" ? null : aiSystemId },
            );

            // The session now exists; visiting the Discussion page afresh
            // mounts the chat, which starts the analysis itself.
            router.visit(
                data.redirect ?? `/admin/resume/applications/${applicationId}`,
            );
        } catch (err) {
            setError(
                apiErrorMessage(
                    err,
                    "Failed to begin analysis.",
                    networkErrorMessage(err),
                ),
            );
            setIsStarting(false);
        }
    };

    return (
        <Card variant="outlined">
            <CardContent>
                <Typography variant="h6" gutterBottom>
                    No AI session yet
                </Typography>
                <Typography
                    variant="body2"
                    color="text.secondary"
                    sx={{ mb: 2 }}
                >
                    This application was recorded without an analysis. Begin one
                    to assess the fit, tailor a resume or draft a cover letter.
                    Its status and history stay as they are.
                </Typography>

                {error ? (
                    <Alert severity="error" sx={{ mb: 2 }}>
                        {error}
                    </Alert>
                ) : null}

                <Box
                    component="form"
                    onSubmit={(e) => {
                        e.preventDefault();
                        void handleBegin();
                    }}
                >
                    <AISystemWarmupDetector
                        systems={systems}
                        aiSystemId={aiSystemId}
                        modelState="idle"
                        onAiSystemChange={setAiSystemId}
                    />
                    <Button
                        type="submit"
                        variant="contained"
                        startIcon={<NotStartedIcon />}
                        disabled={isStarting || aiSystemId === ""}
                    >
                        {isStarting ? "Starting..." : "Begin Analysis"}
                    </Button>
                </Box>
            </CardContent>
        </Card>
    );
}
