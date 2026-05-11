import Box from "@mui/material/Box";
import Checkbox from "@mui/material/Checkbox";
import Chip from "@mui/material/Chip";
import FormControlLabel from "@mui/material/FormControlLabel";
import MenuItem from "@mui/material/MenuItem";
import TextField from "@mui/material/TextField";
import Typography from "@mui/material/Typography";

function slugify(text: string): string {
    return text
        .toLowerCase()
        .trim()
        .replace(/[^\w\s-]/g, "")
        .replace(/\s+/g, "-")
        .replace(/-+/g, "-")
        .replace(/^-+|-+$/g, "");
}

export interface FormData {
    name: string;
    slug: string;
    access_path: "chat" | "root";
    description: string;
    ai_system_id: number | "";
    prompt_template: string;
    allowed_roles: string[];
    is_active: boolean;
    is_public: boolean;
    require_visitor_identity: boolean;
}

interface SystemOption {
    id: number;
    name: string;
    model: string;
}

interface AiChatBotFormProps {
    data: FormData;
    setData: (
        key: keyof FormData,
        value: string | number | boolean | string[],
    ) => void;
    errors: Partial<{ [K in keyof FormData]: string }>;
    systems: SystemOption[];
    roles: string[];
    originalName: string;
}

export default function Form({
    data,
    setData,
    errors,
    systems,
    roles,
    originalName,
}: AiChatBotFormProps) {
    return (
        <>
            <Box
                sx={{
                    display: "grid",
                    gap: 2,
                    gridTemplateColumns: { xs: "1fr", md: "1fr 1fr" },
                    mb: 2,
                }}
            >
                <TextField
                    label="Name"
                    required
                    size="small"
                    value={data.name}
                    onChange={(event) => {
                        setData("name", event.target.value);
                    }}
                    onBlur={() => {
                        if (
                            data.name !== originalName &&
                            (data.slug === "" ||
                                data.slug === slugify(originalName))
                        ) {
                            setData("slug", slugify(data.name));
                        }
                    }}
                    error={!!errors.name}
                    helperText={errors.name}
                />
                <TextField
                    label="Slug"
                    required
                    size="small"
                    value={data.slug}
                    onChange={(event) => {
                        setData("slug", event.target.value);
                    }}
                    error={!!errors.slug}
                    helperText={errors.slug ?? "Used in the public chat URL"}
                />
            </Box>

            <TextField
                label="Access Path"
                select
                required
                size="small"
                fullWidth
                value={data.access_path}
                onChange={(event) => {
                    setData("access_path", event.target.value);
                }}
                error={!!errors.access_path}
                helperText={
                    errors.access_path ??
                    `This bot will open at ${data.access_path === "root" ? `/${data.slug || "[slug]"}` : `/chat/${data.slug || "[slug]"}`}`
                }
                sx={{ mb: 2 }}
            >
                <MenuItem value="chat">/chat/[slug]</MenuItem>
                <MenuItem value="root">/[slug]</MenuItem>
            </TextField>

            <TextField
                label="Description"
                size="small"
                fullWidth
                multiline
                rows={3}
                value={data.description}
                onChange={(event) => {
                    setData("description", event.target.value);
                }}
                error={!!errors.description}
                helperText={errors.description}
                sx={{ mb: 2 }}
            />

            <TextField
                label="AI System"
                select
                required
                size="small"
                fullWidth
                value={data.ai_system_id}
                onChange={(event) => {
                    setData(
                        "ai_system_id",
                        parseInt(event.target.value, 10) || "",
                    );
                }}
                error={!!errors.ai_system_id}
                helperText={errors.ai_system_id}
                sx={{ mb: 2 }}
            >
                {systems.map((system) => (
                    <MenuItem key={system.id} value={system.id}>
                        {system.name} ({system.model})
                    </MenuItem>
                ))}
            </TextField>

            <TextField
                label="Prompt Template"
                required
                size="small"
                fullWidth
                multiline
                rows={10}
                value={data.prompt_template}
                onChange={(event) => {
                    setData("prompt_template", event.target.value);
                }}
                error={!!errors.prompt_template}
                helperText={
                    errors.prompt_template ??
                    "Supported placeholders: {{bot_name}}, {{bot_slug}}, {{bot_description}}, {{visitor_name}}, {{visitor_email}}"
                }
                sx={{ mb: 2 }}
            />

            <Box sx={{ mb: 2 }}>
                <Typography variant="subtitle2" sx={{ mb: 1 }}>
                    Allowed Roles
                </Typography>
                <TextField
                    select
                    size="small"
                    fullWidth
                    value=""
                    onChange={(event) => {
                        const value = event.target.value;
                        if (!value || data.allowed_roles.includes(value)) {
                            return;
                        }

                        setData("allowed_roles", [
                            ...data.allowed_roles,
                            value,
                        ]);
                    }}
                    helperText={
                        errors.allowed_roles ??
                        "Leave empty to allow any authenticated role when the bot is not public."
                    }
                >
                    <MenuItem value="">Select a role</MenuItem>
                    {roles.map((role) => (
                        <MenuItem key={role} value={role}>
                            {role}
                        </MenuItem>
                    ))}
                </TextField>
                <Box sx={{ display: "flex", gap: 1, flexWrap: "wrap", mt: 1 }}>
                    {data.allowed_roles.map((role) => (
                        <Chip
                            key={role}
                            label={role}
                            onDelete={() => {
                                setData(
                                    "allowed_roles",
                                    data.allowed_roles.filter(
                                        (item) => item !== role,
                                    ),
                                );
                            }}
                        />
                    ))}
                </Box>
            </Box>

            <Box sx={{ display: "grid", gap: 1 }}>
                <FormControlLabel
                    control={
                        <Checkbox
                            checked={data.is_active}
                            onChange={(event) => {
                                setData("is_active", event.target.checked);
                            }}
                        />
                    }
                    label="Active"
                />
                <FormControlLabel
                    control={
                        <Checkbox
                            checked={data.is_public}
                            onChange={(event) => {
                                setData("is_public", event.target.checked);
                            }}
                        />
                    }
                    label="Public"
                />
                <FormControlLabel
                    control={
                        <Checkbox
                            checked={data.require_visitor_identity}
                            onChange={(event) => {
                                setData(
                                    "require_visitor_identity",
                                    event.target.checked,
                                );
                            }}
                        />
                    }
                    label="Require visitor name and email before the first message"
                />
            </Box>
        </>
    );
}
