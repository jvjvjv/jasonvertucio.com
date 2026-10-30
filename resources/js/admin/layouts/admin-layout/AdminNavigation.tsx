import MenuIcon from "@mui/icons-material/Menu";
import Box from "@mui/material/Box";
import Drawer from "@mui/material/Drawer";
import IconButton from "@mui/material/IconButton";
import { useMemo, useState } from "react";

import { buildNavAreas } from "./navHelpers";
import NavList from "./NavList";

import type { AppBarItem } from "@/types";

import { border, color, font, shell, typeScale } from "@/admin/theme/tokens";

interface AdminNavigationProps {
    adminNav: AppBarItem[];
    currentPath: string;
}

/**
 * A sidebar that stays beside the content on wide screens. On narrow ones, a
 * top bar's menu button opens the same list in a panel.
 */
export default function AdminNavigation({
    adminNav,
    currentPath,
}: AdminNavigationProps) {
    const [panelOpen, setPanelOpen] = useState(false);
    const areas = useMemo(() => buildNavAreas(adminNav), [adminNav]);

    return (
        <>
            <Box
                sx={{
                    display: { xs: "none", md: "block" },
                    position: "sticky",
                    top: 0,
                    alignSelf: "flex-start",
                    flexShrink: 0,
                    width: { md: shell.sidebarMd, lg: shell.sidebarLg },
                    height: "100vh",
                    overflowY: "auto",
                    bgcolor: color.blue,
                }}
            >
                <NavList areas={areas} currentPath={currentPath} />
            </Box>

            <Box
                sx={{
                    display: { xs: "flex", md: "none" },
                    alignItems: "center",
                    gap: 1,
                    px: 1,
                    py: 0.5,
                    bgcolor: color.blue,
                    color: color.onBlue,
                }}
            >
                <IconButton
                    color="inherit"
                    aria-label="Open navigation menu"
                    onClick={() => {
                        setPanelOpen(true);
                    }}
                    sx={{
                        "&.Mui-focusVisible": {
                            outline: border.focusOnBlue,
                            outlineOffset: -2,
                        },
                    }}
                >
                    <MenuIcon />
                </IconButton>
                <Box
                    component="a"
                    href="/"
                    sx={{
                        color: "inherit",
                        fontFamily: font.heading,
                        fontSize: typeScale.section,
                        fontWeight: 700,
                        textDecoration: "none",
                        "&:focus-visible": {
                            outline: border.focusOnBlue,
                            outlineOffset: 2,
                        },
                    }}
                >
                    Jason Vertucio
                </Box>
            </Box>
            <Drawer
                open={panelOpen}
                onClose={() => {
                    setPanelOpen(false);
                }}
                sx={{ display: { xs: "block", md: "none" } }}
                slotProps={{
                    paper: {
                        sx: {
                            width: shell.drawer,
                            maxWidth: "85vw",
                            bgcolor: color.blue,
                        },
                    },
                }}
            >
                <NavList
                    areas={areas}
                    currentPath={currentPath}
                    onNavigate={() => {
                        setPanelOpen(false);
                    }}
                />
            </Drawer>
        </>
    );
}
