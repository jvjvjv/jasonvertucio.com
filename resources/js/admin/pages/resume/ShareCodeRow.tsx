import ContentCopyIcon from "@mui/icons-material/ContentCopy";
import ExpandLessIcon from "@mui/icons-material/ExpandLess";
import ExpandMoreIcon from "@mui/icons-material/ExpandMore";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Chip from "@mui/material/Chip";
import IconButton from "@mui/material/IconButton";
import TableCell from "@mui/material/TableCell";
import TableRow from "@mui/material/TableRow";
import Typography from "@mui/material/Typography";

import ViewsDownloadsTable from "./ViewsDownloadsTable";

interface ViewRecord {
    created_at_formatted: string;
    ip_address: string;
    user_agent: string;
}

interface DownloadRecord {
    created_at_formatted: string;
    version: string;
    ip_address: string;
    user_agent: string;
}

interface ShareCode {
    id: string;
    name: string | null;
    email: string | null;
    email_sent: boolean;
    created_at_formatted: string;
    expires_at_formatted: string | null;
    views_count: number;
    downloads_count: number;
    is_trashed: boolean;
    is_expired: boolean;
    resume_url: string;
    views: ViewRecord[];
    downloads: DownloadRecord[];
}

interface ShareCodeRowProps {
    code: ShareCode;
    expanded: string | null;
    onToggleExpand: (_key: string) => void;
    onInvalidate: (_id: string) => void;
}

export default function ShareCodeRow({
    code,
    expanded,
    onToggleExpand,
    onInvalidate,
}: ShareCodeRowProps) {
    const statusChip = code.is_trashed ? (
        <Chip
            label="Invalidated"
            size="small"
            color="error"
            variant="outlined"
        />
    ) : code.is_expired ? (
        <Chip label="Expired" size="small" color="warning" variant="outlined" />
    ) : (
        <Chip label="Active" size="small" color="success" variant="outlined" />
    );

    const viewsExpanded = expanded === code.id;
    const downloadsExpanded = expanded === `${code.id}-downloads`;

    return (
        <>
            <TableRow sx={{ opacity: code.is_trashed ? 0.5 : 1 }}>
                <TableCell>
                    <Box sx={{ display: "flex", alignItems: "center", gap: 1 }}>
                        <Typography
                            variant="body2"
                            fontFamily="monospace"
                            sx={{
                                bgcolor: "grey.100",
                                px: 1,
                                py: 0.25,
                                borderRadius: 1,
                            }}
                        >
                            {code.id}
                        </Typography>
                        {!code.is_trashed && !code.is_expired && (
                            <IconButton
                                size="small"
                                onClick={() => {
                                    void navigator.clipboard.writeText(
                                        code.resume_url,
                                    );
                                }}
                                title="Copy URL"
                            >
                                <ContentCopyIcon fontSize="small" />
                            </IconButton>
                        )}
                    </Box>
                </TableCell>
                <TableCell>{code.name ?? "-"}</TableCell>
                <TableCell sx={{ maxWidth: 150 }}>
                    {code.email ? (
                        <Box
                            sx={{
                                display: "flex",
                                alignItems: "center",
                                gap: 0.5,
                            }}
                        >
                            {code.email_sent && (
                                <Typography
                                    color="success.main"
                                    variant="caption"
                                >
                                    ✓
                                </Typography>
                            )}
                            <Typography
                                variant="body2"
                                noWrap
                                title={code.email}
                                sx={{ cursor: "default" }}
                            >
                                {code.email}
                            </Typography>
                        </Box>
                    ) : (
                        "-"
                    )}
                </TableCell>
                <TableCell>
                    <Typography variant="body2">
                        {code.created_at_formatted}
                    </Typography>
                </TableCell>
                <TableCell>
                    <Typography variant="body2">
                        {code.expires_at_formatted ?? "Never"}
                    </Typography>
                </TableCell>
                <TableCell>
                    {code.views_count > 0 ? (
                        <Button
                            size="small"
                            onClick={() => {
                                onToggleExpand(code.id);
                            }}
                            endIcon={
                                viewsExpanded ? (
                                    <ExpandLessIcon />
                                ) : (
                                    <ExpandMoreIcon />
                                )
                            }
                        >
                            {code.views_count}
                        </Button>
                    ) : (
                        "-"
                    )}
                </TableCell>
                <TableCell>
                    {code.downloads_count > 0 ? (
                        <Button
                            size="small"
                            onClick={() => {
                                onToggleExpand(`${code.id}-downloads`);
                            }}
                            endIcon={
                                downloadsExpanded ? (
                                    <ExpandLessIcon />
                                ) : (
                                    <ExpandMoreIcon />
                                )
                            }
                        >
                            {code.downloads_count}
                        </Button>
                    ) : (
                        "-"
                    )}
                </TableCell>
                <TableCell>{statusChip}</TableCell>
                <TableCell align="right">
                    {!code.is_trashed ? (
                        <Button
                            size="small"
                            color="error"
                            onClick={() => {
                                onInvalidate(code.id);
                            }}
                        >
                            Invalidate
                        </Button>
                    ) : (
                        "-"
                    )}
                </TableCell>
            </TableRow>

            {code.views.length > 0 && (
                <ViewsDownloadsTable
                    type="views"
                    expanded={viewsExpanded}
                    rows={code.views}
                />
            )}

            {code.downloads.length > 0 && (
                <ViewsDownloadsTable
                    type="downloads"
                    expanded={downloadsExpanded}
                    rows={code.downloads}
                />
            )}
        </>
    );
}
