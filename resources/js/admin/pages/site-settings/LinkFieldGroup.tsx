import Box from "@mui/material/Box";
import MenuItem from "@mui/material/MenuItem";
import TextField from "@mui/material/TextField";

import PermissionSelect from "./PermissionSelect";

interface LinkData {
    label?: string;
    href?: string;
    ariaLabel?: string;
    hover?: string;
    target?: string;
    can?: string;
}

interface LinkFieldGroupProps {
    link: LinkData;
    permissions: string[];
    onUpdateLink: (_field: string, _value: string) => void;
}

export default function LinkFieldGroup({
    link,
    permissions,
    onUpdateLink,
}: LinkFieldGroupProps) {
    return (
        <Box
            sx={{
                display: "grid",
                gap: 2,
                gridTemplateColumns: {
                    xs: "1fr",
                    md: "1fr 1fr",
                },
            }}
        >
            <TextField
                label="Label"
                required
                size="small"
                value={link.label ?? ""}
                onChange={(e) => {
                    onUpdateLink("label", e.target.value);
                }}
                placeholder="#Skills"
            />
            <TextField
                label="URL / Href"
                required
                size="small"
                value={link.href ?? ""}
                onChange={(e) => {
                    onUpdateLink("href", e.target.value);
                }}
                placeholder="/#skills or https://..."
            />
            <TextField
                label="Aria Label"
                size="small"
                value={link.ariaLabel ?? ""}
                onChange={(e) => {
                    onUpdateLink("ariaLabel", e.target.value);
                }}
                placeholder="Accessible label for screen readers"
            />
            <TextField
                label="Hover text"
                size="small"
                value={link.hover ?? ""}
                onChange={(e) => {
                    onUpdateLink("hover", e.target.value);
                }}
                placeholder="Tooltip shown on hover"
            />
            <TextField
                label="Target"
                select
                size="small"
                value={link.target ?? ""}
                onChange={(e) => {
                    onUpdateLink("target", e.target.value);
                }}
            >
                <MenuItem value="">Same tab</MenuItem>
                <MenuItem value="_blank">New tab (_blank)</MenuItem>
            </TextField>
            <PermissionSelect
                value={link.can ?? ""}
                permissions={permissions}
                onChange={(value) => {
                    onUpdateLink("can", value);
                }}
            />
        </Box>
    );
}
