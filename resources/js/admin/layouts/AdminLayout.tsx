import { type ReactNode, useState } from 'react';
import { Link, usePage } from '@inertiajs/react';
import AppBar from '@mui/material/AppBar';
import Toolbar from '@mui/material/Toolbar';
import Typography from '@mui/material/Typography';
import Button from '@mui/material/Button';
import Container from '@mui/material/Container';
import Box from '@mui/material/Box';
import Alert from '@mui/material/Alert';
import Snackbar from '@mui/material/Snackbar';
import { ADMIN_NAVIGATION_ITEMS } from '../constants/navigation';
import type { SharedProps } from '../types';

interface AdminLayoutProps {
    children: ReactNode;
    title?: string;
}

export default function AdminLayout({ children, title }: AdminLayoutProps) {
    const page = usePage<SharedProps>();
    const { flash } = page.props;
    const currentPath = page.url.split('?')[0];
    const [flashOpen, setFlashOpen] = useState(
        !!(flash.success || flash.error)
    );

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
                        flexWrap: "wrap",
                        py: 1,
                    }}
                >
                    <Typography
                        variant="h6"
                        component={Link}
                        href="/"
                        sx={{
                            mr: "auto",
                            textDecoration: "none",
                            color: "white",
                        }}
                    >
                        Jason Vertucio
                    </Typography>

                    <Box
                        component="nav"
                        sx={{
                            display: "flex",
                            flexWrap: "wrap",
                            justifyContent: {
                                xs: "flex-start",
                                md: "flex-end",
                            },
                            gap: 1,
                            width: { xs: "100%", md: "auto" },
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
                                    component={Link}
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
                onClose={() => setFlashOpen(false)}
                anchorOrigin={{ vertical: "top", horizontal: "center" }}
            >
                <Alert
                    onClose={() => setFlashOpen(false)}
                    severity={flash.success ? "success" : "error"}
                    variant="filled"
                >
                    {flash.success || flash.error}
                </Alert>
            </Snackbar>
        </Box>
    );
}
