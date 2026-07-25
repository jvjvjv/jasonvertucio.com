import { Link as InertiaLink } from "@inertiajs/react";
import Box from "@mui/material/Box";
import Card from "@mui/material/Card";
import CardActionArea from "@mui/material/CardActionArea";
import CardContent from "@mui/material/CardContent";
import Grid from "@mui/material/Grid";
import Typography from "@mui/material/Typography";

import type { ReactNode } from "react";

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

export default function NavGrid({ blocks }: NavGridProps) {
    return (
        <Grid container spacing={3}>
            {blocks.map((block) => (
                <Grid size={{ xs: 12, sm: 6, lg: 4 }} key={block.href}>
                    <Card>
                        <CardActionArea
                            component={block.external ? "a" : InertiaLink}
                            href={block.href}
                        >
                            <CardContent
                                sx={{
                                    display: "flex",
                                    alignItems: "center",
                                    p: 3,
                                }}
                            >
                                <Box
                                    sx={{
                                        flexShrink: 0,
                                        width: 48,
                                        height: 48,
                                        bgcolor: "primary.main",
                                        borderRadius: 2,
                                        display: "flex",
                                        alignItems: "center",
                                        justifyContent: "center",
                                        color: "white",
                                        mr: 2,
                                    }}
                                >
                                    {block.icon}
                                </Box>
                                <Box>
                                    <Typography
                                        variant="subtitle1"
                                        fontWeight="bold"
                                    >
                                        {block.label}
                                    </Typography>
                                    <Typography
                                        variant="body2"
                                        color="text.secondary"
                                    >
                                        {block.description}
                                    </Typography>
                                </Box>
                            </CardContent>
                        </CardActionArea>
                    </Card>
                </Grid>
            ))}
        </Grid>
    );
}
