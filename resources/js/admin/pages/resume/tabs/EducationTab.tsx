import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Card from "@mui/material/Card";
import CardContent from "@mui/material/CardContent";
import Typography from "@mui/material/Typography";
import { useState } from "react";

import ReorderModal from "../ReorderModal";

import EducationCard from "./EducationCard";

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
                    <EducationCard
                        key={eduIdx}
                        education={edu}
                        onChange={(updatedEducation) => {
                            const updated = [...education];
                            updated[eduIdx] = updatedEducation;
                            onChange(updated);
                        }}
                        onRemove={() => {
                            onChange(education.filter((_, i) => i !== eduIdx));
                        }}
                    />
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
