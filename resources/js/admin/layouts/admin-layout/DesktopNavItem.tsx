import ExpandMoreIcon from "@mui/icons-material/ExpandMore";
import Button from "@mui/material/Button";
import Divider from "@mui/material/Divider";
import ListItemText from "@mui/material/ListItemText";
import Menu from "@mui/material/Menu";
import MenuItem from "@mui/material/MenuItem";
import { useState } from "react";

import { isPathActive, linkComponent } from "./navHelpers";

import type { AppBarItem } from "@/types";

interface DesktopNavItemProps {
    item: AppBarItem;
    currentPath: string;
}

export default function DesktopNavItem({
    item,
    currentPath,
}: DesktopNavItemProps) {
    const [anchor, setAnchor] = useState<null | HTMLElement>(null);
    const hasChildren = item.children.length > 0;
    const isActive = isPathActive(currentPath, item);

    return (
        <>
            <Button
                color="inherit"
                component={
                    hasChildren ? "button" : linkComponent(item.external)
                }
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
                        component={linkComponent(item.external)}
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
                            component={linkComponent(child.external)}
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
