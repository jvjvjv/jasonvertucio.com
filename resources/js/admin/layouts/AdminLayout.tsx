import { usePage } from "@inertiajs/react";
import Alert from "@mui/material/Alert";
import Box from "@mui/material/Box";
import Container from "@mui/material/Container";
import Snackbar from "@mui/material/Snackbar";
import Typography from "@mui/material/Typography";
import { type ReactNode, useState } from "react";

import AdminNavigation from "./admin-layout/AdminNavigation";

import type { SharedProps } from "@/types";
import type { ContainerProps } from "@mui/material/Container";

interface AdminLayoutProps {
    children: ReactNode;
    title?: string;
    noMargin?: boolean;
    showChrome?: boolean;
    /**
     * The page content's maximum width, as MUI Container's `maxWidth`. Admin
     * pages are full width unless a page asks for less.
     */
    maxContainerWidth?: ContainerProps["maxWidth"];
}

export default function AdminLayout({
    children,
    title,
    noMargin,
    showChrome = true,
    maxContainerWidth = false,
}: AdminLayoutProps) {
    const page = usePage<SharedProps>();
    const { flash } = page.props;
    const adminNav = page.props.adminNav;
    const currentPath = page.url.split("?")[0];
    const [flashOpen, setFlashOpen] = useState(
        !!(flash.success ?? flash.error),
    );
    // A page keeps this layout mounted across visits that preserve its state
    // (a reload, or a delete that redirects back to the same page), so a flash
    // message can arrive after mount. Reopen the snackbar when it does, rather
    // than only ever reading the flash the page first rendered with.
    const [prevFlash, setPrevFlash] = useState(flash);
    if (prevFlash !== flash) {
        setPrevFlash(flash);
        setFlashOpen(!!(flash.success ?? flash.error));
    }

    return (
        <Box
            sx={{
                display: "flex",
                flexDirection: { xs: "column", md: "row" },
                minHeight: "100vh",
            }}
        >
            {showChrome && (
                <AdminNavigation
                    adminNav={adminNav}
                    currentPath={currentPath}
                />
            )}

            <Box
                sx={{
                    display: "flex",
                    flexDirection: "column",
                    flexGrow: 1,
                    minWidth: 0,
                }}
            >
                <Box
                    component="main"
                    sx={{ flexGrow: 1, py: noMargin ? {} : { xs: 2, md: 4 } }}
                >
                    <Container
                        maxWidth={maxContainerWidth}
                        disableGutters={!showChrome}
                    >
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
                {showChrome && (
                    <Box
                        component="footer"
                        sx={{
                            mt: "auto",
                            py: 1,
                            bgcolor: "secondary.main",
                            color: "white",
                        }}
                    >
                        <Container>
                            <Typography variant="body2" align="right">
                                Copyright &copy; {new Date().getFullYear()},
                                Jason Vertucio.
                            </Typography>
                        </Container>
                    </Box>
                )}
            </Box>
        </Box>
    );
}
