import { useState } from 'react';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Card from '@mui/material/Card';
import CardContent from '@mui/material/CardContent';
import Checkbox from '@mui/material/Checkbox';
import FormControlLabel from '@mui/material/FormControlLabel';
import IconButton from '@mui/material/IconButton';
import MenuItem from '@mui/material/MenuItem';
import TextField from '@mui/material/TextField';
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
import DateInput from '../../../components/DateInput';
import ReorderModal from '../ReorderModal';
import type { Job } from '../types';
import { SALARY_PERIODS } from '../types';

interface SortableBulletProps {
    id: string;
    value: string;
    onChange: (value: string) => void;
    onDelete: () => void;
}

function SortableBullet({ id, value, onChange, onDelete }: SortableBulletProps) {
    const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id });

    return (
        <Box
            ref={setNodeRef}
            style={{ transform: CSS.Transform.toString(transform), transition, opacity: isDragging ? 0.5 : 1 }}
            sx={{ display: 'flex', gap: 1, mb: 1, alignItems: 'flex-start' }}
        >
            <IconButton
                size="small"
                sx={{ cursor: 'grab', mt: 0.5, touchAction: 'none', fontSize: '1.1rem' }}
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
                onChange={(e) => onChange(e.target.value)}
            />
            <IconButton size="small" color="error" onClick={onDelete}>
                ✕
            </IconButton>
        </Box>
    );
}

interface ExperienceTabProps {
    experience: Job[];
    onChange: (experience: Job[]) => void;
}

