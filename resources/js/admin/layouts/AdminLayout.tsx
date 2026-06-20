import { Link as InertiaLink, usePage } from "@inertiajs/react";
import ExpandMoreIcon from "@mui/icons-material/ExpandMore";
import MenuIcon from "@mui/icons-material/Menu";
import Alert from "@mui/material/Alert";
import AppBar from "@mui/material/AppBar";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Collapse from "@mui/material/Collapse";
import Container from "@mui/material/Container";
import Divider from "@mui/material/Divider";
import IconButton from "@mui/material/IconButton";
import List from "@mui/material/List";
import ListItemButton from "@mui/material/ListItemButton";
import ListItemText from "@mui/material/ListItemText";
import Menu from "@mui/material/Menu";
import MenuItem from "@mui/material/MenuItem";
import Snackbar from "@mui/material/Snackbar";
import Toolbar from "@mui/material/Toolbar";
import Typography from "@mui/material/Typography";
import { type ReactNode, useState } from "react";

import type { AppBarItem, SharedProps } from "@/types";

interface AdminLayoutProps {
    children: ReactNode;
    title?: string;
}

function isPathActive(currentPath: string, item: AppBarItem): boolean {
    return (
        currentPath === item.href ||
        currentPath.startsWith(`${item.href}/`) ||
        item.children.some(
            (c) =>
                currentPath === c.href || currentPath.startsWith(`${c.href}/`),
        )
    );
}

function DesktopNavItem({
    item,
    currentPath,
}: {
    item: AppBarItem;
    currentPath: string;
}) {
    const [anchor, setAnchor] = useState<null | HTMLElement>(null);
    const hasChildren = item.children.length > 0;
    const isActive = isPathActive(currentPath, item);

    return (
        <>
            <Button
                color="inherit"
                component={hasChildren ? "button" : InertiaLink}
                href={hasChildren ? undefined : item.href}
                endIcon={
                    hasChildren ? (
                        <ExpandMoreIcon
                            sx={{
                                transition: "transform 150ms",
                                transform: anchor
                                    ? "rotate(180deg)"
                                    : "rotate(0deg)",
                            }}
                        />
                    ) : undefined
                }
                onClick={
                    hasChildren
                        ? (e: React.MouseEvent<HTMLButtonElement>) => {
                              setAnchor(e.currentTarget);
                          }
                        : undefined
                }
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

            {hasChildren && (
                <Menu
                    anchorEl={anchor}
                    open={Boolean(anchor)}
                    onClose={() => {
                        setAnchor(null);
                    }}
                    anchorOrigin={{ vertical: "bottom", horizontal: "left" }}
                    transformOrigin={{ vertical: "top", horizontal: "left" }}
                    slotProps={{ paper: { sx: { minWidth: 220 } } }}
                >
                    <MenuItem
                        component={InertiaLink}
                        href={item.href}
                        selected={currentPath === item.href}
                        onClick={() => {
                            setAnchor(null);
                        }}
                    >
                        <ListItemText
                            primary={item.label}
                            slotProps={{
                                primary: {
                                    variant: "body2",
                                    color: "text.secondary",
                                },
                            }}
                        />
                    </MenuItem>
                    <Divider />
                    {item.children.map((child) => (
                        <MenuItem
                            key={child.href}
                            component={InertiaLink}
                            href={child.href}
                            selected={
                                currentPath === child.href ||
                                currentPath.startsWith(`${child.href}/`)
                            }
                            onClick={() => {
                                setAnchor(null);
                            }}
                        >
                            {child.label}
                        </MenuItem>
                    ))}
                </Menu>
            )}
        </>
    );
}

function MobileNavSection({
    item,
    currentPath,
    onNavigate,
}: {
    item: AppBarItem;
    currentPath: string;
    onNavigate: () => void;
}) {
    const [open, setOpen] = useState(false);
    const hasChildren = item.children.length > 0;
    const isActive = isPathActive(currentPath, item);

    if (!hasChildren) {
        return (
            <MenuItem
                component={InertiaLink}
                href={item.href}
                selected={isActive}
                onClick={onNavigate}
            >
                {item.label}
            </MenuItem>
        );
    }

    return (
        <>
            <MenuItem
                selected={isActive}
                onClick={() => {
                    setOpen((v) => !v);
                }}
                sx={{ justifyContent: "space-between" }}
            >
                {item.label}
                <ExpandMoreIcon
                    fontSize="small"
                    sx={{
                        ml: 1,
                        transition: "transform 150ms",
                        transform: open ? "rotate(180deg)" : "rotate(0deg)",
                    }}
                />
            </MenuItem>
            <Collapse in={open}>
                <List disablePadding dense>
                    <ListItemButton
                        component={InertiaLink}
                        href={item.href}
                        selected={currentPath === item.href}
                        onClick={onNavigate}
                        sx={{ pl: 4 }}
                    >
                        <ListItemText
                            primary={item.label}
                            slotProps={{
                                primary: {
                                    variant: "body2",
                                    color: "text.secondary",
                                },
                            }}
                        />
                    </ListItemButton>
                    {item.children.map((child) => (
                        <ListItemButton
                            key={child.href}
                            component={InertiaLink}
                            href={child.href}
                            selected={
                                currentPath === child.href ||
                                currentPath.startsWith(`${child.href}/`)
                            }
                            onClick={onNavigate}
                            sx={{ pl: 4 }}
                        >
                            <ListItemText primary={child.label} />
                        </ListItemButton>
                    ))}
                </List>
            </Collapse>
        </>
    );
}

export default function AdminLayout({ children, title }: AdminLayoutProps) {
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

            <Box component="main" sx={{ flexGrow: 1, py: { xs: 2, md: 4 } }}>
                <Container maxWidth={false}>
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
                <Container>
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
