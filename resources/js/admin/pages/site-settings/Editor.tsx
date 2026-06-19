import { Head } from "@inertiajs/react";
import AddIcon from "@mui/icons-material/Add";
import RemoveIcon from "@mui/icons-material/Remove";
import SaveIcon from "@mui/icons-material/Save";
import Alert from "@mui/material/Alert";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Snackbar from "@mui/material/Snackbar";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import { useCallback, useState } from "react";

import PageHeader from "../../components/PageHeader";
import AdminLayout from "../../layouts/AdminLayout";

import LinkCard from "./LinkCard";

import { api } from "@/api";

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

        try {
            const json = await api.post<SaveResponse>(
                "/api/admin/site-settings",
                { links: payload },
            );

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
                    <LinkCard
                        key={link._id}
                        link={link}
                        index={index}
                        totalCount={links.length}
                        permissions={permissions}
                        onToggleCard={() => {
                            toggleCard(index);
                        }}
                        onMoveLink={(direction) => {
                            moveLink(index, direction);
                        }}
                        onRemoveLink={() => {
                            removeLink(index);
                        }}
                        onUpdateLink={(field, value) => {
                            updateLink(index, field, value);
                        }}
                    />
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
