import { Link as InertiaLink } from "@inertiajs/react";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";

import type { PaginationLink } from "@/types";

interface PaginationProps {
    links: PaginationLink[];
    lastPage: number;
}

function decodePaginationLabel(label: string): string {
    return label
        .replace(/&laquo;/g, "«")
        .replace(/&raquo;/g, "»")
        .replace(/&amp;/g, "&");
}

export default function Pagination({ links, lastPage }: PaginationProps) {
    if (lastPage <= 1) {
        return null;
    }

    return (
        <Box
            component="nav"
            aria-label="Pagination"
            sx={{
                display: "flex",
                flexWrap: "wrap",
                justifyContent: "center",
                gap: 1,
                py: 1.5,
                borderTop: 1,
                borderColor: "divider",
            }}
        >
            {links.map((link, i) => (
                <Button
                    key={i}
                    component={link.url ? InertiaLink : "button"}
                    href={link.url ?? undefined}
                    size="small"
                    variant={link.active ? "contained" : "text"}
                    disabled={!link.url}
                    aria-current={link.active ? "page" : undefined}
                >
                    {decodePaginationLabel(link.label)}
                </Button>
            ))}
        </Box>
    );
}
