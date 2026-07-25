import { usePage } from "@inertiajs/react";
import MenuIcon from "@mui/icons-material/Menu";
import Alert from "@mui/material/Alert";
import AppBar from "@mui/material/AppBar";
import Box from "@mui/material/Box";
import Container from "@mui/material/Container";
import Divider from "@mui/material/Divider";
import IconButton from "@mui/material/IconButton";
import Menu from "@mui/material/Menu";
import Snackbar from "@mui/material/Snackbar";
import Toolbar from "@mui/material/Toolbar";
import Typography from "@mui/material/Typography";
import { type ReactNode, useState } from "react";

import DesktopNavItem from "./admin-layout/DesktopNavItem";
import MobileNavSection from "./admin-layout/MobileNavSection";

import type { SharedProps } from "@/types";

interface AdminLayoutProps {
    children: ReactNode;
    title?: string;
    noMargin?: boolean;
    showChrome?: boolean;
}

export default function AdminLayout({
    children,
    title,
    noMargin,
    showChrome = true,
}: AdminLayoutProps) {
    const page = usePage<SharedProps>();
    const { flash } = page.props;
    const adminNav = page.props.adminNav;
    const currentPath = page.url.split("?")[0];
    const [flashOpen, setFlashOpen] = useState(
        !!(flash.success ?? flash.error),
    );
    const [menuAnchor, setMenuAnchor] = useState<null | HTMLElement>(null);

    return (
        <Box
            sx={{
                display: "flex",
                flexDirection: "column",
                minHeight: "100vh",
            }}
        >
            {showChrome && (
                <AppBar position="static" sx={{ boxShadow: "none" }}>
                    <Toolbar
                        sx={{
                            maxWidth: "1280px",
                            width: "100%",
                            mx: "auto",
                            px: 2,
                            gap: 2,
                        }}
                    >
                        <Typography
                            variant="h6"
                            component="a"
                            href="/"
                            sx={{
                                mr: "auto",
                                textDecoration: "none",
                                color: "white",
                            }}
                        >
                            Jason Vertucio
                        </Typography>

                        {/* Desktop nav */}
                        <Box
                            component="nav"
                            sx={{
                                display: { xs: "none", md: "flex" },
                                flexWrap: "wrap",
                                justifyContent: "flex-end",
                                gap: 1,
                            }}
                        >
                            {adminNav.map((item) => (
                                <DesktopNavItem
                                    key={item.href}
                                    item={item}
                                    currentPath={currentPath}
                                />
                            ))}
                        </Box>

                        {/* Mobile hamburger */}
                        <IconButton
                            color="inherit"
                            aria-label="open navigation menu"
                            onClick={(e) => {
                                setMenuAnchor(e.currentTarget);
                            }}
                            sx={{ display: { xs: "flex", md: "none" } }}
                        >
                            <MenuIcon />
                        </IconButton>
                        <Menu
                            anchorEl={menuAnchor}
                            open={Boolean(menuAnchor)}
                            onClose={() => {
                                setMenuAnchor(null);
                            }}
                            slotProps={{ paper: { sx: { minWidth: 240 } } }}
                        >
                            {adminNav.map((item, i) => (
                                <Box key={item.href}>
                                    {i > 0 && <Divider />}
                                    <MobileNavSection
                                        item={item}
                                        currentPath={currentPath}
                                        onNavigate={() => {
                                            setMenuAnchor(null);
                                        }}
                                    />
                                </Box>
                            ))}
                        </Menu>
                    </Toolbar>
                </AppBar>
            )}

            <Box
                component="main"
                sx={{ flexGrow: 1, py: noMargin ? {} : { xs: 2, md: 4 } }}
            >
                <Container maxWidth={false} disableGutters>
                    {title && (
                        <Typography
                            variant="h4"
                            component="h1"
                            sx={{ mb: 3, fontWeight: "bold" }}
                        >
                            {title}
                        </Typography>
                    )}
                    {children}
                </Container>
            </Box>

            <Snackbar
                open={flashOpen}
                autoHideDuration={5000}
                onClose={() => {
                    setFlashOpen(false);
                }}
                anchorOrigin={{ vertical: "top", horizontal: "center" }}
            >
                <Alert
                    onClose={() => {
                        setFlashOpen(false);
                    }}
                    severity={flash.success ? "success" : "error"}
                    variant="filled"
                >
                    {flash.success ?? flash.error}
                </Alert>
            </Snackbar>
        </Box>
    );
}
