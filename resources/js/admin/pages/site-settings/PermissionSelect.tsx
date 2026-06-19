import MenuItem from "@mui/material/MenuItem";
import TextField from "@mui/material/TextField";

interface PermissionSelectProps {
    value: string;
    permissions: string[];
    onChange: (_value: string) => void;
}

export default function PermissionSelect({
    value,
    permissions,
    onChange,
}: PermissionSelectProps) {
    return (
        <TextField
            label="Required permission"
            select
            size="small"
            value={value}
            onChange={(e) => {
                onChange(e.target.value);
            }}
            helperText="Hide from users without this permission"
        >
            <MenuItem value="">Public (no restriction)</MenuItem>
            <MenuItem value="authenticated">Authenticated users only</MenuItem>
            {permissions.map((permission) => (
                <MenuItem key={permission} value={permission}>
                    {permission}
                </MenuItem>
            ))}
        </TextField>
    );
}
