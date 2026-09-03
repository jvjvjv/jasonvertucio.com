import { Link as InertiaLink } from "@inertiajs/react";
import ArrowBackIcon from "@mui/icons-material/ArrowBack";
import Box from "@mui/material/Box";
import MuiLink from "@mui/material/Link";
import Typography from "@mui/material/Typography";

import type { ReactNode } from "react";

interface PageHeaderProps {
    title: string;
    backHref?: string;
    backLabel?: string;
    /** Page-level actions, rendered on the title row opposite the title. */
    children?: ReactNode;
}

export default function PageHeader({
    title,
    backHref,
    backLabel = "Back",
    children,
}: PageHeaderProps) {
    return (
        <Box sx={{ mb: 4 }}>
            {backHref && (
                <MuiLink
                    component={InertiaLink}
                    href={backHref}
                    underline="hover"
                    variant="body2"
                    sx={{
                        display: "inline-flex",
                        alignItems: "center",
                        gap: 0.5,
                        mb: 1,
                    }}
                >
                    <ArrowBackIcon fontSize="small" />
                    {backLabel}
                </MuiLink>
            )}
            <Box
                sx={{
                    display: "flex",
                    alignItems: "center",
                    justifyContent: "space-between",
                    gap: 2,
                    flexWrap: "wrap",
                }}
            >
                <Typography variant="h4" component="h1" fontWeight="bold">
                    {title}
                </Typography>
                {children}
            </Box>
        </Box>
    );
}
