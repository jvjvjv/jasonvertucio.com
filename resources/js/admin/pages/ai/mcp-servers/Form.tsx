import Checkbox from "@mui/material/Checkbox";
import FormControlLabel from "@mui/material/FormControlLabel";
import TextField from "@mui/material/TextField";

import type { McpServerAuthDraft } from "@/admin/components/McpServerAuthEditor";
import type { McpServerAuthType } from "@/types";
import type { InertiaFormProps } from "@inertiajs/react";

import McpServerAuthEditor, {
    toAuthPayload,
} from "@/admin/components/McpServerAuthEditor";

export interface FormData {
    slug: string;
    name: string;
    url: string;
    timeout_seconds: string;
    enabled: boolean;
    auth: McpServerAuthDraft;
}

/**
 * The request body for the form. `auth` is only included when `includeAuth`
 * is set — on edit, an omitted `auth` keeps the stored credentials.
 */
export function toRequestPayload(
    data: FormData,
    includeAuth: boolean,
): { [key: string]: unknown } {
    const payload: { [key: string]: unknown } = {
        slug: data.slug,
        name: data.name,
        url: data.url,
        timeout_seconds:
            data.timeout_seconds.trim() === ""
                ? null
                : Number(data.timeout_seconds),
        enabled: data.enabled,
    };

    if (includeAuth) {
        payload.auth = toAuthPayload(data.auth);
    }

    return payload;
}

interface FormProps {
    data: FormData;
    setData: InertiaFormProps<FormData>["setData"];
    errors: { [key: string]: string | undefined };
    authTypes: McpServerAuthType[];
    isEdit?: boolean;
    storedAuthType?: McpServerAuthType;
    onAuthChange?: () => void;
}

export default function Form({
    data,
    setData,
    errors,
    authTypes,
    isEdit = false,
    storedAuthType,
    onAuthChange,
}: FormProps) {
    return (
        <>
            <TextField
                label="Name"
                size="small"
                fullWidth
                value={data.name}
                onChange={(e) => {
                    setData("name", e.target.value);
                }}
                error={!!errors.name}
                helperText={errors.name}
                sx={{ mb: 2 }}
            />

            <TextField
                label="Slug"
                size="small"
                fullWidth
                value={data.slug}
                disabled={isEdit}
                onChange={(e) => {
                    setData("slug", e.target.value);
                }}
                error={!!errors.slug}
                helperText={
                    errors.slug ??
                    (isEdit
                        ? "The slug cannot be changed — it prefixes every tool name granted to AI systems."
                        : "Lowercase letters, digits and hyphens (max 24). Tools are exposed as {slug}__{tool}.")
                }
                slotProps={{
                    htmlInput: {
                        maxLength: 24,
                        sx: { fontFamily: "monospace" },
                    },
                }}
                sx={{ mb: 2 }}
            />

            <TextField
                label="URL"
                size="small"
                fullWidth
                value={data.url}
                placeholder="https://example.com/mcp"
                onChange={(e) => {
                    setData("url", e.target.value);
                }}
                error={!!errors.url}
                helperText={errors.url ?? "The server's HTTP(S) MCP endpoint"}
                sx={{ mb: 2 }}
            />

            <TextField
                label="Timeout (seconds)"
                type="number"
                size="small"
                fullWidth
                value={data.timeout_seconds}
                onChange={(e) => {
                    setData("timeout_seconds", e.target.value);
                }}
                error={!!errors.timeout_seconds}
                helperText={
                    errors.timeout_seconds ??
                    "Optional. Bounds the handshake and each call; capped by the site's configured maximum."
                }
                slotProps={{ htmlInput: { min: 1, max: 300 } }}
                sx={{ mb: 2 }}
            />

            <McpServerAuthEditor
                value={data.auth}
                onChange={(auth) => {
                    setData("auth", auth);
                    onAuthChange?.();
                }}
                authTypes={authTypes}
                error={errors.auth}
                storedType={storedAuthType}
            />

            <FormControlLabel
                control={
                    <Checkbox
                        checked={data.enabled}
                        onChange={(e) => {
                            setData("enabled", e.target.checked);
                        }}
                    />
                }
                label="Enabled — a disabled server's tools are not offered to any AI system"
            />
        </>
    );
}
