import { useState, useEffect } from 'react';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Dialog from '@mui/material/Dialog';
import DialogActions from '@mui/material/DialogActions';
import DialogContent from '@mui/material/DialogContent';
import DialogTitle from '@mui/material/DialogTitle';
import IconButton from '@mui/material/IconButton';
import Typography from '@mui/material/Typography';
import {
    DndContext,
    closestCenter,
    KeyboardSensor,
    PointerSensor,
    useSensor,
    useSensors,
    type DragEndEvent,
} from '@dnd-kit/core';
import {
    SortableContext,
    sortableKeyboardCoordinates,
    verticalListSortingStrategy,
    useSortable,
    arrayMove,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';

interface ReorderModalProps {
    open: boolean;
    onClose: () => void;
    title: string;
    items: { id: string; label: string }[];
    onReorder: (newOrderedIds: string[]) => void;
}

function SortableItem({ id, label }: { id: string; label: string }) {
    const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id });

    return (
        <Box
            ref={setNodeRef}
            style={{ transform: CSS.Transform.toString(transform), transition, opacity: isDragging ? 0.5 : 1 }}
            sx={{
                display: 'flex',
                alignItems: 'center',
                gap: 1,
                py: 0.75,
                px: 1,
                mb: 0.5,
                bgcolor: 'background.paper',
                borderRadius: 1,
                border: 1,
                borderColor: 'divider',
            }}
        >
            <IconButton
                size="small"
                sx={{ cursor: 'grab', touchAction: 'none', fontSize: '1.1rem' }}
                {...attributes}
                {...listeners}
            >
                ⠿
            </IconButton>
            <Typography variant="body2">{label}</Typography>
        </Box>
    );
}

export default function ReorderModal({ open, onClose, title, items, onReorder }: ReorderModalProps) {
    const [localItems, setLocalItems] = useState(items);

    useEffect(() => {
        if (open) {
            setLocalItems(items);
        }
    }, [open]); // eslint-disable-line react-hooks/exhaustive-deps

    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 5 } }),
        useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
    );

    const handleDragEnd = (event: DragEndEvent) => {
        const { active, over } = event;
        if (over && active.id !== over.id) {
            const oldIndex = localItems.findIndex((i) => i.id === active.id);
            const newIndex = localItems.findIndex((i) => i.id === over.id);
            const reordered = arrayMove(localItems, oldIndex, newIndex);
            setLocalItems(reordered);
            onReorder(reordered.map((i) => i.id));
        }
    };

    return (
        <Dialog open={open} onClose={onClose} fullWidth maxWidth="xs">
            <DialogTitle>{title}</DialogTitle>
            <DialogContent>
                <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={handleDragEnd}>
                    <SortableContext items={localItems.map((i) => i.id)} strategy={verticalListSortingStrategy}>
                        {localItems.map((item) => (
                            <SortableItem key={item.id} id={item.id} label={item.label} />
                        ))}
                    </SortableContext>
                </DndContext>
            </DialogContent>
            <DialogActions>
                <Button onClick={onClose}>Done</Button>
            </DialogActions>
        </Dialog>
    );
}
