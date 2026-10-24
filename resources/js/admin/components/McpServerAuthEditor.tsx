import AddIcon from "@mui/icons-material/Add";
import DeleteOutlineIcon from "@mui/icons-material/DeleteOutline";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import IconButton from "@mui/material/IconButton";
import MenuItem from "@mui/material/MenuItem";
import TextField from "@mui/material/TextField";
import Typography from "@mui/material/Typography";

import type { McpServerAuthType } from "@/types";

export interface HeaderRow {
    name: string;
    value: string;
}

/** The editable form of a server's auth; see toAuthPayload() for what is sent. */
export interface McpServerAuthDraft {
    type: McpServerAuthType;
    token: string;
    headers: HeaderRow[];
    client_id: string;
    client_secret: string;
    scope: string;
    token_endpoint: string;
}

export function emptyAuthDraft(
    type: McpServerAuthType = "none",
): McpServerAuthDraft {
    return {
        type,
        token: "",
        headers: [{ name: "", value: "" }],
        client_id: "",
        client_secret: "",
        scope: "",
        token_endpoint: "",
    };
}

/**
 * The `auth` object the server expects for the draft's type, with blank
 * optional fields and empty header rows dropped.
 */
export function toAuthPayload(draft: McpServerAuthDraft): {
    [key: string]: unknown;
} {
    switch (draft.type) {
        case "bearer":
            return { type: "bearer", token: draft.token };
        case "headers":
            return {
                type: "headers",
                headers: Object.fromEntries(
                    draft.headers
                        .filter((row) => row.name.trim() !== "")
                        .map((row) => [row.name.trim(), row.value]),
                ),
            };
        case "client_credentials": {
            const payload: { [key: string]: string } = {
                type: "client_credentials",
                client_id: draft.client_id,
            };

            for (const key of [
                "client_secret",
                "scope",
                "token_endpoint",
            ] as const) {
                if (draft[key].trim() !== "") {
                    payload[key] = draft[key].trim();
                }
            }

            return payload;
        }
        case "none":
            return { type: "none" };
    }
}

const AUTH_TYPE_LABELS: { [K in McpServerAuthType]: string } = {
    none: "None",
    bearer: "Bearer token",
    headers: "Custom headers",
    client_credentials: "OAuth client credentials",
};

interface McpServerAuthEditorProps {
    value: McpServerAuthDraft;
    onChange: (_value: McpServerAuthDraft) => void;
    authTypes: McpServerAuthType[];
    error?: string;
    /**
     * On edit: the stored auth type. Stored credentials are never sent to the
     * browser, so any change here replaces them in full.
     */
    storedType?: McpServerAuthType;
}

export default function McpServerAuthEditor({
    value,
    onChange,
    authTypes,
    error,
    storedType,
}: McpServerAuthEditorProps) {
    const update = (patch: Partial<McpServerAuthDraft>) => {
        onChange({ ...value, ...patch });
    };

    const updateHeader = (index: number, patch: Partial<HeaderRow>) => {
        update({
            headers: value.headers.map((row, i) =>
                i === index ? { ...row, ...patch } : row,
            ),
        });
    };

    const secretPlaceholder =
        storedType !== undefined && storedType === value.type
            ? "Stored — enter a new value to replace it"
            : undefined;

    return (
        <Box sx={{ mb: 2 }}>
            <Typography variant="subtitle2" sx={{ mb: 1 }}>
                Authentication
            </Typography>

            {storedType !== undefined ? (
                <Typography
                    variant="body2"
                    color="text.secondary"
                    sx={{ mb: 1.5 }}
                >
                    Stored credentials are never shown. Leave this section
                    untouched to keep them; changing anything here replaces them
                    in full.
                </Typography>
            ) : null}

            <TextField
                label="Auth type"
                select
                size="small"
                fullWidth
                value={value.type}
                onChange={(e) => {
                    update({ type: e.target.value as McpServerAuthType });
                }}
                error={!!error}
                helperText={error}
                sx={{ mb: 2 }}
            >
                {authTypes.map((type) => (
                    <MenuItem key={type} value={type}>
                        {AUTH_TYPE_LABELS[type]}
                    </MenuItem>
                ))}
            </TextField>

            {value.type === "bearer" ? (
                <TextField
                    label="Token"
                    type="password"
                    size="small"
                    fullWidth
                    autoComplete="off"
                    value={value.token}
                    placeholder={secretPlaceholder}
                    onChange={(e) => {
                        update({ token: e.target.value });
                    }}
                    sx={{ mb: 2 }}
                />
            ) : null}

            {value.type === "headers" ? (
                <Box sx={{ mb: 2 }}>
                    {value.headers.map((row, index) => (
                        <Box
                            key={index}
                            sx={{ display: "flex", gap: 1, mb: 1 }}
                        >
                            <TextField
                                label="Header"
                                size="small"
                                value={row.name}
                                placeholder="X-Api-Key"
                                onChange={(e) => {
                                    updateHeader(index, {
                                        name: e.target.value,
                                    });
                                }}
                                sx={{ flex: 1 }}
                            />
                            <TextField
                                label="Value"
                                type="password"
                                size="small"
                                autoComplete="off"
                                value={row.value}
                                placeholder={secretPlaceholder}
                                onChange={(e) => {
                                    updateHeader(index, {
                                        value: e.target.value,
                                    });
                                }}
                                sx={{ flex: 2 }}
                            />
                            <IconButton
                                size="small"
                                aria-label="Remove header"
                                disabled={value.headers.length === 1}
                                onClick={() => {
                                    update({
                                        headers: value.headers.filter(
                                            (_, i) => i !== index,
                                        ),
                                    });
                                }}
                            >
                                <DeleteOutlineIcon fontSize="small" />
                            </IconButton>
                        </Box>
                    ))}
                    <Button
                        size="small"
                        startIcon={<AddIcon />}
                        onClick={() => {
                            update({
                                headers: [
                                    ...value.headers,
                                    { name: "", value: "" },
                                ],
                            });
                        }}
                    >
                        Add header
                    </Button>
                </Box>
            ) : null}

            {value.type === "client_credentials" ? (
                <>
                    <TextField
                        label="Client ID"
                        size="small"
                        fullWidth
                        value={value.client_id}
                        onChange={(e) => {
                            update({ client_id: e.target.value });
                        }}
                        sx={{ mb: 2 }}
                    />
                    <TextField
                        label="Client secret"
                        type="password"
                        size="small"
                        fullWidth
                        autoComplete="off"
                        value={value.client_secret}
                        placeholder={secretPlaceholder}
                        onChange={(e) => {
                            update({ client_secret: e.target.value });
                        }}
                        sx={{ mb: 2 }}
                    />
                    <TextField
                        label="Scope"
                        size="small"
                        fullWidth
                        value={value.scope}
                        helperText="Optional"
                        onChange={(e) => {
                            update({ scope: e.target.value });
                        }}
                        sx={{ mb: 2 }}
                    />
                    <TextField
                        label="Token endpoint"
                        size="small"
                        fullWidth
                        value={value.token_endpoint}
                        helperText="Optional — discovered from the server's OAuth metadata when blank"
                        onChange={(e) => {
                            update({ token_endpoint: e.target.value });
                        }}
                        sx={{ mb: 2 }}
                    />
                </>
            ) : null}
        </Box>
    );
}
