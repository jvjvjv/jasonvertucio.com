import Box from "@mui/material/Box";
import IconButton from "@mui/material/IconButton";
import TextField from "@mui/material/TextField";

import SkillsInput from "../../../components/SkillsInput";

import type { SkillCategory } from "../types";

interface SkillCategoryCardProps {
    category: SkillCategory;
    onChange: (_category: SkillCategory) => void;
    onRemove: () => void;
}

export default function SkillCategoryCard({
    category,
    onChange,
    onRemove,
}: SkillCategoryCardProps) {
    return (
        <Box
            sx={{
                mb: 3,
                p: 2,
                bgcolor: "grey.50",
                borderRadius: 1,
            }}
        >
            <Box
                sx={{
                    display: "flex",
                    alignItems: "center",
                    gap: 2,
                    mb: 2,
                }}
            >
                <TextField
                    size="small"
                    placeholder="Category Title"
                    value={category.title}
                    onChange={(e) => {
                        onChange({ ...category, title: e.target.value });
                    }}
                    sx={{ flex: 1 }}
                    slotProps={{
                        input: {
                            sx: { fontWeight: "bold" },
                        },
                    }}
                />
                <IconButton color="error" size="small" onClick={onRemove}>
                    ✕
                </IconButton>
            </Box>
            <SkillsInput
                skills={category.list}
                onChange={(list) => {
                    onChange({ ...category, list });
                }}
            />
        </Box>
    );
}
