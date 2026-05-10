import Button from "@mui/material/Button";
import type { ButtonProps } from "@mui/material/Button";
import IconButton from "@mui/material/IconButton";
import type { ReactNode } from "react";

import useDeviceInfo from "@/hooks/useDeviceInfo";

interface ResponsiveButtonProps {
    color?: ButtonProps["color"];
    disabled?: boolean;
    title?: string;
    onClick?: ButtonProps["onClick"];
    variant?: ButtonProps["variant"];
    icon: ReactNode;
    size?: ButtonProps["size"];
    label: ReactNode;
}

export default function ResponsiveButton({
    color,
    disabled,
    title,
    onClick,
    variant = "outlined",
    icon,
    size = "small",
    label,
}: ResponsiveButtonProps) {
    const { isMobile } = useDeviceInfo();

    return isMobile ? (
        <IconButton
            color={color}
            disabled={disabled}
            title={title}
            onClick={onClick}
        >
            {icon}
        </IconButton>
    ) : (
        <Button
            size={size}
            color={color}
            variant={variant}
            disabled={disabled}
            onClick={onClick}
            startIcon={icon}
        >
            {label}
        </Button>
    );
}
