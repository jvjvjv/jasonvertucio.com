import { Link as InertiaLink } from "@inertiajs/react";
import Box from "@mui/material/Box";
import ButtonBase from "@mui/material/ButtonBase";
import Typography from "@mui/material/Typography";

import type { ReactNode } from "react";

import { border, font, typeScale } from "@/admin/theme/tokens";

export interface NavBlock {
    href: string;
    icon: ReactNode;
    label: string;
    description: string;
    external?: boolean;
}

interface NavGridProps {
    blocks: NavBlock[];
}

/**
 * A hub's destinations, as a directory: one row each, so the labels line up
 * and can be scanned without reading every description.
 */
export default function NavGrid({ blocks }: NavGridProps) {
    return (
        <Box
            component="ul"
            sx={{
                listStyle: "none",
                m: 0,
                p: 0,
                maxWidth: 960,
                borderTop: border.hairline,
            }}
        >
            {blocks.map((block) => (
                <Box
                    component="li"
                    key={block.href}
                    sx={{ borderBottom: border.hairline }}
                >
                    <ButtonBase
                        component={block.external ? "a" : InertiaLink}
                        href={block.href}
                        sx={{
                            display: "grid",
                            gridTemplateColumns: {
                                xs: "32px 1fr",
                                sm: "32px 240px 1fr",
                            },
                            columnGap: 1.5,
                            rowGap: 0.25,
                            alignItems: "center",
                            justifyContent: "stretch",
                            width: "100%",
                            px: 1,
                            py: 1.75,
                            textAlign: "left",
                            "&:hover": { bgcolor: "action.hover" },
                            "&.Mui-focusVisible": { outlineOffset: -2 },
                        }}
                    >
                        <Box
                            sx={{
                                gridRow: { xs: "span 2", sm: "auto" },
                                display: "flex",
                                color: "primary.main",
                            }}
                        >
                            {block.icon}
                        </Box>
                        <Box
                            component="span"
                            sx={{
                                fontFamily: font.heading,
                                fontSize: typeScale.section,
                                fontWeight: 700,
                                lineHeight: 1.3,
                            }}
                        >
                            {block.label}
                        </Box>
                        <Typography
                            variant="body2"
                            component="span"
                            color="text.secondary"
                        >
                            {block.description}
                        </Typography>
                    </ButtonBase>
                </Box>
            ))}
        </Box>
    );
}
