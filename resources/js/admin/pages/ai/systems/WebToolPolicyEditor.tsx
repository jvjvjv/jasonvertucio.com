import Box from "@mui/material/Box";
import FormControlLabel from "@mui/material/FormControlLabel";
import Paper from "@mui/material/Paper";
import Switch from "@mui/material/Switch";
import Table from "@mui/material/Table";
import TableBody from "@mui/material/TableBody";
import TableCell from "@mui/material/TableCell";
import TableContainer from "@mui/material/TableContainer";
import TableHead from "@mui/material/TableHead";
import TableRow from "@mui/material/TableRow";
import TextField from "@mui/material/TextField";
import Typography from "@mui/material/Typography";
import { useState } from "react";

import SkillsInput from "@/admin/components/SkillsInput";

interface WebToolPolicyEditorProps {
    /** The `web_tool_policy` form field: a JSON string, or "" for unrestricted. */
    value: string;
    error?: string;
    onChange: (_value: string) => void;
}

interface HeadersTextByDomain {
    [domain: string]: string;
}

interface ParsedPolicy {
    allowedDomains: string[];
    headersTextByDomain: HeadersTextByDomain;
}

/** Renders one credential's header map back as "Name: value" lines. */
function headersToText(headers: unknown): string {
    if (typeof headers !== "object" || headers === null) return "";
    return Object.entries(headers as { [key: string]: unknown })
        .map(([name, val]) => `${name}: ${String(val)}`)
        .join("\n");
}

/** Parses the incoming JSON string once, tolerating blank/invalid input. */
function parseInitial(value: string): ParsedPolicy {
    if (value.trim() === "") {
        return { allowedDomains: [], headersTextByDomain: {} };
    }

    try {
        const decoded = JSON.parse(value) as {
            allowed_domains?: unknown;
            credentials?: unknown;
        };
        const allowedDomains = Array.isArray(decoded.allowed_domains)
            ? decoded.allowed_domains.filter(
                  (d): d is string => typeof d === "string",
              )
            : [];
        const credentials =
            typeof decoded.credentials === "object" &&
            decoded.credentials !== null
                ? (decoded.credentials as { [domain: string]: unknown })
                : {};
        const headersTextByDomain: HeadersTextByDomain = {};
        allowedDomains.forEach((domain) => {
            headersTextByDomain[domain] = headersToText(
                credentials[domain] ?? {},
            );
        });

        return { allowedDomains, headersTextByDomain };
    } catch {
        return { allowedDomains: [], headersTextByDomain: {} };
    }
}

/** Parses one domain's "Name: value" per-line textarea into a header map. */
function parseHeadersText(text: string): { [name: string]: string } {
    const headers: { [name: string]: string } = {};

    text.split("\n").forEach((line) => {
        const idx = line.indexOf(":");
        if (idx === -1) return;
        const name = line.slice(0, idx).trim();
        const val = line.slice(idx + 1).trim();
        if (name !== "") headers[name] = val;
    });

    return headers;
}

/** Serializes the editor's state back into the `web_tool_policy` JSON string. */
function buildValue(
    allowedDomains: string[],
    headersTextByDomain: HeadersTextByDomain,
): string {
    if (allowedDomains.length === 0) return "";

    const credentials: { [domain: string]: { [name: string]: string } } = {};
    allowedDomains.forEach((domain) => {
        const headers = parseHeadersText(headersTextByDomain[domain] ?? "");
        if (Object.keys(headers).length > 0) {
            credentials[domain] = headers;
        }
    });

    return JSON.stringify({
        allowed_domains: allowedDomains,
        ...(Object.keys(credentials).length > 0 ? { credentials } : {}),
    });
}

