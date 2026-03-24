import { Link } from '@inertiajs/react';
import TableCell from '@mui/material/TableCell';
import TableRow from '@mui/material/TableRow';
import Typography from '@mui/material/Typography';

interface EmptyTableRowProps {
    colSpan: number;
    message: string;
    actionLabel?: string;
    actionHref?: string;
}

export default function EmptyTableRow({ colSpan, message, actionLabel, actionHref }: EmptyTableRowProps) {
    return (
        <TableRow>
            <TableCell colSpan={colSpan} align="center" sx={{ py: 4 }}>
                <Typography color="text.secondary">{message}</Typography>
                {actionLabel && actionHref && (
                    <Typography variant="body2" sx={{ mt: 0.5 }}>
                        <Link href={actionHref}>{actionLabel}</Link>
                    </Typography>
                )}
            </TableCell>
        </TableRow>
    );
}
