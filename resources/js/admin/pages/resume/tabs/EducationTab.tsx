import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Card from "@mui/material/Card";
import CardContent from "@mui/material/CardContent";
import IconButton from "@mui/material/IconButton";
import TextField from "@mui/material/TextField";
import Typography from "@mui/material/Typography";
import { useState } from "react";

import DateInput from "../../../components/DateInput";
import ReorderModal from "../ReorderModal";

import type { Education } from "../types";

interface EducationTabProps {
    education: Education[];
    onChange: (education: Education[]) => void;
}

export default function EducationTab({
    education,
    onChange,
}: EducationTabProps) {
    const [reorderOpen, setReorderOpen] = useState(false);

    const handleReorder = (newIds: string[]) => {
        const reordered = newIds.map(
            (id) => education[parseInt(id.split("-")[1])],
        );
        onChange(reordered);
    };

    return (
        <Card>
            <CardContent>
                <Box
                    sx={{
                        display: "flex",
                        justifyContent: "space-between",
                        alignItems: "center",
                        mb: 2,
                    }}
                >
                    <Typography variant="h6">Education</Typography>
                    <Box sx={{ display: "flex", gap: 1 }}>
                        {education.length >= 2 && (
                            <Button
                                size="small"
                                onClick={() => {
                                    setReorderOpen(true);
                                }}
                            >
                                Reorder
                            </Button>
                        )}
                        <Button
                            size="small"
                            onClick={() => {
                                onChange([
                                    ...education,
                                    {
                                        institution: "",
                                        location: "",
                                        degree: "",
                                        level: "",
                                        dates: ["", ""],
                                        description: "",
                                    },
                                ]);
                            }}
                        >
                            + Add Education
                        </Button>
                    </Box>
                </Box>

                {education.map((edu, eduIdx) => (
                    <Box
                        key={eduIdx}
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
                                justifyContent: "flex-end",
                                mb: 2,
                            }}
                        >
                            <IconButton
                                size="small"
                                color="error"
                                onClick={() => {
                                    onChange(
                                        education.filter(
                                            (_, i) => i !== eduIdx,
                                        ),
                                    );
                                }}
                            >
                                ✕
                            </IconButton>
                        </Box>
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
                                label="Institution"
                                required
                                size="small"
                                value={edu.institution}
                                onChange={(e) => {
                                    const updated = [...education];
                                    updated[eduIdx] = {
                                        ...updated[eduIdx],
                                        institution: e.target.value,
                                    };
                                    onChange(updated);
                                }}
                            />
                            <TextField
                                label="Location"
                                size="small"
                                placeholder="City, State"
                                value={edu.location}
                                onChange={(e) => {
                                    const updated = [...education];
                                    updated[eduIdx] = {
                                        ...updated[eduIdx],
                                        location: e.target.value,
                                    };
                                    onChange(updated);
                                }}
                            />
                            <TextField
                                label="Degree"
                                size="small"
                                value={edu.degree}
                                onChange={(e) => {
                                    const updated = [...education];
                                    updated[eduIdx] = {
                                        ...updated[eduIdx],
                                        degree: e.target.value,
                                    };
                                    onChange(updated);
                                }}
                            />
                            <TextField
                                label="Level"
                                size="small"
                                placeholder="BS, BA, Masters, Certificate, Non-Degree…"
                                value={edu.level}
                                onChange={(e) => {
                                    const updated = [...education];
                                    updated[eduIdx] = {
                                        ...updated[eduIdx],
                                        level: e.target.value,
                                    };
                                    onChange(updated);
                                }}
                            />
                            <Box
                                sx={{
                                    display: "flex",
                                    gap: 2,
                                    gridColumn: { md: "1 / -1" },
                                }}
                            >
                                <DateInput
                                    label="Start Date"
                                    value={edu.dates[0]}
                                    onChange={(val) => {
                                        const updated = [...education];
                                        updated[eduIdx] = {
                                            ...updated[eduIdx],
                                            dates: [val, edu.dates[1]],
                                        };
                                        onChange(updated);
                                    }}
                                />
                                <DateInput
                                    label="End Date"
                                    value={edu.dates[1]}
                                    allowPresent
                                    onChange={(val) => {
                                        const updated = [...education];
                                        updated[eduIdx] = {
                                            ...updated[eduIdx],
                                            dates: [edu.dates[0], val],
                                        };
                                        onChange(updated);
                                    }}
                                />
                            </Box>
                            <TextField
                                label="Description"
                                size="small"
                                fullWidth
                                multiline
                                rows={2}
                                value={edu.description}
                                onChange={(e) => {
                                    const updated = [...education];
                                    updated[eduIdx] = {
                                        ...updated[eduIdx],
                                        description: e.target.value,
                                    };
                                    onChange(updated);
                                }}
                                sx={{ gridColumn: { md: "1 / -1" } }}
                            />
                        </Box>
                    </Box>
                ))}

                <ReorderModal
                    open={reorderOpen}
                    onClose={() => {
                        setReorderOpen(false);
                    }}
                    title="Reorder Education"
                    items={education.map((edu, i) => ({
                        id: `edu-${i}`,
                        label:
                            edu.institution || `(unnamed institution ${i + 1})`,
                    }))}
                    onReorder={handleReorder}
                />
            </CardContent>
        </Card>
    );
}
