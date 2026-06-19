import Box from "@mui/material/Box";
import { useState } from "react";

import ReorderModal from "../ReorderModal";

import SkillsGroupCard from "./SkillsGroupCard";

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
            <SkillsGroupCard
                title="Top Skills"
                categories={skills.top}
                onChange={(categories) => {
                    onChange("top", categories);
                }}
                onReorderClick={() => {
                    setReorderOpen("top");
                }}
            />

            <SkillsGroupCard
                title="Other Skills"
                categories={skills.other}
                onChange={(categories) => {
                    onChange("other", categories);
                }}
                onReorderClick={() => {
                    setReorderOpen("other");
                }}
            />

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
