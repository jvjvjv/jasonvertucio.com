import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
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
    /** Domains whose header credentials are stored. The values are never sent. */
    domainsWithHeaders?: { [domain: string]: boolean };
    onChange: (_value: string) => void;
}

interface HeadersTextByDomain {
    [domain: string]: string;
}

interface ParsedPolicy {
    allowedDomains: string[];
}

/**
 * Parses the incoming JSON string once, tolerating blank/invalid input. Stored
 * header values are never in it: the editor starts every textarea empty, and an
 * empty textarea means "keep what is stored".
 */
function parseInitial(value: string): ParsedPolicy {
    if (value.trim() === "") {
        return { allowedDomains: [] };
    }

    try {
        const decoded = JSON.parse(value) as { allowed_domains?: unknown };
        const allowedDomains = Array.isArray(decoded.allowed_domains)
            ? decoded.allowed_domains.filter(
                  (d): d is string => typeof d === "string",
              )
            : [];

        return { allowedDomains };
    } catch {
        return { allowedDomains: [] };
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

/**
 * Serializes the editor's state back into the `web_tool_policy` JSON string.
 * Only headers typed this session are sent; `cleared_hosts` names domains whose
 * stored headers should be removed.
 */
function buildValue(
    allowedDomains: string[],
    headersTextByDomain: HeadersTextByDomain,
    clearedHosts: string[],
): string {
    if (allowedDomains.length === 0) return "";

    const credentials: { [domain: string]: { [name: string]: string } } = {};
    allowedDomains.forEach((domain) => {
        const headers = parseHeadersText(headersTextByDomain[domain] ?? "");
        if (Object.keys(headers).length > 0) {
            credentials[domain] = headers;
        }
    });

    const cleared = clearedHosts.filter((domain) =>
        allowedDomains.includes(domain),
    );

    return JSON.stringify({
        allowed_domains: allowedDomains,
        ...(Object.keys(credentials).length > 0 ? { credentials } : {}),
        ...(cleared.length > 0 ? { cleared_hosts: cleared } : {}),
    });
}

export default function WebToolPolicyEditor({
    value,
    error,
    domainsWithHeaders = {},
    onChange,
}: WebToolPolicyEditorProps) {
    const [initial] = useState(() => parseInitial(value));
    const [clearedHosts, setClearedHosts] = useState<string[]>([]);
    const [unrestricted, setUnrestricted] = useState(
        () => initial.allowedDomains.length === 0,
    );
    const [allowedDomains, setAllowedDomains] = useState<string[]>(
        initial.allowedDomains,
    );
    const [headersTextByDomain, setHeadersTextByDomain] =
        useState<HeadersTextByDomain>({});

    const handleUnrestrictedToggle = (checked: boolean) => {
        setUnrestricted(checked);

        if (checked) {
            setAllowedDomains([]);
            setHeadersTextByDomain({});
            setClearedHosts([]);
            onChange("");
        } else {
            onChange(
                buildValue(allowedDomains, headersTextByDomain, clearedHosts),
            );
        }
    };

    const handleDomainsChange = (domains: string[]) => {
        // Carry over each surviving domain's header text; new domains start blank.
        const nextHeaders: HeadersTextByDomain = {};
        domains.forEach((domain) => {
            nextHeaders[domain] = headersTextByDomain[domain] ?? "";
        });

        const nextCleared = clearedHosts.filter((domain) =>
            domains.includes(domain),
        );

        setAllowedDomains(domains);
        setHeadersTextByDomain(nextHeaders);
        setClearedHosts(nextCleared);
        onChange(buildValue(domains, nextHeaders, nextCleared));
    };

    const handleHeaderTextChange = (domain: string, text: string) => {
        const nextHeaders = { ...headersTextByDomain, [domain]: text };
        const nextCleared =
            text === ""
                ? clearedHosts
                : clearedHosts.filter((host) => host !== domain);

        setHeadersTextByDomain(nextHeaders);
        setClearedHosts(nextCleared);
        onChange(buildValue(allowedDomains, nextHeaders, nextCleared));
    };

    const handleToggleCleared = (domain: string) => {
        const nextCleared = clearedHosts.includes(domain)
            ? clearedHosts.filter((host) => host !== domain)
            : [...clearedHosts, domain];
        const nextHeaders = { ...headersTextByDomain, [domain]: "" };

        setClearedHosts(nextCleared);
        setHeadersTextByDomain(nextHeaders);
        onChange(buildValue(allowedDomains, nextHeaders, nextCleared));
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
                                                    disabled={clearedHosts.includes(
                                                        domain,
                                                    )}
                                                    placeholder={
                                                        domainsWithHeaders[
                                                            domain
                                                        ]
                                                            ? "Headers set — leave blank to keep, or enter new headers to replace"
                                                            : "Authorization: Bearer ..."
                                                    }
                                                    helperText={
                                                        clearedHosts.includes(
                                                            domain,
                                                        )
                                                            ? "Stored headers will be removed when you save."
                                                            : undefined
                                                    }
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
                                                {domainsWithHeaders[domain] && (
                                                    <Button
                                                        size="small"
                                                        color={
                                                            clearedHosts.includes(
                                                                domain,
                                                            )
                                                                ? "inherit"
                                                                : "error"
                                                        }
                                                        onClick={() => {
                                                            handleToggleCleared(
                                                                domain,
                                                            );
                                                        }}
                                                        sx={{ mt: 0.5 }}
                                                    >
                                                        {clearedHosts.includes(
                                                            domain,
                                                        )
                                                            ? "Keep stored headers"
                                                            : "Remove headers"}
                                                    </Button>
                                                )}
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
