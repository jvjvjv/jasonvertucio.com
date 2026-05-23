import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Card from "@mui/material/Card";
import CardContent from "@mui/material/CardContent";
import IconButton from "@mui/material/IconButton";
import TextField from "@mui/material/TextField";
import Typography from "@mui/material/Typography";
import { useState } from "react";

import SkillsInput from "../../../components/SkillsInput";
import ReorderModal from "../ReorderModal";

import type { SkillCategory } from "../types";

interface SkillsTabProps {
    skills: { top: SkillCategory[]; other: SkillCategory[] };
    onChange: (type: "top" | "other", categories: SkillCategory[]) => void;
}

export default function SkillsTab({ skills, onChange }: SkillsTabProps) {
    const [reorderOpen, setReorderOpen] = useState<"top" | "other" | null>(
        null,
    );

    const handleReorder = (type: "top" | "other", newIds: string[]) => {
        const reordered = newIds.map((id) => {
            const idx = parseInt(id.split("-")[2]);
            return skills[type][idx];
        });
        onChange(type, reordered);
    };

    return (
        <Box sx={{ display: "flex", flexDirection: "column", gap: 2 }}>
            {(["top", "other"] as const).map((type) => (
                <Card key={type}>
                    <CardContent>
                        <Box
                            sx={{
                                display: "flex",
                                justifyContent: "space-between",
                                alignItems: "center",
                                mb: 2,
                            }}
                        >
                            <Typography variant="h6">
                                {type === "top" ? "Top Skills" : "Other Skills"}
                            </Typography>
                            <Box sx={{ display: "flex", gap: 1 }}>
                                {skills[type].length >= 2 && (
                                    <Button
                                        size="small"
                                        onClick={() => {
                                            setReorderOpen(type);
                                        }}
                                    >
                                        Reorder
                                    </Button>
                                )}
                                <Button
                                    size="small"
                                    onClick={() => {
                                        onChange(type, [
                                            ...skills[type],
                                            { title: "", list: [] },
                                        ]);
                                    }}
                                >
                                    + Add Category
                                </Button>
                            </Box>
                        </Box>
                        {skills[type].map((category, catIdx) => (
                            <Box
                                key={catIdx}
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
                                            const updated = [...skills[type]];
                                            updated[catIdx] = {
                                                ...updated[catIdx],
                                                title: e.target.value,
                                            };
                                            onChange(type, updated);
                                        }}
                                        sx={{ flex: 1 }}
                                        slotProps={{
                                            input: {
                                                sx: { fontWeight: "bold" },
                                            },
                                        }}
                                    />
                                    <IconButton
                                        color="error"
                                        size="small"
                                        onClick={() => {
                                            const updated = skills[type].filter(
                                                (_, i) => i !== catIdx,
                                            );
                                            onChange(type, updated);
                                        }}
                                    >
                                        ✕
                                    </IconButton>
                                </Box>
                                <SkillsInput
                                    skills={category.list}
                                    onChange={(newList) => {
                                        const updated = [...skills[type]];
                                        updated[catIdx] = {
                                            ...updated[catIdx],
                                            list: newList,
                                        };
                                        onChange(type, updated);
                                    }}
                                />
                            </Box>
                        ))}
                    </CardContent>
                </Card>
            ))}

            {reorderOpen && (
                <ReorderModal
                    open={true}
                    onClose={() => {
                        setReorderOpen(null);
                    }}
                    title={`Reorder ${reorderOpen === "top" ? "Top" : "Other"} Skill Categories`}
                    items={skills[reorderOpen].map((cat, i) => ({
                        id: `cat-${reorderOpen}-${i}`,
                        label: cat.title || `(untitled category ${i + 1})`,
                    }))}
                    onReorder={(ids) => {
                        handleReorder(reorderOpen, ids);
                    }}
                />
            )}
        </Box>
    );
}
