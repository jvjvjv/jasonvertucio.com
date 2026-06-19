import Box from "@mui/material/Box";
import Collapse from "@mui/material/Collapse";
import Table from "@mui/material/Table";
import TableBody from "@mui/material/TableBody";
import TableCell from "@mui/material/TableCell";
import TableHead from "@mui/material/TableHead";
import TableRow from "@mui/material/TableRow";
import Typography from "@mui/material/Typography";

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

interface ViewsDownloadsTableProps {
    type: "views" | "downloads";
    expanded: boolean;
    rows: ViewRecord[] | DownloadRecord[];
}

export default function ViewsDownloadsTable({
    type,
    expanded,
    rows,
}: ViewsDownloadsTableProps) {
    const isDownloads = type === "downloads";

    return (
        <TableRow>
            <TableCell
                colSpan={9}
                sx={{
                    p: 0,
                    borderBottom: expanded ? undefined : "none",
                }}
            >
                <Collapse in={expanded}>
                    <Box
                        sx={{
                            px: 3,
                            py: 2,
                            bgcolor: isDownloads ? "info.50" : "grey.50",
                        }}
                    >
                        <Typography variant="subtitle2" sx={{ mb: 1 }}>
                            {isDownloads ? "Download History" : "View History"}
                        </Typography>
                        <Table size="small">
                            <TableHead>
                                <TableRow>
                                    <TableCell>Date</TableCell>
                                    {isDownloads && (
                                        <TableCell>Version</TableCell>
                                    )}
                                    <TableCell>IP Address</TableCell>
                                    <TableCell>User Agent</TableCell>
                                </TableRow>
                            </TableHead>
                            <TableBody>
                                {rows.map((row, index) => (
                                    <TableRow key={index}>
                                        <TableCell>
                                            {row.created_at_formatted}
                                        </TableCell>
                                        {isDownloads && (
                                            <TableCell
                                                sx={{ fontFamily: "monospace" }}
                                            >
                                                {
                                                    (row as DownloadRecord)
                                                        .version
                                                }
                                            </TableCell>
                                        )}
                                        <TableCell
                                            sx={{ fontFamily: "monospace" }}
                                        >
                                            {row.ip_address}
                                        </TableCell>
                                        <TableCell
                                            sx={{ maxWidth: 300 }}
                                            title={row.user_agent}
                                        >
                                            <Typography variant="body2" noWrap>
                                                {row.user_agent}
                                            </Typography>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </Box>
                </Collapse>
            </TableCell>
        </TableRow>
    );
}
