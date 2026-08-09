import ExpandMoreIcon from "@mui/icons-material/ExpandMore";
import Collapse from "@mui/material/Collapse";
import List from "@mui/material/List";
import ListItemButton from "@mui/material/ListItemButton";
import ListItemText from "@mui/material/ListItemText";
import MenuItem from "@mui/material/MenuItem";
import { useState } from "react";

import { isPathActive, linkComponent } from "./navHelpers";

import type { AppBarItem } from "@/types";

interface MobileNavSectionProps {
    item: AppBarItem;
    currentPath: string;
    onNavigate: () => void;
}

export default function MobileNavSection({
    item,
    currentPath,
    onNavigate,
}: MobileNavSectionProps) {
    const [open, setOpen] = useState(false);
    const hasChildren = item.children.length > 0;
    const isActive = isPathActive(currentPath, item);

    if (!hasChildren) {
        return (
            <MenuItem
                component={linkComponent(item.external)}
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
                        component={linkComponent(item.external)}
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
                            component={linkComponent(child.external)}
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