export default function WebToolPolicyEditor({
    value,
    error,
    onChange,
}: WebToolPolicyEditorProps) {
    const [initial] = useState(() => parseInitial(value));
    const [unrestricted, setUnrestricted] = useState(
        () => initial.allowedDomains.length === 0,
    );
    const [allowedDomains, setAllowedDomains] = useState<string[]>(
        initial.allowedDomains,
    );
    const [headersTextByDomain, setHeadersTextByDomain] =
        useState<HeadersTextByDomain>(initial.headersTextByDomain);

    const handleUnrestrictedToggle = (checked: boolean) => {
        setUnrestricted(checked);

        if (checked) {
            setAllowedDomains([]);
            setHeadersTextByDomain({});
            onChange("");
        } else {
            onChange(buildValue(allowedDomains, headersTextByDomain));
        }
    };

    const handleDomainsChange = (domains: string[]) => {
        // Carry over each surviving domain's header text; new domains start blank.
        const nextHeaders: HeadersTextByDomain = {};
        domains.forEach((domain) => {
            nextHeaders[domain] = headersTextByDomain[domain] ?? "";
        });

        setAllowedDomains(domains);
        setHeadersTextByDomain(nextHeaders);
        onChange(buildValue(domains, nextHeaders));
    };

    const handleHeaderTextChange = (domain: string, text: string) => {
        const nextHeaders = { ...headersTextByDomain, [domain]: text };
        setHeadersTextByDomain(nextHeaders);
        onChange(buildValue(allowedDomains, nextHeaders));
    };

    return (
        <Box sx={{ mb: 2 }}>
            <Typography variant="subtitle2" sx={{ mb: 0.5 }}>
                Web Tool Policy
            </Typography>
            <Typography variant="body2" color="text.secondary" sx={{ mb: 1 }}>
                Scopes fetch-web-page/http-request on this system to specific
                domains, optionally with per-domain credential headers.
                Restricting to at least one domain here is also what allows this
                bot to send a credential the model was handed directly in
                conversation (e.g. a token the user pasted in) — that only works
                when a domain allow-list is set below.
            </Typography>

            <FormControlLabel
                control={
                    <Switch
                        checked={unrestricted}
                        onChange={(e) => {
                            handleUnrestrictedToggle(e.target.checked);
                        }}
                    />
                }
                label="Unrestricted (default)"
            />

            {!unrestricted && (
                <Box sx={{ mt: 1 }}>
                    <Typography
                        variant="body2"
                        color="text.secondary"
                        sx={{ mb: 0.5 }}
                    >
                        Allowed Domains
                    </Typography>
                    <SkillsInput
                        skills={allowedDomains}
                        onChange={handleDomainsChange}
                    />

                    {allowedDomains.length > 0 && (
                        <TableContainer
                            component={Paper}
                            variant="outlined"
                            sx={{ mt: 2 }}
                        >
                            <Table size="small">
                                <TableHead>
                                    <TableRow>
                                        <TableCell sx={{ width: "30%" }}>
                                            Domain
                                        </TableCell>
                                        <TableCell>
                                            Credentials — one header per line,
                                            &quot;Name: value&quot;
                                        </TableCell>
                                    </TableRow>
                                </TableHead>
                                <TableBody>
                                    {allowedDomains.map((domain) => (
                                        <TableRow key={domain}>
                                            <TableCell
                                                sx={{
                                                    verticalAlign: "top",
                                                    fontFamily: "monospace",
                                                    wordBreak: "break-all",
                                                }}
                                            >
                                                {domain}
                                            </TableCell>
                                            <TableCell>
                                                <TextField
                                                    size="small"
                                                    fullWidth
                                                    multiline
                                                    minRows={2}
                                                    placeholder="Authorization: Bearer ..."
                                                    value={
                                                        headersTextByDomain[
                                                            domain
                                                        ] ?? ""
                                                    }
                                                    onChange={(e) => {
                                                        handleHeaderTextChange(
                                                            domain,
                                                            e.target.value,
                                                        );
                                                    }}
                                                    slotProps={{
                                                        input: {
                                                            sx: {
                                                                fontFamily:
                                                                    "monospace",
                                                            },
                                                        },
                                                    }}
                                                />
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </TableContainer>
                    )}
                </Box>
            )}

            {error && (
                <Typography
                    variant="caption"
                    color="error"
                    sx={{ display: "block", mt: 0.5 }}
                >
                    {error}
                </Typography>
            )}
        </Box>
    );
}
