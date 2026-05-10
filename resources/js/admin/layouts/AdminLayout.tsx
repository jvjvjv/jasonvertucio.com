import { type ReactNode, useState } from "react";
import { Link as InertiaLink, usePage } from "@inertiajs/react";
import AppBar from "@mui/material/AppBar";
import Toolbar from "@mui/material/Toolbar";
import Typography from "@mui/material/Typography";
import Button from "@mui/material/Button";
import Container from "@mui/material/Container";
import Box from "@mui/material/Box";
import Alert from "@mui/material/Alert";
import Snackbar from "@mui/material/Snackbar";
import IconButton from "@mui/material/IconButton";
import Menu from "@mui/material/Menu";
import MenuItem from "@mui/material/MenuItem";
import MenuIcon from "@mui/icons-material/Menu";
import { ADMIN_NAVIGATION_ITEMS } from "../constants/navigation";
import type { SharedProps } from "@/types";

interface AdminLayoutProps {
    children: ReactNode;
    title?: string;
}

export default function AdminLayout({ children, title }: AdminLayoutProps) {
    const page = usePage<SharedProps>();
    const { flash } = page.props;
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
            <AppBar position="sticky" sx={{ boxShadow: "none" }}>
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
                        component={InertiaLink}
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
                        {ADMIN_NAVIGATION_ITEMS.map((item) => {
                            const isActive =
                                currentPath === item.slug ||
                                currentPath.startsWith(`${item.slug}/`);

                            return (
                                <Button
                                    key={item.slug}
                                    color="inherit"
                                    component={InertiaLink}
                                    href={item.slug}
                                    variant={isActive ? "outlined" : "text"}
                                    sx={{
                                        borderColor: isActive
                                            ? "rgba(255, 255, 255, 0.7)"
                                            : "transparent",
                                        bgcolor: isActive
                                            ? "rgba(255, 255, 255, 0.08)"
                                            : "transparent",
                                        px: 1.5,
                                    }}
                                >
                                    {item.label}
                                </Button>
                            );
                        })}
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
                    >
                        {ADMIN_NAVIGATION_ITEMS.map((item) => {
                            const isActive =
                                currentPath === item.slug ||
                                currentPath.startsWith(`${item.slug}/`);

                            return (
                                <MenuItem
                                    key={item.slug}
                                    component={InertiaLink}
                                    href={item.slug}
                                    onClick={() => {
                                        setMenuAnchor(null);
                                    }}
                                    selected={isActive}
                                >
                                    {item.label}
                                </MenuItem>
                            );
                        })}
                    </Menu>
                </Toolbar>
            </AppBar>

            <Box component="main" sx={{ flexGrow: 1, py: 4 }}>
                <Container maxWidth="lg">
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

            <Box
                component="footer"
                sx={{
                    mt: "auto",
                    py: 1.5,
                    bgcolor: "secondary.main",
                    color: "white",
                }}
            >
                <Container maxWidth="lg">
                    <Typography variant="body2" align="right">
                        Copyright &copy; {new Date().getFullYear()}, Jason
                        Vertucio.
                    </Typography>
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
