import Box from "@mui/material/Box";
import Checkbox from "@mui/material/Checkbox";
import FormControlLabel from "@mui/material/FormControlLabel";
import MenuItem from "@mui/material/MenuItem";
import TextField from "@mui/material/TextField";
import { useEffect } from "react";

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
    context_length: number | null;
    temperature: string;
    prompt_template: string;
    required_permission: string | null;
    is_active: boolean;
    require_visitor_identity: boolean;
    tools_enabled: boolean;
}

interface SystemOption {
    id: number;
    name: string;
    model: string;
    context_length: number | null;
    temperature: number | null;
    supports_tools: boolean;
}

interface AiChatBotFormProps {
    data: FormData;
    setData: (
        key: keyof FormData,
        value: string | number | boolean | string[] | null,
    ) => void;
    errors: Partial<{ [K in keyof FormData]: string }>;
    systems: SystemOption[];
    permissions: string[];
    originalName: string;
}

export default function Form({
    data,
    setData,
    errors,
    systems,
    permissions,
    originalName,
}: AiChatBotFormProps) {
    const selectedSystem = systems.find(
        (system) => system.id === data.ai_system_id,
    );
    const toolsEnabledBySystem = selectedSystem?.supports_tools === true;
    const systemTemperaturePlaceholder =
        selectedSystem?.temperature !== null &&
        selectedSystem?.temperature !== undefined
            ? selectedSystem.temperature.toString()
            : undefined;
    const systemContextLengthPlaceholder =
        selectedSystem?.context_length !== null &&
        selectedSystem?.context_length !== undefined
            ? selectedSystem.context_length.toString()
            : undefined;

    useEffect(() => {
        if (toolsEnabledBySystem && !data.tools_enabled) {
            setData("tools_enabled", true);
        }
    }, [data.tools_enabled, setData, toolsEnabledBySystem]);

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

            <Box
                sx={{
                    display: "grid",
                    gap: 2,
                    gridTemplateColumns: { xs: "1fr", md: "1fr 1fr" },
                    mb: 2,
                }}
            >
                <TextField
                    label="Temperature"
                    type="number"
                    size="small"
                    value={data.temperature}
                    placeholder={systemTemperaturePlaceholder}
                    onChange={(event) => {
                        setData("temperature", event.target.value);
                    }}
                    error={!!errors.temperature}
                    slotProps={{ htmlInput: { min: 0, max: 1, step: 0.01 } }}
                    sx={{ mb: 2 }}
                />
                <TextField
                    label="Context Length"
                    type="number"
                    size="small"
                    value={data.context_length ?? ""}
                    placeholder={systemContextLengthPlaceholder}
                    onChange={(event) => {
                        const value = event.target.value.trim();
                        setData(
                            "context_length",
                            value === "" ? null : parseInt(value, 10) || null,
                        );
                    }}
                    error={!!errors.context_length}
                    slotProps={{ htmlInput: { min: 1, max: 200000 } }}
                    sx={{ mb: 2 }}
                />
            </Box>

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

            <TextField
                label="Required Permission"
                select
                size="small"
                fullWidth
                value={data.required_permission ?? ""}
                onChange={(event) => {
                    setData("required_permission", event.target.value || null);
                }}
                error={!!errors.required_permission}
                helperText={
                    errors.required_permission ??
                    "Leave as Public to allow access with no authentication required."
                }
                sx={{ mb: 2 }}
            >
                <MenuItem value="">Public (no restriction)</MenuItem>
                <MenuItem value="authenticated">
                    Authenticated users only
                </MenuItem>
                {permissions.map((permission) => (
                    <MenuItem key={permission} value={permission}>
                        {permission}
                    </MenuItem>
                ))}
            </TextField>

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
                <FormControlLabel
                    control={
                        <Checkbox
                            checked={data.tools_enabled}
                            disabled={toolsEnabledBySystem}
                            onChange={(event) => {
                                setData("tools_enabled", event.target.checked);
                            }}
                        />
                    }
                    label={
                        toolsEnabledBySystem
                            ? "Enable MCP tools for this bot (provided by the selected system)"
                            : "Enable MCP tools for this bot"
                    }
                />
            </Box>
        </>
    );
}