export default function ExperienceTab({ experience, onChange }: ExperienceTabProps) {
    const [reorderOpen, setReorderOpen] = useState(false);

    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 5 } }),
        useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
    );

    const handleReorder = (newIds: string[]) => {
        const reordered = newIds.map((id) => experience[parseInt(id.split('-')[1])]);
        onChange(reordered);
    };

    return (
        <Card>
            <CardContent>
                <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 2 }}>
                    <Typography variant="h6">Work Experience</Typography>
                    <Box sx={{ display: 'flex', gap: 1 }}>
                        {experience.length >= 2 && (
                            <Button size="small" onClick={() => setReorderOpen(true)}>
                                Reorder
                            </Button>
                        )}
                        <Button
                            size="small"
                            onClick={() =>
                                onChange([
                                    ...experience,
                                    {
                                        jobTitle: '',
                                        company: '',
                                        location: '',
                                        dates: ['', ''],
                                        bullets: [''],
                                        salaryStart: { amount: null, period: '' },
                                        salaryEnd: { amount: null, period: '' },
                                        isFreelance: false,
                                    },
                                ])
                            }
                        >
                            + Add Job
                        </Button>
                    </Box>
                </Box>

                {experience.map((job, jobIdx) => {
                    const bulletIds = job.bullets.map((_, i) => `bullet-${jobIdx}-${i}`);

                    return (
                        <Box key={jobIdx} sx={{ mb: 3, p: 2, bgcolor: 'grey.50', borderRadius: 1 }}>
                            <Box sx={{ display: 'flex', justifyContent: 'flex-end', mb: 2 }}>
                                <IconButton
                                    size="small"
                                    color="error"
                                    onClick={() => onChange(experience.filter((_, i) => i !== jobIdx))}
                                >
                                    ✕
                                </IconButton>
                            </Box>
                            <Box sx={{ display: 'grid', gap: 2, gridTemplateColumns: { xs: '1fr', md: '1fr 1fr' } }}>
                                <TextField
                                    label="Job Title"
                                    required
                                    size="small"
                                    value={job.jobTitle}
                                    onChange={(e) => {
                                        const updated = [...experience];
                                        updated[jobIdx] = { ...updated[jobIdx], jobTitle: e.target.value };
                                        onChange(updated);
                                    }}
                                />
                                <TextField
                                    label="Company"
                                    required
                                    size="small"
                                    value={job.company}
                                    onChange={(e) => {
                                        const updated = [...experience];
                                        updated[jobIdx] = { ...updated[jobIdx], company: e.target.value };
                                        onChange(updated);
                                    }}
                                />
                                <TextField
                                    label="Location"
                                    size="small"
                                    value={job.location}
                                    onChange={(e) => {
                                        const updated = [...experience];
                                        updated[jobIdx] = { ...updated[jobIdx], location: e.target.value };
                                        onChange(updated);
                                    }}
                                />
                                <Box sx={{ display: 'flex', gap: 2, gridColumn: { md: '1 / -1' } }}>
                                    <DateInput
                                        label="Start Date"
                                        value={job.dates[0] ?? ''}
                                        onChange={(val) => {
                                            const updated = [...experience];
                                            updated[jobIdx] = {
                                                ...updated[jobIdx],
                                                dates: [val, job.dates[1] ?? ''],
                                            };
                                            onChange(updated);
                                        }}
                                    />
                                    <DateInput
                                        label="End Date"
                                        value={job.dates[1] ?? ''}
                                        allowPresent
                                        onChange={(val) => {
                                            const updated = [...experience];
                                            updated[jobIdx] = {
                                                ...updated[jobIdx],
                                                dates: [job.dates[0] ?? '', val],
                                            };
                                            onChange(updated);
                                        }}
                                    />
                                </Box>
                                <FormControlLabel
                                    control={
                                        <Checkbox
                                            checked={job.isFreelance}
                                            onChange={(e) => {
                                                const updated = [...experience];
                                                updated[jobIdx] = { ...updated[jobIdx], isFreelance: e.target.checked };
                                                onChange(updated);
                                            }}
                                        />
                                    }
                                    label="Freelance / Contract"
                                    sx={{ gridColumn: { md: '1 / -1' } }}
                                />
                            </Box>

                            {/* Salary (private) */}
                            <Box sx={{ mt: 2, pt: 2, borderTop: 1, borderColor: 'divider' }}>
                                <Typography variant="caption" color="text.secondary" sx={{ display: 'block', mb: 1 }}>
                                    Salary data is private and will not appear on the public resume.
                                </Typography>
                                <Box sx={{ display: 'grid', gap: 2, gridTemplateColumns: { xs: '1fr', md: '1fr 1fr' } }}>
                                    {(['salaryStart', 'salaryEnd'] as const).map((salaryKey) => (
                                        <Box key={salaryKey} sx={{ display: 'flex', gap: 1 }}>
                                            <TextField
                                                label={salaryKey === 'salaryStart' ? 'Starting Salary' : 'Ending Salary'}
                                                type="number"
                                                size="small"
                                                value={job[salaryKey].amount ?? ''}
                                                onChange={(e) => {
                                                    const updated = [...experience];
                                                    updated[jobIdx] = {
                                                        ...updated[jobIdx],
                                                        [salaryKey]: {
                                                            ...job[salaryKey],
                                                            amount: e.target.value ? parseFloat(e.target.value) : null,
                                                        },
                                                    };
                                                    onChange(updated);
                                                }}
                                                slotProps={{ htmlInput: { min: 0, step: 0.01 } }}
                                                sx={{ flex: 1 }}
                                            />
                                            <TextField
                                                select
                                                size="small"
                                                value={job[salaryKey].period}
                                                onChange={(e) => {
                                                    const updated = [...experience];
                                                    updated[jobIdx] = {
                                                        ...updated[jobIdx],
                                                        [salaryKey]: {
                                                            ...job[salaryKey],
                                                            period: e.target.value,
                                                        },
                                                    };
                                                    onChange(updated);
                                                }}
                                                sx={{ minWidth: 120 }}
                                            >
                                                {SALARY_PERIODS.map((p) => (
                                                    <MenuItem key={p.value} value={p.value}>
                                                        {p.label}
                                                    </MenuItem>
                                                ))}
                                            </TextField>
                                        </Box>
                                    ))}
                                </Box>
                            </Box>

                            {/* Bullets */}
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
                                            const updated = [...experience];
                                            updated[jobIdx] = {
                                                ...updated[jobIdx],
                                                bullets: arrayMove(job.bullets, oldIndex, newIndex),
                                            };
                                            onChange(updated);
                                        }
                                    }}
                                >
                                    <SortableContext items={bulletIds} strategy={verticalListSortingStrategy}>
                                        {job.bullets.map((bullet, bulletIdx) => (
                                            <SortableBullet
                                                key={bulletIds[bulletIdx]}
                                                id={bulletIds[bulletIdx]}
                                                value={bullet}
                                                onChange={(val) => {
                                                    const updated = [...experience];
                                                    const bullets = [...job.bullets];
                                                    bullets[bulletIdx] = val;
                                                    updated[jobIdx] = { ...updated[jobIdx], bullets };
                                                    onChange(updated);
                                                }}
                                                onDelete={() => {
                                                    const updated = [...experience];
                                                    updated[jobIdx] = {
                                                        ...updated[jobIdx],
                                                        bullets: job.bullets.filter((_, i) => i !== bulletIdx),
                                                    };
                                                    onChange(updated);
                                                }}
                                            />
                                        ))}
                                    </SortableContext>
                                </DndContext>
                                <Button
                                    size="small"
                                    onClick={() => {
                                        const updated = [...experience];
                                        updated[jobIdx] = { ...updated[jobIdx], bullets: [...job.bullets, ''] };
                                        onChange(updated);
                                    }}
                                >
                                    + Add Bullet
                                </Button>
                            </Box>
                        </Box>
                    );
                })}

                <ReorderModal
                    open={reorderOpen}
                    onClose={() => setReorderOpen(false)}
                    title="Reorder Work Experience"
                    items={experience.map((job, i) => ({
                        id: `job-${i}`,
                        label: `${job.jobTitle || '(no title)'} @ ${job.company || '(no company)'}`,
                    }))}
                    onReorder={handleReorder}
                />
            </CardContent>
        </Card>
    );
}
