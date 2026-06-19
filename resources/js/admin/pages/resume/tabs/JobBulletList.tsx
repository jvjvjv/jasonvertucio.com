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
import IconButton from "@mui/material/IconButton";
import TextField from "@mui/material/TextField";
import Typography from "@mui/material/Typography";

interface SortableBulletProps {
    id: string;
    value: string;
    onChange: (_value: string) => void;
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

interface JobBulletListProps {
    bullets: string[];
    onChange: (_bullets: string[]) => void;
}

export default function JobBulletList({
    bullets,
    onChange,
}: JobBulletListProps) {
    const bulletIds = bullets.map((_, i) => `bullet-${i}`);
    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 5 } }),
        useSensor(KeyboardSensor, {
            coordinateGetter: sortableKeyboardCoordinates,
        }),
    );

    return (
        <Box sx={{ mt: 2 }}>
            <Typography variant="body2" fontWeight={600} gutterBottom>
                Bullet Points
            </Typography>
            <DndContext
                sensors={sensors}
                collisionDetection={closestCenter}
                onDragEnd={(event: DragEndEvent) => {
                    const { active, over } = event;
                    if (over && active.id !== over.id) {
                        const oldIndex = bulletIds.indexOf(active.id as string);
                        const newIndex = bulletIds.indexOf(over.id as string);
                        onChange(arrayMove(bullets, oldIndex, newIndex));
                    }
                }}
            >
                <SortableContext
                    items={bulletIds}
                    strategy={verticalListSortingStrategy}
                >
                    {bullets.map((bullet, bulletIdx) => (
                        <SortableBullet
                            key={bulletIds[bulletIdx]}
                            id={bulletIds[bulletIdx]}
                            value={bullet}
                            onChange={(value) => {
                                const nextBullets = [...bullets];
                                nextBullets[bulletIdx] = value;
                                onChange(nextBullets);
                            }}
                            onDelete={() => {
                                onChange(
                                    bullets.filter(
                                        (_, index) => index !== bulletIdx,
                                    ),
                                );
                            }}
                        />
                    ))}
                </SortableContext>
            </DndContext>
            <Button
                size="small"
                onClick={() => {
                    onChange([...bullets, ""]);
                }}
            >
                + Add Bullet
            </Button>
        </Box>
    );
}
