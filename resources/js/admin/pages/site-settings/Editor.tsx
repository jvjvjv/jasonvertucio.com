import { Head } from "@inertiajs/react";
import AddIcon from "@mui/icons-material/Add";
import ArrowDownwardIcon from "@mui/icons-material/ArrowDownward";
import ArrowUpwardIcon from "@mui/icons-material/ArrowUpward";
import DeleteIcon from "@mui/icons-material/Delete";
import DragIndicatorIcon from "@mui/icons-material/DragIndicator";
import ExpandLessIcon from "@mui/icons-material/ExpandLess";
import ExpandMoreIcon from "@mui/icons-material/ExpandMore";
import RemoveIcon from "@mui/icons-material/Remove";
import SaveIcon from "@mui/icons-material/Save";
import Alert from "@mui/material/Alert";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Card from "@mui/material/Card";
import Collapse from "@mui/material/Collapse";
import IconButton from "@mui/material/IconButton";
import MenuItem from "@mui/material/MenuItem";
import Snackbar from "@mui/material/Snackbar";
import Stack from "@mui/material/Stack";
import TextField from "@mui/material/TextField";
import Typography from "@mui/material/Typography";
import { useCallback, useState } from "react";

import PageHeader from "../../components/PageHeader";
import AdminLayout from "../../layouts/AdminLayout";

interface LinkItem {
    label?: string;
    href?: string;
    ariaLabel?: string;
    hover?: string;
    target?: string;
    can?: string;
    divider?: boolean;
}

interface InternalLink extends LinkItem {
    _id: number;
    _open: boolean;
}

interface SaveResponse {
    status: string;
    message?: string;
}

interface EditorProps {
    links: LinkItem[];
    permissions: string[];
}

let idCounter = 0;

function toInternal(links: LinkItem[]): InternalLink[] {
    return links.map((link) => ({ ...link, _id: idCounter++, _open: false }));
}

