import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Card from "@mui/material/Card";
import CardContent from "@mui/material/CardContent";
import Typography from "@mui/material/Typography";
import { useState } from "react";

import ReorderModal from "../ReorderModal";

import JobCard from "./JobCard";

import type { Job } from "../types";

interface ExperienceTabProps {
    experience: Job[];
    onChange: (experience: Job[]) => void;
}

export default function ExperienceTab({
    experience,
    onChange,
}: ExperienceTabProps) {
    const [reorderOpen, setReorderOpen] = useState(false);

    const handleReorder = (newIds: string[]) => {
        const reordered = newIds.map(
            (id) => experience[parseInt(id.split("-")[1])],
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
                    <Typography variant="h6">Work Experience</Typography>
                    <Box sx={{ display: "flex", gap: 1 }}>
                        {experience.length >= 2 && (
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
                                    {
                                        jobTitle: "",
                                        jobTitleLabel: "",
                                        company: "",
                                        location: "",
                                        dates: ["", ""],
                                        bullets: [""],
                                        salaryStart: {
                                            amount: null,
                                            period: "",
                                        },
                                        salaryEnd: { amount: null, period: "" },
                                        isFreelance: false,
                                    },
                                    ...experience,
                                ]);
                            }}
                        >
                            + Add Job
                        </Button>
                    </Box>
                </Box>

                {experience.map((job, jobIdx) => (
                    <JobCard
                        key={jobIdx}
                        job={job}
                        onChange={(updatedJob) => {
                            const updated = [...experience];
                            updated[jobIdx] = updatedJob;
                            onChange(updated);
                        }}
                        onRemove={() => {
                            onChange(experience.filter((_, i) => i !== jobIdx));
                        }}
                    />
                ))}

                <ReorderModal
                    open={reorderOpen}
                    onClose={() => {
                        setReorderOpen(false);
                    }}
                    title="Reorder Work Experience"
                    items={experience.map((job, i) => ({
                        id: `job-${i}`,
                        label: `${job.jobTitleLabel || job.jobTitle || "(no title)"} @ ${job.company || "(no company)"}`,
                    }))}
                    onReorder={handleReorder}
                />
            </CardContent>
        </Card>
    );
}
