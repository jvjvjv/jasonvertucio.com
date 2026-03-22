import { useRef } from 'react';
import Box from '@mui/material/Box';
import Chip from '@mui/material/Chip';

interface SkillsInputProps {
    skills: string[];
    onChange: (skills: string[]) => void;
}

export default function SkillsInput({ skills, onChange }: SkillsInputProps) {
    const inputRef = useRef<HTMLInputElement>(null);

    const addSkill = (value: string) => {
        const trimmed = value.replace(/,/g, '').trim();
        if (trimmed === '') return;
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

    const handleKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
        const input = e.currentTarget;
        if (e.key === 'Enter' || e.key === ',') {
            e.preventDefault();
            addSkill(input.value);
            input.value = '';
        } else if (e.key === 'Backspace' && input.value === '' && skills.length > 0) {
            onChange(skills.slice(0, -1));
        }
    };

    const handlePaste = (e: React.ClipboardEvent<HTMLInputElement>) => {
        e.preventDefault();
        const pasted = e.clipboardData.getData('text');
        const newSkills = pasted
            .split(',')
            .map((s) => s.trim())
            .filter((s) => s !== '');
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
        if (inputRef.current) inputRef.current.value = '';
    };

    return (
        <Box
            onClick={() => inputRef.current?.focus()}
            sx={{
                display: 'flex',
                flexWrap: 'wrap',
                alignItems: 'center',
                gap: 0.5,
                p: 1,
                border: 1,
                borderColor: 'divider',
                borderRadius: 1,
                minHeight: 42,
                cursor: 'text',
                '&:focus-within': {
                    borderColor: 'primary.main',
                    borderWidth: 2,
                    p: '7px',
                },
            }}
        >
            {skills.map((skill, idx) => (
                <Chip
                    key={idx}
                    label={skill}
                    size="small"
                    color="primary"
                    variant="outlined"
                    onDelete={() => removeSkill(idx)}
                />
            ))}
            <Box
                component="input"
                ref={inputRef}
                placeholder={skills.length === 0 ? 'Type skill, press comma or Enter...' : ''}
                onKeyDown={handleKeyDown}
                onPaste={handlePaste}
                sx={{
                    flex: 1,
                    minWidth: 150,
                    border: 'none',
                    outline: 'none',
                    fontSize: '0.875rem',
                    py: 0.5,
                    px: 0.5,
                    bgcolor: 'transparent',
                }}
            />
        </Box>
    );
}
