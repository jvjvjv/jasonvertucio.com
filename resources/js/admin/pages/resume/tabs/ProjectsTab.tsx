import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Card from "@mui/material/Card";
import CardContent from "@mui/material/CardContent";
import Typography from "@mui/material/Typography";
import { useState } from "react";

import ReorderModal from "../ReorderModal";

import ProjectCard from "./ProjectCard";

import type { Project } from "../types";

interface ProjectsTabProps {
    projects: Project[];
    onChange: (projects: Project[]) => void;
}

export default function ProjectsTab({ projects, onChange }: ProjectsTabProps) {
    const [reorderOpen, setReorderOpen] = useState(false);

    const handleReorder = (newIds: string[]) => {
        const reordered = newIds.map(
            (id) => projects[parseInt(id.split("-")[1])],
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
                    <Typography variant="h6">Selected Projects</Typography>
                    <Box sx={{ display: "flex", gap: 1 }}>
                        {projects.length >= 2 && (
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
                                    ...projects,
                                    {
                                        projectName: "",
                                        description: "",
                                        bullets: [""],
                                    },
                                ]);
                            }}
                        >
                            + Add Project
                        </Button>
                    </Box>
                </Box>

                {projects.map((project, projIdx) => (
                    <ProjectCard
                        key={projIdx}
                        project={project}
                        onChange={(updatedProject) => {
                            const updated = [...projects];
                            updated[projIdx] = updatedProject;
                            onChange(updated);
                        }}
                        onRemove={() => {
                            onChange(projects.filter((_, i) => i !== projIdx));
                        }}
                    />
                ))}

                <ReorderModal
                    open={reorderOpen}
                    onClose={() => {
                        setReorderOpen(false);
                    }}
                    title="Reorder Projects"
                    items={projects.map((project, i) => ({
                        id: `proj-${i}`,
                        label:
                            project.projectName || `(unnamed project ${i + 1})`,
                    }))}
                    onReorder={handleReorder}
                />
            </CardContent>
        </Card>
    );
}
