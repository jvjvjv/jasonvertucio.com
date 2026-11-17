import DeleteOutlineIcon from "@mui/icons-material/DeleteOutline";
import EditIcon from "@mui/icons-material/Edit";
import ExpandLessIcon from "@mui/icons-material/ExpandLess";
import ExpandMoreIcon from "@mui/icons-material/ExpandMore";
import PersonAddAltOutlinedIcon from "@mui/icons-material/PersonAddAltOutlined";
import Alert from "@mui/material/Alert";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Collapse from "@mui/material/Collapse";
import Dialog from "@mui/material/Dialog";
import DialogActions from "@mui/material/DialogActions";
import DialogContent from "@mui/material/DialogContent";
import DialogTitle from "@mui/material/DialogTitle";
import Divider from "@mui/material/Divider";
import FormControlLabel from "@mui/material/FormControlLabel";
import IconButton from "@mui/material/IconButton";
import Switch from "@mui/material/Switch";
import TextField from "@mui/material/TextField";
import Tooltip from "@mui/material/Tooltip";
import Typography from "@mui/material/Typography";
import { useEffect, useRef, useState } from "react";

import useFollowUps from "./useFollowUps";

import type { ContactInput } from "./useFollowUps";
import type {
    ApplicationContact,
    ApplicationFollowUp,
    FollowUpState,
    StatusUpdate,
} from "@/types";
import type { SyntheticEvent } from "react";

import ConfirmDialog from "@/admin/components/ConfirmDialog";
import StatusChip from "@/admin/components/StatusChip";
import useConfirmDialog from "@/hooks/useConfirmDialog";
import { formatCalendarDate } from "@/utils/date";

/** Statuses a follow-up is drafted in; mirrors `ApplicationStatus::receivesFollowUps()`. */
const FOLLOW_UP_STATUSES = new Set(["applied", "interviewed", "offered"]);

const HISTORY_COLORS = {
    sending: "info",
    sent: "success",
    failed: "error",
} as const;

const EMPTY_CONTACT: ContactInput = {
    email: "",
    name: "",
    role: "",
    notes: "",
};

interface FollowUpsCardProps {
    applicationId: number;
    /** The application's current status, for the paused and stopped notes. */
    status: string;
    /** Watched so a new entry, which discards a waiting draft, refreshes this. */
    statusUpdates: StatusUpdate[];
    followUps: FollowUpState;
}

/**
 * The Discussion page's contacts and follow-ups: the per-application switch,
 * the contacts who receive follow-ups, the draft waiting for review and the
 * follow-ups already sent. Nothing is sent without Approve & send.
 */
