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
    horizontalListSortingStrategy,
    sortableKeyboardCoordinates,
    useSortable,
} from "@dnd-kit/sortable";
import { CSS } from "@dnd-kit/utilities";
import Box from "@mui/material/Box";
import Chip from "@mui/material/Chip";
import { useRef } from "react";

import type { ClipboardEvent, KeyboardEvent } from "react";

interface SkillsInputProps {
    skills: string[];
    onChange: (skills: string[]) => void;
}

function SortableChip({
    id,
    label,
    onDelete,
}: {
    id: string;
    label: string;
    onDelete: () => void;
}) {
    const {
        attributes,
        listeners,
        setNodeRef,
        transform,
        transition,
        isDragging,
    } = useSortable({ id });

    const style = {
        transform: CSS.Transform.toString(transform),
        transition,
        opacity: isDragging ? 0.5 : 1,
        cursor: "grab",
    };

    return (
        <Chip
            ref={setNodeRef}
            label={label}
            size="small"
            color="primary"
            variant="outlined"
            onDelete={onDelete}
            style={style}
            {...attributes}
            {...listeners}
        />
    );
}

export default function SkillsInput({ skills, onChange }: SkillsInputProps) {
    const inputRef = useRef<HTMLInputElement>(null);

    const sensors = useSensors(
        useSensor(PointerSensor, {
            activationConstraint: { distance: 5 },
        }),
        useSensor(KeyboardSensor, {
            coordinateGetter: sortableKeyboardCoordinates,
        }),
    );

    const sortableIds = skills.map((skill, idx) => `${idx}-${skill}`);

    const handleDragEnd = (event: DragEndEvent) => {
        const { active, over } = event;
        if (over && active.id !== over.id) {
            const oldIndex = sortableIds.indexOf(active.id as string);
            const newIndex = sortableIds.indexOf(over.id as string);
            onChange(arrayMove(skills, oldIndex, newIndex));
        }
    };

    const addSkill = (value: string) => {
        const trimmed = value.trim();
        if (trimmed === "") return;
        const isDuplicate = skills.some(
            (s) => s.toLowerCase() === trimmed.toLowerCase(),
        );
        if (!isDuplicate) {
            onChange([...skills, trimmed]);
        }
    };

    const removeSkill = (index: number) => {
        onChange(skills.filter((_, i) => i !== index));
    };

    const handleKeyDown = (e: KeyboardEvent<HTMLInputElement>) => {
        const input = e.currentTarget;
        if (e.key === "Enter") {
            e.preventDefault();
            addSkill(input.value);
            input.value = "";
        } else if (
            e.key === "Backspace" &&
            input.value === "" &&
            skills.length > 0
        ) {
            onChange(skills.slice(0, -1));
        }
    };

    const handlePaste = (e: ClipboardEvent<HTMLInputElement>) => {
        e.preventDefault();
        const pasted = e.clipboardData.getData("text");
        const newSkills = pasted
            .replace(/\r/g, "")
            .split("\n")
            .map((s) => s.trim())
            .filter((s) => s !== "");
        const updated = [...skills];
        newSkills.forEach((skill) => {
            const isDuplicate = updated.some(
                (s) => s.toLowerCase() === skill.toLowerCase(),
            );
            if (!isDuplicate) {
                updated.push(skill);
            }
        });
        onChange(updated);
        if (inputRef.current) inputRef.current.value = "";
    };

    return (
        <Box
            onClick={() => inputRef.current?.focus()}
            sx={{
                display: "flex",
                flexWrap: "wrap",
                alignItems: "center",
                gap: 0.5,
                p: 1,
                border: 1,
                borderColor: "divider",
                borderRadius: 1,
                minHeight: 42,
                cursor: "text",
                "&:focus-within": {
                    borderColor: "primary.main",
                    borderWidth: 2,
                    p: "7px",
                },
            }}
        >
            <DndContext
                sensors={sensors}
                collisionDetection={closestCenter}
                onDragEnd={handleDragEnd}
            >
                <SortableContext
                    items={sortableIds}
                    strategy={horizontalListSortingStrategy}
                >
                    {skills.map((skill, idx) => (
                        <SortableChip
                            key={sortableIds[idx]}
                            id={sortableIds[idx]}
                            label={skill}
                            onDelete={() => {
                                removeSkill(idx);
                            }}
                        />
                    ))}
                </SortableContext>
            </DndContext>
            <Box
                component="input"
                ref={inputRef}
                placeholder={
                    skills.length === 0 ? "Type skill, press Enter..." : ""
                }
                onKeyDown={handleKeyDown}
                onPaste={handlePaste}
                sx={{
                    flex: 1,
                    minWidth: 150,
                    border: "none",
                    outline: "none",
                    fontSize: "0.875rem",
                    py: 0.5,
                    px: 0.5,
                    bgcolor: "transparent",
                }}
            />
        </Box>
    );
}
