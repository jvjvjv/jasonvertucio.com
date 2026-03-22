import { Link } from '@inertiajs/react';
import Typography from '@mui/material/Typography';
import Box from '@mui/material/Box';
import MuiLink from '@mui/material/Link';
import ArrowBackIcon from '@mui/icons-material/ArrowBack';

interface PageHeaderProps {
    title: string;
    backHref?: string;
    backLabel?: string;
}

export default function PageHeader({ title, backHref, backLabel = 'Back' }: PageHeaderProps) {
    return (
        <Box sx={{ mb: 4 }}>
            {backHref && (
                <MuiLink
                    component={Link}
                    href={backHref}
                    underline="hover"
                    variant="body2"
                    sx={{ display: 'inline-flex', alignItems: 'center', gap: 0.5, mb: 1 }}
                >
                    <ArrowBackIcon fontSize="small" />
                    {backLabel}
                </MuiLink>
            )}
            <Typography variant="h4" component="h1" fontWeight="bold">
                {title}
            </Typography>
        </Box>
    );
}
