import Box from "@mui/material/Box";
import IconButton from "@mui/material/IconButton";
import TextField from "@mui/material/TextField";

import JobBulletList from "./JobBulletList";

import type { Project } from "../types";

interface ProjectCardProps {
    project: Project;
    onChange: (_project: Project) => void;
    onRemove: () => void;
}

export default function ProjectCard({
    project,
    onChange,
    onRemove,
}: ProjectCardProps) {
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
                    justifyContent: "flex-end",
                    mb: 2,
                }}
            >
                <IconButton size="small" color="error" onClick={onRemove}>
                    ✕
                </IconButton>
            </Box>
            <TextField
                label="Project Name"
                required
                size="small"
                fullWidth
                value={project.projectName}
                onChange={(e) => {
                    onChange({ ...project, projectName: e.target.value });
                }}
                sx={{ mb: 2 }}
            />
            <TextField
                label="Description"
                size="small"
                fullWidth
                multiline
                rows={2}
                value={project.description}
                onChange={(e) => {
                    onChange({ ...project, description: e.target.value });
                }}
                sx={{ mb: 2 }}
            />
            <JobBulletList
                bullets={project.bullets}
                onChange={(bullets) => {
                    onChange({ ...project, bullets });
                }}
            />
        </Box>
    );
}
