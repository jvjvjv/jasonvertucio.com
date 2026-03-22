import { type ReactNode, useState } from 'react';
import { Link, usePage } from '@inertiajs/react';
import AppBar from '@mui/material/AppBar';
import Toolbar from '@mui/material/Toolbar';
import Typography from '@mui/material/Typography';
import Button from '@mui/material/Button';
import Menu from '@mui/material/Menu';
import MenuItem from '@mui/material/MenuItem';
import Divider from '@mui/material/Divider';
import Container from '@mui/material/Container';
import Box from '@mui/material/Box';
import Alert from '@mui/material/Alert';
import Snackbar from '@mui/material/Snackbar';
import KeyboardArrowDownIcon from '@mui/icons-material/KeyboardArrowDown';
import type { SharedProps, NavLink } from '../types';

interface AdminLayoutProps {
    children: ReactNode;
    title?: string;
}

export default function AdminLayout({ children, title }: AdminLayoutProps) {
    const { auth, navLinks, flash } = usePage<SharedProps>().props;
    const [anchorEl, setAnchorEl] = useState<null | HTMLElement>(null);
    const [flashOpen, setFlashOpen] = useState(
        !!(flash.success || flash.error)
    );

    const handleMenuOpen = (event: React.MouseEvent<HTMLElement>) => {
        setAnchorEl(event.currentTarget);
    };

    const handleMenuClose = () => {
        setAnchorEl(null);
    };

    return (
        <Box sx={{ display: 'flex', flexDirection: 'column', minHeight: '100vh' }}>
            <AppBar position="sticky" sx={{ boxShadow: 'none' }}>
                <Toolbar sx={{ maxWidth: '1280px', width: '100%', mx: 'auto', px: 2 }}>
                    <Typography
                        variant="h6"
                        component={Link}
                        href="/"
                        sx={{
                            flexGrow: 1,
                            textDecoration: 'none',
                            color: 'white',
                            fontFamily: '"Convection Condensed", sans-serif',
                        }}
                    >
                        Jason Vertucio
                    </Typography>

                    <Button
                        color="inherit"
                        onClick={handleMenuOpen}
                        endIcon={<KeyboardArrowDownIcon />}
                    >
                        Places
                    </Button>
                    <Menu
                        anchorEl={anchorEl}
                        open={Boolean(anchorEl)}
                        onClose={handleMenuClose}
                    >
                        <MenuItem
                            component={Link}
                            href="/"
                            onClick={handleMenuClose}
                        >
                            Home
                        </MenuItem>
                        {navLinks.map((link: NavLink, index: number) => {
                            if (link.divider) {
                                return <Divider key={`divider-${index}`} />;
                            }
                            return (
                                <MenuItem
                                    key={link.href}
                                    component="a"
                                    href={link.href}
                                    onClick={handleMenuClose}
                                    {...(link.target ? { target: link.target, rel: 'noopener noreferrer' } : {})}
                                >
                                    {link.label}
                                </MenuItem>
                            );
                        })}
                    </Menu>
                </Toolbar>
            </AppBar>

            <Box component="main" sx={{ flexGrow: 1, py: 4 }}>
                <Container maxWidth="lg">
                    {title && (
                        <Typography variant="h4" component="h1" sx={{ mb: 3, fontWeight: 'bold' }}>
                            {title}
                        </Typography>
                    )}
                    {children}
                </Container>
            </Box>

            <Box
                component="footer"
                sx={{
                    mt: 'auto',
                    py: 1.5,
                    bgcolor: 'secondary.main',
                    color: 'white',
                }}
            >
                <Container maxWidth="lg">
                    <Typography variant="body2" align="right">
                        Copyright &copy; {new Date().getFullYear()}, Jason Vertucio.
                    </Typography>
                </Container>
            </Box>

            <Snackbar
                open={flashOpen}
                autoHideDuration={5000}
                onClose={() => setFlashOpen(false)}
                anchorOrigin={{ vertical: 'top', horizontal: 'center' }}
            >
                <Alert
                    onClose={() => setFlashOpen(false)}
                    severity={flash.success ? 'success' : 'error'}
                    variant="filled"
                >
                    {flash.success || flash.error}
                </Alert>
            </Snackbar>
        </Box>
    );
}
