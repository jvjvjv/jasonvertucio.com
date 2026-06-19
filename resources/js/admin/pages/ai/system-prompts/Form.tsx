import TextField from "@mui/material/TextField";

import type { InertiaFormProps } from "@inertiajs/react";

export interface FormData {
    title: string;
    description: string;
    content: string;
}

interface FormProps {
    data: FormData;
    setData: InertiaFormProps<FormData>["setData"];
    errors: Partial<Record<keyof FormData, string>>;
}

export default function Form({ data, setData, errors }: FormProps) {
    return (
        <>
            <TextField
                label="Title"
                size="small"
                fullWidth
                value={data.title}
                onChange={(e) => {
                    setData("title", e.target.value);
                }}
                error={!!errors.title}
                helperText={
                    errors.title ?? "Short display name (max 64 characters)"
                }
                slotProps={{ htmlInput: { maxLength: 64 } }}
                sx={{ mb: 2 }}
            />

            <TextField
                label="Description"
                size="small"
                fullWidth
                value={data.description}
                onChange={(e) => {
                    setData("description", e.target.value);
                }}
                error={!!errors.description}
                helperText={
                    errors.description ??
                    "Brief description of this prompt's purpose (max 200 characters)"
                }
                slotProps={{ htmlInput: { maxLength: 200 } }}
                sx={{ mb: 2 }}
            />

            <TextField
                label="Content"
                size="small"
                fullWidth
                multiline
                rows={16}
                value={data.content}
                onChange={(e) => {
                    setData("content", e.target.value);
                }}
                error={!!errors.content}
                helperText={
                    errors.content ??
                    "The system prompt text sent to the AI model"
                }
                slotProps={{
                    input: {
                        sx: { fontFamily: "monospace", fontSize: "0.85rem" },
                    },
                }}
                sx={{ mb: 2 }}
            />
        </>
    );
}
