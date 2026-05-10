import Button from "@mui/material/Button";
import type { ButtonProps } from "@mui/material/Button";
import IconButton from "@mui/material/IconButton";
import type { ReactNode } from "react";

import useDeviceInfo from "@/hooks/useDeviceInfo";
import { Link } from "@inertiajs/react";

interface ResponsiveButtonProps {
    size?: ButtonProps["size"];
    href?: ButtonProps["href"];
    color?: ButtonProps["color"];
    icon: ReactNode;
    label: ReactNode;
    title?: string;
    variant?: ButtonProps["variant"];
    disabled?: boolean;
    onClick?: ButtonProps["onClick"];
}

export default function ResponsiveButton({
    color,
    href,
    disabled,
    title,
    onClick,
    variant = "outlined",
    icon,
    size = "small",
    label,
}: ResponsiveButtonProps) {
    const { isMobile } = useDeviceInfo();
    const spreadHref = href ? { href, component: Link } : {};

    return isMobile ? <IconButton
        {...spreadHref}
        color={color}
        disabled={disabled}
        title={title}
        onClick={onClick}
    >
        {icon}
    </IconButton> : <Button
        {...spreadHref}
        size={size}
        color={color}
        variant={variant}
        disabled={disabled}
        onClick={onClick}
        startIcon={icon}
    >
        {label}
    </Button>;
}
