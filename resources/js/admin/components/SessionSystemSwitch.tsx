import SwapHorizIcon from "@mui/icons-material/SwapHoriz";
import Alert from "@mui/material/Alert";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import CircularProgress from "@mui/material/CircularProgress";
import Dialog from "@mui/material/Dialog";
import DialogActions from "@mui/material/DialogActions";
import DialogContent from "@mui/material/DialogContent";
import DialogContentText from "@mui/material/DialogContentText";
import DialogTitle from "@mui/material/DialogTitle";
import MenuItem from "@mui/material/MenuItem";
import TextField from "@mui/material/TextField";
import Typography from "@mui/material/Typography";
import { useState } from "react";

import type { AlertColor } from "@mui/material/Alert";

import { api, apiErrorMessage, networkErrorMessage } from "@/api";

type ContextSource = "configured" | "model_max" | "unknown";

interface SwitchableSystem {
    id: number;
    name: string;
    model: string;
    context: { tokens: number | null; source: ContextSource };
}

interface SwitchOptions {
    current: { id: number; name: string; model: string } | null;
    systems: SwitchableSystem[];
    estimated_tokens: number;
}

interface SessionSystemSwitchProps {
    /** GET for the choices, POST to switch. */
    url: string;
    currentName: string | null;
    canSwitch: boolean;
    /** True while the page is streaming a turn. */
    disabled?: boolean;
    onSwitched: () => void;
}

/** At this share of a configured context, the transcript is near the limit. */
const NEAR_LIMIT = 0.8;

const formatTokens = (tokens: number): string => tokens.toLocaleString();

/**
 * What the size warning says for a chosen system. A model maximum is only
 * ever reported as a figure, never as confirmation that the transcript fits:
 * a local model is usually loaded with far less.
 */
export function sizeWarning(
    estimate: number,
    context: SwitchableSystem["context"],
): { severity: AlertColor; text: string } {
    const size = `This conversation is about ${formatTokens(estimate)} tokens`;

    if (context.source === "configured" && context.tokens !== null) {
        const limit = formatTokens(context.tokens);

        if (estimate >= context.tokens) {
            return {
                severity: "error",
                text: `${size}. This system holds ${limit}, so the next reply is likely to fail.`,
            };
        }

        if (estimate >= context.tokens * NEAR_LIMIT) {
            return {
                severity: "warning",
                text: `${size}, near this system's ${limit} limit.`,
            };
        }

        return {
            severity: "info",
            text: `${size}. This system holds ${limit}.`,
        };
    }

    if (context.source === "model_max" && context.tokens !== null) {
        return {
            severity: "info",
            text: `${size}. The model supports up to ${formatTokens(context.tokens)}, but the size it is loaded with is unknown.`,
        };
    }

    return {
        severity: "info",
        text: `${size}. This system's context size is not set.`,
    };
}

/**
 * The current AI system of a session, and a way to move the session to
 * another system whose prompt fits the same feature. The whole transcript
 * goes to the new system on the next message, so the dialog says how large
 * it is before the switch is confirmed.
 */