export default function SiteSettingsEditor({
    links: initialLinks,
    permissions,
}: EditorProps) {
    const [links, setLinks] = useState<InternalLink[]>(() =>
        toInternal(initialLinks),
    );
    const [saving, setSaving] = useState(false);
    const [snackbar, setSnackbar] = useState<{
        open: boolean;
        severity: "success" | "error";
        message: string;
    }>({
        open: false,
        severity: "success",
        message: "",
    });

    const toggleCard = useCallback((index: number) => {
        setLinks((prev) =>
            prev.map((l, i) => (i === index ? { ...l, _open: !l._open } : l)),
        );
    }, []);

    const updateLink = useCallback(
        (index: number, field: string, value: string) => {
            setLinks((prev) =>
                prev.map((l, i) =>
                    i === index ? { ...l, [field]: value } : l,
                ),
            );
        },
        [],
    );

    const moveLink = useCallback((index: number, direction: -1 | 1) => {
        setLinks((prev) => {
            const newIndex = index + direction;
            if (newIndex < 0 || newIndex >= prev.length) return prev;
            const updated = [...prev];
            [updated[index], updated[newIndex]] = [
                updated[newIndex],
                updated[index],
            ];
            return updated;
        });
    }, []);

    const removeLink = useCallback((index: number) => {
        setLinks((prev) => prev.filter((_, i) => i !== index));
    }, []);

    const addLink = useCallback(() => {
        setLinks((prev) => [
            ...prev,
            {
                _id: idCounter++,
                _open: true,
                label: "",
                href: "",
                ariaLabel: "",
                hover: "",
                target: "",
                can: "",
            },
        ]);
    }, []);

    const addDivider = useCallback(() => {
        setLinks((prev) => [
            ...prev,
            { _id: idCounter++, _open: false, divider: true },
        ]);
    }, []);

    const handleSubmit = async () => {
        setSaving(true);

        const payload = links.map((link) => {
            if (link.divider) {
                return { divider: "1" };
            }
            return {
                href: link.href ?? "",
                label: link.label ?? "",
                ariaLabel: link.ariaLabel ?? "",
                hover: link.hover ?? "",
                target: link.target ?? "",
                can: link.can ?? "",
            };
        });

        const token =
            document
                .querySelector('meta[name="csrf-token"]')
                ?.getAttribute("content") ?? "";

        try {
            const res = await fetch("/admin/site-settings", {
                method: "POST",
                headers: {
                    "X-CSRF-TOKEN": token,
                    Accept: "application/json",
                    "Content-Type": "application/json",
                },
                body: JSON.stringify({ links: payload }),
            });
            const json = (await res.json()) as SaveResponse;

            if (json.status === "success") {
                setSnackbar({
                    open: true,
                    severity: "success",
                    message: "Navigation saved.",
                });
            } else {
                setSnackbar({
                    open: true,
                    severity: "error",
                    message: json.message ?? "Save failed.",
                });
            }
        } catch {
            setSnackbar({
                open: true,
                severity: "error",
                message: "Network error.",
            });
        } finally {
            setSaving(false);
        }
    };

    return (
        <AdminLayout>
            <Head title="Navigation | Site Settings" />
            <PageHeader
                title="Site Settings"
                backHref="/admin"
                backLabel="Back to Admin"
            />
            <Typography
                variant="body2"
                color="text.secondary"
                sx={{ mb: 3, mt: -2 }}
            >
                Manage the sidebar navigation links. Use arrows to reorder.
            </Typography>

            <Stack spacing={1.5} sx={{ mb: 3 }}>
                {links.map((link, index) => (
                    <Card
                        key={link._id}
                        sx={link._open ? { borderColor: "primary.main" } : {}}
                    >
                        {/* Header row */}
                        <Box
                            onClick={() => {
                                toggleCard(index);
                            }}
                            sx={{
                                display: "flex",
                                alignItems: "center",
                                gap: 1.5,
                                px: 2,
                                py: 1.5,
                                cursor: "pointer",
                                userSelect: "none",
                                bgcolor: link._open ? "grey.50" : "transparent",
                                borderBottom: link._open ? "1px solid" : "none",
                                borderColor: "divider",
                                "&:hover": { bgcolor: "grey.50" },
                            }}
                        >
                            <DragIndicatorIcon
                                fontSize="small"
                                sx={{ color: "grey.400" }}
                            />

                            {/* Move buttons */}
                            <IconButton
                                size="small"
                                disabled={index === 0}
                                onClick={(e) => {
                                    e.stopPropagation();
                                    moveLink(index, -1);
                                }}
                                aria-label="Move up"
                            >
                                <ArrowUpwardIcon fontSize="small" />
                            </IconButton>
                            <IconButton
                                size="small"
                                disabled={index === links.length - 1}
                                onClick={(e) => {
                                    e.stopPropagation();
                                    moveLink(index, 1);
                                }}
                                aria-label="Move down"
                            >
                                <ArrowDownwardIcon fontSize="small" />
                            </IconButton>

                            <Typography
                                variant="body2"
                                fontWeight="medium"
                                sx={{
                                    flex: 1,
                                    overflow: "hidden",
                                    textOverflow: "ellipsis",
                                    whiteSpace: "nowrap",
                                    color: link.divider
                                        ? "grey.400"
                                        : "text.primary",
                                    fontStyle: link.divider
                                        ? "italic"
                                        : "normal",
                                }}
                            >
                                {link.divider
                                    ? "— divider —"
                                    : (link.label ?? "(new link)")}
                            </Typography>

                            {!link.divider && link.can && (
                                <Typography
                                    variant="caption"
                                    sx={{
                                        px: 1,
                                        py: 0.25,
                                        bgcolor: "warning.light",
                                        color: "warning.dark",
                                        borderRadius: 1,
                                        display: { xs: "none", sm: "inline" },
                                    }}
                                >
                                    {link.can}
                                </Typography>
                            )}

                            {link._open ? (
                                <ExpandLessIcon
                                    fontSize="small"
                                    sx={{ color: "grey.400" }}
                                />
                            ) : (
                                <ExpandMoreIcon
                                    fontSize="small"
                                    sx={{ color: "grey.400" }}
                                />
                            )}

                            <IconButton
                                size="small"
                                color="error"
                                onClick={(e) => {
                                    e.stopPropagation();
                                    removeLink(index);
                                }}
                                aria-label="Remove link"
                            >
                                <DeleteIcon fontSize="small" />
                            </IconButton>
                        </Box>

                        {/* Expandable body */}
                        <Collapse in={link._open}>
                            <Box sx={{ px: 2, py: 2 }}>
                                {link.divider ? (
                                    <Typography
                                        variant="body2"
                                        color="text.secondary"
                                        fontStyle="italic"
                                    >
                                        This is a visual divider. It renders as
                                        a horizontal rule in the navigation
                                        dropdown.
                                    </Typography>
                                ) : (
                                    <Box
                                        sx={{
                                            display: "grid",
                                            gap: 2,
                                            gridTemplateColumns: {
                                                xs: "1fr",
                                                md: "1fr 1fr",
                                            },
                                        }}
                                    >
                                        <TextField
                                            label="Label"
                                            required
                                            size="small"
                                            value={link.label ?? ""}
                                            onChange={(e) => {
                                                updateLink(
                                                    index,
                                                    "label",
                                                    e.target.value,
                                                );
                                            }}
                                            placeholder="#Skills"
                                        />
                                        <TextField
                                            label="URL / Href"
                                            required
                                            size="small"
                                            value={link.href ?? ""}
                                            onChange={(e) => {
                                                updateLink(
                                                    index,
                                                    "href",
                                                    e.target.value,
                                                );
                                            }}
                                            placeholder="/#skills or https://..."
                                        />
                                        <TextField
                                            label="Aria Label"
                                            size="small"
                                            value={link.ariaLabel ?? ""}
                                            onChange={(e) => {
                                                updateLink(
                                                    index,
                                                    "ariaLabel",
                                                    e.target.value,
                                                );
                                            }}
                                            placeholder="Accessible label for screen readers"
                                        />
                                        <TextField
                                            label="Hover text"
                                            size="small"
                                            value={link.hover ?? ""}
                                            onChange={(e) => {
                                                updateLink(
                                                    index,
                                                    "hover",
                                                    e.target.value,
                                                );
                                            }}
                                            placeholder="Tooltip shown on hover"
                                        />
                                        <TextField
                                            label="Target"
                                            select
                                            size="small"
                                            value={link.target ?? ""}
                                            onChange={(e) => {
                                                updateLink(
                                                    index,
                                                    "target",
                                                    e.target.value,
                                                );
                                            }}
                                        >
                                            <MenuItem value="">
                                                Same tab
                                            </MenuItem>
                                            <MenuItem value="_blank">
                                                New tab (_blank)
                                            </MenuItem>
                                        </TextField>
                                        <TextField
                                            label="Required permission"
                                            select
                                            size="small"
                                            value={link.can ?? ""}
                                            onChange={(e) => {
                                                updateLink(
                                                    index,
                                                    "can",
                                                    e.target.value,
                                                );
                                            }}
                                            helperText="Hide from users without this permission"
                                        >
                                            <MenuItem value="">
                                                Public (no restriction)
                                            </MenuItem>
                                            <MenuItem value="authenticated">
                                                Authenticated users only
                                            </MenuItem>
                                            {permissions.map((perm) => (
                                                <MenuItem
                                                    key={perm}
                                                    value={perm}
                                                >
                                                    {perm}
                                                </MenuItem>
                                            ))}
                                        </TextField>
                                    </Box>
                                )}
                            </Box>
                        </Collapse>
                    </Card>
                ))}
            </Stack>

            {/* Add buttons */}
            <Box sx={{ display: "flex", gap: 2, mb: 4 }}>
                <Button
                    variant="outlined"
                    startIcon={<AddIcon />}
                    onClick={addLink}
                >
                    Add navigation link
                </Button>
                <Button
                    variant="outlined"
                    color="inherit"
                    startIcon={<RemoveIcon />}
                    onClick={addDivider}
                >
                    Add divider
                </Button>
            </Box>

            {/* Save */}
            <Button
                variant="contained"
                startIcon={<SaveIcon />}
                disabled={saving}
                onClick={handleSubmit}
            >
                {saving ? "Saving…" : "Save Navigation"}
            </Button>

            <Snackbar
                open={snackbar.open}
                autoHideDuration={4000}
                onClose={() => {
                    setSnackbar((s) => ({ ...s, open: false }));
                }}
                anchorOrigin={{ vertical: "top", horizontal: "center" }}
            >
                <Alert
                    severity={snackbar.severity}
                    variant="filled"
                    onClose={() => {
                        setSnackbar((s) => ({ ...s, open: false }));
                    }}
                >
                    {snackbar.message}
                </Alert>
            </Snackbar>
        </AdminLayout>
    );
}
