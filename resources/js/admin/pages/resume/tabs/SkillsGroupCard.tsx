import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Card from "@mui/material/Card";
import CardContent from "@mui/material/CardContent";
import Typography from "@mui/material/Typography";

import SkillCategoryCard from "./SkillCategoryCard";

import type { SkillCategory } from "../types";

interface SkillsGroupCardProps {
    title: string;
    categories: SkillCategory[];
    onChange: (_categories: SkillCategory[]) => void;
    onReorderClick: () => void;
}

export default function SkillsGroupCard({
    title,
    categories,
    onChange,
    onReorderClick,
}: SkillsGroupCardProps) {
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
                    <Typography variant="h6">{title}</Typography>
                    <Box sx={{ display: "flex", gap: 1 }}>
                        {categories.length >= 2 && (
                            <Button size="small" onClick={onReorderClick}>
                                Reorder
                            </Button>
                        )}
                        <Button
                            size="small"
                            onClick={() => {
                                onChange([
                                    ...categories,
                                    { title: "", list: [] },
                                ]);
                            }}
                        >
                            + Add Category
                        </Button>
                    </Box>
                </Box>

                {categories.map((category, catIdx) => (
                    <SkillCategoryCard
                        key={catIdx}
                        category={category}
                        onChange={(updatedCategory) => {
                            const updated = [...categories];
                            updated[catIdx] = updatedCategory;
                            onChange(updated);
                        }}
                        onRemove={() => {
                            onChange(categories.filter((_, i) => i !== catIdx));
                        }}
                    />
                ))}
            </CardContent>
        </Card>
    );
}