export default function SessionSystemSwitch({
    url,
    currentName,
    canSwitch,
    disabled = false,
    onSwitched,
}: SessionSystemSwitchProps) {
    const [open, setOpen] = useState(false);
    const [options, setOptions] = useState<SwitchOptions | null>(null);
    const [selectedId, setSelectedId] = useState<number | "">("");
    const [isLoading, setIsLoading] = useState(false);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const selected =
        options?.systems.find((system) => system.id === selectedId) ?? null;

    const openDialog = async () => {
        setOpen(true);
        setOptions(null);
        setSelectedId("");
        setError(null);
        setIsLoading(true);
        try {
            setOptions(await api.get<SwitchOptions>(url));
        } catch (caught) {
            setError(
                apiErrorMessage(
                    caught,
                    "Could not load the AI systems.",
                    networkErrorMessage(caught),
                ),
            );
        } finally {
            setIsLoading(false);
        }
    };

    const confirm = async () => {
        if (selected === null) {
            return;
        }
        setIsSubmitting(true);
        setError(null);
        try {
            await api.post(url, { ai_system_id: selected.id });
            setOpen(false);
            onSwitched();
        } catch (caught) {
            setError(
                apiErrorMessage(
                    caught,
                    "Could not switch the AI system.",
                    networkErrorMessage(caught),
                ),
            );
        } finally {
            setIsSubmitting(false);
        }
    };

    if (currentName === null && !canSwitch) {
        return null;
    }

    const warning =
        selected !== null && options !== null
            ? sizeWarning(options.estimated_tokens, selected.context)
            : null;

    return (
        <>
            <Box
                sx={{
                    display: "flex",
                    alignItems: "center",
                    gap: 0.5,
                    minWidth: 0,
                }}
            >
                {currentName !== null ? (
                    <Typography
                        variant="caption"
                        color="text.secondary"
                        noWrap
                        title={currentName}
                    >
                        AI: {currentName}
                    </Typography>
                ) : null}
                {canSwitch ? (
                    <Button
                        size="small"
                        startIcon={<SwapHorizIcon fontSize="small" />}
                        disabled={disabled}
                        title={
                            disabled
                                ? "Wait for the reply to finish before switching"
                                : "Move this session to another AI system"
                        }
                        onClick={() => {
                            void openDialog();
                        }}
                        sx={{ flexShrink: 0 }}
                    >
                        Switch
                    </Button>
                ) : null}
            </Box>

            <Dialog
                open={open}
                onClose={
                    isSubmitting
                        ? undefined
                        : () => {
                              setOpen(false);
                          }
                }
                maxWidth="sm"
                fullWidth
            >
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        void confirm();
                    }}
                >
                    <DialogTitle>Switch AI system</DialogTitle>
                    <DialogContent>
                        <DialogContentText sx={{ mb: 2 }}>
                            Every later message in this session goes to the
                            system you choose. The session keeps its
                            instructions, and the whole conversation is sent
                            with your next message.
                        </DialogContentText>

                        {isLoading ? (
                            <Box
                                sx={{
                                    display: "flex",
                                    justifyContent: "center",
                                    py: 2,
                                }}
                            >
                                <CircularProgress
                                    size={24}
                                    aria-label="Loading AI systems"
                                />
                            </Box>
                        ) : null}

                        {options !== null && options.systems.length === 0 ? (
                            <Alert severity="info">
                                No other AI system can run this session. Assign
                                this feature&rsquo;s prompt to another system to
                                make it available here.
                            </Alert>
                        ) : null}

                        {options !== null && options.systems.length > 0 ? (
                            <TextField
                                select
                                fullWidth
                                size="small"
                                label="Switch to"
                                value={selectedId}
                                onChange={(e) => {
                                    setSelectedId(Number(e.target.value));
                                }}
                                helperText={
                                    options.current !== null
                                        ? `Currently ${options.current.name}`
                                        : undefined
                                }
                            >
                                {options.systems.map((system) => (
                                    <MenuItem key={system.id} value={system.id}>
                                        {system.name}
                                        <Typography
                                            component="span"
                                            variant="caption"
                                            color="text.secondary"
                                            sx={{ ml: 1 }}
                                        >
                                            {system.model}
                                        </Typography>
                                    </MenuItem>
                                ))}
                            </TextField>
                        ) : null}

                        {warning !== null ? (
                            <Alert severity={warning.severity} sx={{ mt: 2 }}>
                                {warning.text} Results of earlier tool calls are
                                not carried over; the new system sees only the
                                chat text.
                            </Alert>
                        ) : null}

                        {error ? (
                            <Alert severity="error" sx={{ mt: 2 }}>
                                {error}
                            </Alert>
                        ) : null}
                    </DialogContent>
                    <DialogActions>
                        <Button
                            type="button"
                            onClick={() => {
                                setOpen(false);
                            }}
                            disabled={isSubmitting}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            variant="contained"
                            disabled={selected === null || isSubmitting}
                        >
                            Switch
                        </Button>
                    </DialogActions>
                </form>
            </Dialog>
        </>
    );
}