export default function FollowUpsCard({
    applicationId,
    status,
    statusUpdates,
    followUps,
}: FollowUpsCardProps) {
    const {
        state,
        busy,
        error,
        clearError,
        setEnabled,
        saveContact,
        deleteContact,
        saveDraft,
        approve,
        discard,
        refresh,
    } = useFollowUps(applicationId, followUps);
    const { dialogProps, confirm } = useConfirmDialog();
    const [editingContact, setEditingContact] = useState<
        ApplicationContact | "new" | null
    >(null);

    // A new status entry discards a waiting draft on the server; re-read.
    const statusSignature = statusUpdates.map((entry) => entry.id).join(",");
    const lastSignature = useRef(statusSignature);
    useEffect(() => {
        if (lastSignature.current !== statusSignature) {
            lastSignature.current = statusSignature;
            refresh();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [statusSignature]);

    const recipients = state.contacts.map((contact) => contact.email);
    const pending = state.pending_follow_up;

    const confirmSend = (
        followUpId: number,
        beforeSend?: () => Promise<boolean>,
    ) => {
        confirm(
            `Send this follow-up to ${recipients.join(", ")}? It goes out from your mail address, with a blind copy to you.`,
            () => {
                void (async () => {
                    if (beforeSend && !(await beforeSend())) {
                        return;
                    }
                    await approve(followUpId);
                })();
            },
            {
                title: "Send follow-up",
                confirmLabel: "Send",
                confirmColor: "primary",
            },
        );
    };

    return (
        <Box>
            <Box
                sx={{
                    display: "flex",
                    flexWrap: "wrap",
                    alignItems: "center",
                    justifyContent: "space-between",
                    gap: 1,
                    mb: 1,
                }}
            >
                <Typography variant="subtitle2">
                    Contacts & Follow-ups
                </Typography>
                <FormControlLabel
                    control={
                        <Switch
                            checked={state.follow_ups_enabled}
                            disabled={busy === "switch"}
                            onChange={(event) => {
                                void setEnabled(event.target.checked);
                            }}
                        />
                    }
                    label="Weekly follow-ups"
                    labelPlacement="start"
                />
            </Box>

            {error ? (
                <Alert severity="error" onClose={clearError} sx={{ mb: 2 }}>
                    {error}
                </Alert>
            ) : null}

            {state.follow_ups_enabled && !state.follow_up_system_configured ? (
                <Alert severity="info" sx={{ mb: 2 }}>
                    No follow-up AI system is configured, so nothing will be
                    drafted. Make an active chat AI system the{" "}
                    <code>application-follow-up</code> default.
                </Alert>
            ) : null}

            <FollowUpSummary state={state} status={status} />

            <ContactList
                contacts={state.contacts}
                busy={busy}
                onAdd={() => {
                    setEditingContact("new");
                }}
                onEdit={setEditingContact}
                onDelete={(contact) => {
                    confirm(
                        `Remove ${contact.name ?? contact.email} from this application? Follow-ups already sent to them keep their record.`,
                        () => {
                            void deleteContact(contact.id);
                        },
                        { title: "Remove contact", confirmLabel: "Remove" },
                    );
                }}
            />

            {pending ? (
                <PendingDraft
                    // Remount on a new draft so its edits start clean.
                    key={pending.id}
                    followUp={pending}
                    busy={busy}
                    canSend={recipients.length > 0}
                    onSave={(subject, body) =>
                        saveDraft(pending.id, subject, body)
                    }
                    onApprove={(beforeSend) => {
                        confirmSend(pending.id, beforeSend);
                    }}
                    onDiscard={() => {
                        confirm(
                            "Discard this draft? Nothing is sent, and a new one may be drafted on the usual schedule.",
                            () => {
                                void discard(pending.id);
                            },
                            { title: "Discard draft", confirmLabel: "Discard" },
                        );
                    }}
                />
            ) : null}

            <FollowUpHistory
                history={state.follow_up_history}
                busy={busy}
                canSend={recipients.length > 0}
                onRetry={(followUp) => {
                    confirmSend(followUp.id);
                }}
            />

            <ContactDialog
                // Remount per contact so the fields start from it.
                key={
                    editingContact === null
                        ? "closed"
                        : editingContact === "new"
                          ? "new"
                          : editingContact.id
                }
                contact={editingContact}
                isSaving={busy === "contact"}
                onSave={saveContact}
                onClose={() => {
                    setEditingContact(null);
                }}
            />
            <ConfirmDialog {...dialogProps} />
        </Box>
    );
}

/**
 * How many follow-ups have gone out in this status stage, and why none will
 * be drafted when that is the case.
 */
function FollowUpSummary({
    state,
    status,
}: {
    state: FollowUpState;
    status: string;
}) {
    const capReached = state.sent_since_status_change >= state.max_per_status;

    let note: string | null = null;
    if (!state.follow_ups_enabled) {
        note = "Follow-ups are off for this application.";
    } else if (status === "interviewing") {
        note =
            "Paused while interviewing. They resume after the next status entry.";
    } else if (!FOLLOW_UP_STATUSES.has(status)) {
        note =
            "Follow-ups are only drafted while applied, interviewed or offered.";
    } else if (capReached) {
        note = "No more will be drafted until the status changes.";
    } else if (state.contacts.length === 0) {
        note = "Add a contact to receive follow-ups.";
    }

    return (
        <Box sx={{ mb: 2 }}>
            <Typography variant="body2">
                {state.sent_since_status_change} of {state.max_per_status} sent
                since the last status change
            </Typography>
            <Typography variant="caption" color="text.secondary">
                {note ??
                    `A draft is written for your review after ${state.cadence_days} days with no news.`}
            </Typography>
        </Box>
    );
}

function ContactList({
    contacts,
    busy,
    onAdd,
    onEdit,
    onDelete,
}: {
    contacts: ApplicationContact[];
    busy: string | null;
    onAdd: () => void;
    onEdit: (contact: ApplicationContact) => void;
    onDelete: (contact: ApplicationContact) => void;
}) {
    return (
        <Box sx={{ mb: 2 }}>
            {contacts.length === 0 ? (
                <Typography
                    variant="body2"
                    color="text.secondary"
                    sx={{ mb: 1 }}
                >
                    No contacts yet.
                </Typography>
            ) : (
                <Box
                    component="ul"
                    sx={{ listStyle: "none", m: 0, p: 0, mb: 1 }}
                >
                    {contacts.map((contact, index) => (
                        <Box component="li" key={contact.id}>
                            {index > 0 && <Divider />}
                            <Box
                                sx={{
                                    display: "flex",
                                    alignItems: "center",
                                    justifyContent: "space-between",
                                    gap: 1,
                                    py: 1,
                                }}
                            >
                                <Box sx={{ minWidth: 0 }}>
                                    <Typography
                                        variant="body2"
                                        fontWeight={600}
                                    >
                                        {contact.name ?? contact.email}
                                        {contact.role ? (
                                            <Typography
                                                component="span"
                                                variant="body2"
                                                color="text.secondary"
                                            >
                                                {" "}
                                                ({contact.role})
                                            </Typography>
                                        ) : null}
                                    </Typography>
                                    {contact.name ? (
                                        <Typography
                                            variant="caption"
                                            color="text.secondary"
                                            sx={{ overflowWrap: "anywhere" }}
                                        >
                                            {contact.email}
                                        </Typography>
                                    ) : null}
                                    {contact.notes ? (
                                        <Typography
                                            variant="caption"
                                            color="text.secondary"
                                            component="p"
                                            sx={{ m: 0 }}
                                        >
                                            {contact.notes}
                                        </Typography>
                                    ) : null}
                                </Box>
                                <Box sx={{ display: "flex", flexShrink: 0 }}>
                                    <IconButton
                                        size="small"
                                        aria-label={`Edit ${contact.name ?? contact.email}`}
                                        onClick={() => {
                                            onEdit(contact);
                                        }}
                                    >
                                        <EditIcon fontSize="small" />
                                    </IconButton>
                                    <IconButton
                                        size="small"
                                        aria-label={`Remove ${contact.name ?? contact.email}`}
                                        disabled={
                                            busy === `contact-${contact.id}`
                                        }
                                        onClick={() => {
                                            onDelete(contact);
                                        }}
                                    >
                                        <DeleteOutlineIcon fontSize="small" />
                                    </IconButton>
                                </Box>
                            </Box>
                        </Box>
                    ))}
                </Box>
            )}
            <Button
                size="small"
                variant="outlined"
                startIcon={<PersonAddAltOutlinedIcon />}
                onClick={onAdd}
            >
                Add contact
            </Button>
        </Box>
    );
}

function ContactDialog({
    contact,
    isSaving,
    onSave,
    onClose,
}: {
    contact: ApplicationContact | "new" | null;
    isSaving: boolean;
    onSave: (
        contactId: number | null,
        input: ContactInput,
    ) => Promise<string | null>;
    onClose: () => void;
}) {
    const existing = contact !== null && contact !== "new" ? contact : null;
    const [input, setInput] = useState<ContactInput>(
        existing
            ? {
                  email: existing.email,
                  name: existing.name ?? "",
                  role: existing.role ?? "",
                  notes: existing.notes ?? "",
              }
            : EMPTY_CONTACT,
    );
    const [error, setError] = useState<string | null>(null);

    const field = (key: keyof ContactInput) => ({
        value: input[key],
        onChange: (event: { target: { value: string } }) => {
            setInput((current) => ({ ...current, [key]: event.target.value }));
        },
    });

    const handleSubmit = async (event: SyntheticEvent) => {
        event.preventDefault();
        const failure = await onSave(existing?.id ?? null, input);
        if (failure) {
            setError(failure);
            return;
        }
        onClose();
    };

    return (
        <Dialog
            open={contact !== null}
            onClose={onClose}
            maxWidth="xs"
            fullWidth
        >
            <form
                onSubmit={(event) => {
                    void handleSubmit(event);
                }}
            >
                <DialogTitle>
                    {existing ? "Edit contact" : "Add contact"}
                </DialogTitle>
                <DialogContent
                    sx={{ display: "flex", flexDirection: "column", gap: 2 }}
                >
                    {error ? <Alert severity="error">{error}</Alert> : null}
                    <TextField
                        label="Email"
                        type="email"
                        size="small"
                        required
                        autoFocus
                        fullWidth
                        sx={{ mt: 1 }}
                        {...field("email")}
                    />
                    <TextField
                        label="Name"
                        size="small"
                        fullWidth
                        {...field("name")}
                    />
                    <TextField
                        label="Role"
                        placeholder="Recruiter"
                        size="small"
                        fullWidth
                        {...field("role")}
                    />
                    <TextField
                        label="Notes"
                        size="small"
                        fullWidth
                        multiline
                        minRows={2}
                        {...field("notes")}
                    />
                </DialogContent>
                <DialogActions>
                    <Button type="button" color="inherit" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button
                        type="submit"
                        variant="contained"
                        disabled={isSaving}
                    >
                        {isSaving ? "Saving..." : "Save"}
                    </Button>
                </DialogActions>
            </form>
        </Dialog>
    );
}

function PendingDraft({
    followUp,
    busy,
    canSend,
    onSave,
    onApprove,
    onDiscard,
}: {
    followUp: ApplicationFollowUp;
    busy: string | null;
    canSend: boolean;
    onSave: (subject: string, body: string) => Promise<boolean>;
    /** `beforeSend` saves unsent edits first; the send stops if it fails. */
    onApprove: (beforeSend?: () => Promise<boolean>) => void;
    onDiscard: () => void;
}) {
    const [subject, setSubject] = useState(followUp.subject);
    const [body, setBody] = useState(followUp.body);
    const isDirty = subject !== followUp.subject || body !== followUp.body;
    const isBlank = subject.trim() === "" || body.trim() === "";
    const isBusy = busy !== null;

    return (
        <Box
            sx={{
                mb: 2,
                p: 2,
                border: 1,
                borderColor: "primary.main",
                borderRadius: 1,
            }}
        >
            <Box
                sx={{
                    display: "flex",
                    flexWrap: "wrap",
                    alignItems: "baseline",
                    justifyContent: "space-between",
                    gap: 1,
                    mb: 2,
                }}
            >
                <Typography variant="subtitle2">
                    Follow-up ready for review
                </Typography>
                {followUp.drafted_at ? (
                    <Typography variant="caption" color="text.secondary">
                        Drafted {formatCalendarDate(followUp.drafted_at)}
                    </Typography>
                ) : null}
            </Box>
            <TextField
                label="Subject"
                size="small"
                fullWidth
                value={subject}
                onChange={(event) => {
                    setSubject(event.target.value);
                }}
                slotProps={{ htmlInput: { maxLength: 191 } }}
                sx={{ mb: 2 }}
            />
            <TextField
                label="Body"
                size="small"
                fullWidth
                multiline
                minRows={6}
                value={body}
                onChange={(event) => {
                    setBody(event.target.value);
                }}
                helperText="Sent as plain text, exactly as written."
            />
            {!canSend ? (
                <Alert severity="warning" sx={{ mt: 2 }}>
                    Add a contact before sending this follow-up.
                </Alert>
            ) : null}
            <Box sx={{ display: "flex", flexWrap: "wrap", gap: 1, mt: 2 }}>
                <Button
                    variant="contained"
                    disabled={!canSend || isBlank || isBusy}
                    onClick={() => {
                        onApprove(
                            isDirty ? () => onSave(subject, body) : undefined,
                        );
                    }}
                >
                    {busy === `approve-${followUp.id}`
                        ? "Sending..."
                        : "Approve & send"}
                </Button>
                <Button
                    variant="outlined"
                    disabled={!isDirty || isBlank || isBusy}
                    onClick={() => {
                        void onSave(subject, body);
                    }}
                >
                    {busy === "save" ? "Saving..." : "Save draft"}
                </Button>
                <Button color="error" disabled={isBusy} onClick={onDiscard}>
                    Discard
                </Button>
            </Box>
        </Box>
    );
}

function FollowUpHistory({
    history,
    busy,
    canSend,
    onRetry,
}: {
    history: ApplicationFollowUp[];
    busy: string | null;
    canSend: boolean;
    onRetry: (followUp: ApplicationFollowUp) => void;
}) {
    const [openId, setOpenId] = useState<number | null>(null);

    if (history.length === 0) {
        return null;
    }

    return (
        <Box>
            <Typography variant="subtitle2" gutterBottom>
                Follow-up history
            </Typography>
            <Box component="ul" sx={{ listStyle: "none", m: 0, p: 0 }}>
                {history.map((followUp, index) => {
                    const isOpen = openId === followUp.id;
                    const date = followUp.sent_at ?? followUp.drafted_at;

                    return (
                        <Box component="li" key={followUp.id}>
                            {index > 0 && <Divider />}
                            <Box
                                sx={{
                                    display: "grid",
                                    gridTemplateColumns: {
                                        xs: "auto 1fr auto",
                                        sm: "110px 80px 1fr auto",
                                    },
                                    alignItems: "center",
                                    columnGap: 2,
                                    rowGap: 0.5,
                                    py: 1,
                                }}
                            >
                                <Typography
                                    variant="body2"
                                    color="text.secondary"
                                >
                                    {date ? formatCalendarDate(date) : "—"}
                                </Typography>
                                <Box>
                                    <StatusChip
                                        status={followUp.status}
                                        colorMap={HISTORY_COLORS}
                                    />
                                </Box>
                                <Typography
                                    variant="body2"
                                    sx={{
                                        gridColumn: {
                                            xs: "1 / -1",
                                            sm: "auto",
                                        },
                                        gridRow: { xs: 2, sm: "auto" },
                                        overflowWrap: "anywhere",
                                    }}
                                >
                                    {followUp.subject}
                                </Typography>
                                <Tooltip
                                    title={isOpen ? "Hide email" : "Show email"}
                                >
                                    <IconButton
                                        size="small"
                                        aria-expanded={isOpen}
                                        aria-label={`${isOpen ? "Hide" : "Show"} “${followUp.subject}”`}
                                        onClick={() => {
                                            setOpenId(
                                                isOpen ? null : followUp.id,
                                            );
                                        }}
                                        sx={{
                                            gridRow: { xs: 1, sm: "auto" },
                                            gridColumn: { xs: 3, sm: "auto" },
                                        }}
                                    >
                                        {isOpen ? (
                                            <ExpandLessIcon fontSize="small" />
                                        ) : (
                                            <ExpandMoreIcon fontSize="small" />
                                        )}
                                    </IconButton>
                                </Tooltip>
                            </Box>
                            {followUp.status === "failed" ? (
                                <Alert
                                    severity="error"
                                    sx={{ mb: 1 }}
                                    action={
                                        <Button
                                            color="inherit"
                                            size="small"
                                            disabled={!canSend || busy !== null}
                                            onClick={() => {
                                                onRetry(followUp);
                                            }}
                                        >
                                            {busy === `approve-${followUp.id}`
                                                ? "Sending..."
                                                : "Retry"}
                                        </Button>
                                    }
                                >
                                    Not sent:{" "}
                                    {followUp.failure_reason ??
                                        "the mail server refused it."}
                                </Alert>
                            ) : null}
                            <Collapse in={isOpen} unmountOnExit>
                                <Box sx={{ pb: 1.5 }}>
                                    {followUp.recipients ? (
                                        <Typography
                                            variant="caption"
                                            color="text.secondary"
                                            component="p"
                                            sx={{
                                                m: 0,
                                                mb: 1,
                                                overflowWrap: "anywhere",
                                            }}
                                        >
                                            To {followUp.recipients.join(", ")}
                                        </Typography>
                                    ) : null}
                                    <Typography
                                        variant="body2"
                                        sx={{
                                            whiteSpace: "pre-wrap",
                                            overflowWrap: "anywhere",
                                        }}
                                    >
                                        {followUp.body}
                                    </Typography>
                                </Box>
                            </Collapse>
                        </Box>
                    );
                })}
            </Box>
        </Box>
    );
}
