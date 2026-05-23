import {
    DndContext,
    type DragEndEvent,
    KeyboardSensor,
    PointerSensor,
    closestCenter,
    useSensor,
    useSensors,
} from "@dnd-kit/core";
import {
    SortableContext,
    arrayMove,
    sortableKeyboardCoordinates,
    useSortable,
    verticalListSortingStrategy,
} from "@dnd-kit/sortable";
import { CSS } from "@dnd-kit/utilities";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Card from "@mui/material/Card";
import CardContent from "@mui/material/CardContent";
import IconButton from "@mui/material/IconButton";
import TextField from "@mui/material/TextField";
import Typography from "@mui/material/Typography";
import { useState } from "react";

import ReorderModal from "../ReorderModal";

import type { Project } from "../types";

interface SortableBulletProps {
    id: string;
    value: string;
    onChange: (value: string) => void;
    onDelete: () => void;
}

function SortableBullet({
    id,
    value,
    onChange,
    onDelete,
}: SortableBulletProps) {
    const {
        attributes,
        listeners,
        setNodeRef,
        transform,
        transition,
        isDragging,
    } = useSortable({ id });

    return (
        <Box
            ref={setNodeRef}
            style={{
                transform: CSS.Transform.toString(transform),
                transition,
                opacity: isDragging ? 0.5 : 1,
            }}
            sx={{ display: "flex", gap: 1, mb: 1, alignItems: "flex-start" }}
        >
            <IconButton
                size="small"
                sx={{
                    cursor: "grab",
                    mt: 0.5,
                    touchAction: "none",
                    fontSize: "1.1rem",
                }}
                {...attributes}
                {...listeners}
            >
                ⠿
            </IconButton>
            <TextField
                size="small"
                fullWidth
                multiline
                rows={2}
                value={value}
                onChange={(e) => {
                    onChange(e.target.value);
                }}
            />
            <IconButton size="small" color="error" onClick={onDelete}>
                ✕
            </IconButton>
        </Box>
    );
}

interface ProjectsTabProps {
    projects: Project[];
    onChange: (projects: Project[]) => void;
}

export default function ProjectsTab({ projects, onChange }: ProjectsTabProps) {
    const [reorderOpen, setReorderOpen] = useState(false);

    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 5 } }),
        useSensor(KeyboardSensor, {
            coordinateGetter: sortableKeyboardCoordinates,
        }),
    );

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

                {projects.map((project, projIdx) => {
                    const bulletIds = project.bullets.map(
                        (_, i) => `bullet-proj-${projIdx}-${i}`,
                    );

                    return (
                        <Box
                            key={projIdx}
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
                                            projects.filter(
                                                (_, i) => i !== projIdx,
                                            ),
                                        );
                                    }}
                                >
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
                                    const updated = [...projects];
                                    updated[projIdx] = {
                                        ...updated[projIdx],
                                        projectName: e.target.value,
                                    };
                                    onChange(updated);
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
                                    const updated = [...projects];
                                    updated[projIdx] = {
                                        ...updated[projIdx],
                                        description: e.target.value,
                                    };
                                    onChange(updated);
                                }}
                                sx={{ mb: 2 }}
                            />
                            <Typography
                                variant="body2"
                                fontWeight={600}
                                gutterBottom
                            >
                                Bullet Points
                            </Typography>
                            <DndContext
                                sensors={sensors}
                                collisionDetection={closestCenter}
                                onDragEnd={(event: DragEndEvent) => {
                                    const { active, over } = event;
                                    if (over && active.id !== over.id) {
                                        const oldIndex = bulletIds.indexOf(
                                            active.id as string,
                                        );
                                        const newIndex = bulletIds.indexOf(
                                            over.id as string,
                                        );
                                        const updated = [...projects];
                                        updated[projIdx] = {
                                            ...updated[projIdx],
                                            bullets: arrayMove(
                                                project.bullets,
                                                oldIndex,
                                                newIndex,
                                            ),
                                        };
                                        onChange(updated);
                                    }
                                }}
                            >
                                <SortableContext
                                    items={bulletIds}
                                    strategy={verticalListSortingStrategy}
                                >
                                    {project.bullets.map(
                                        (bullet, bulletIdx) => (
                                            <SortableBullet
                                                key={bulletIds[bulletIdx]}
                                                id={bulletIds[bulletIdx]}
                                                value={bullet}
                                                onChange={(val) => {
                                                    const updated = [
                                                        ...projects,
                                                    ];
                                                    const bullets = [
                                                        ...project.bullets,
                                                    ];
                                                    bullets[bulletIdx] = val;
                                                    updated[projIdx] = {
                                                        ...updated[projIdx],
                                                        bullets,
                                                    };
                                                    onChange(updated);
                                                }}
                                                onDelete={() => {
                                                    const updated = [
                                                        ...projects,
                                                    ];
                                                    updated[projIdx] = {
                                                        ...updated[projIdx],
                                                        bullets:
                                                            project.bullets.filter(
                                                                (_, i) =>
                                                                    i !==
                                                                    bulletIdx,
                                                            ),
                                                    };
                                                    onChange(updated);
                                                }}
                                            />
                                        ),
                                    )}
                                </SortableContext>
                            </DndContext>
                            <Button
                                size="small"
                                onClick={() => {
                                    const updated = [...projects];
                                    updated[projIdx] = {
                                        ...updated[projIdx],
                                        bullets: [...project.bullets, ""],
                                    };
                                    onChange(updated);
                                }}
                            >
                                + Add Bullet
                            </Button>
                        </Box>
                    );
                })}

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
