import Chip from '@mui/material/Chip';
import { statusColor } from '../utils/statusColor';
import type { ChipColor } from '../utils/statusColor';

interface StatusChipProps {
    status: string;
    label?: string;
    colorMap?: Record<string, ChipColor>;
    variant?: 'outlined' | 'filled';
    size?: 'small' | 'medium';
}

export default function StatusChip({
    status,
    label,
    colorMap,
    variant = 'outlined',
    size = 'small',
}: StatusChipProps) {
    const color = colorMap?.[status] ?? statusColor(status);

    return (
        <Chip
            label={label ?? status}
            size={size}
            color={color}
            variant={variant}
        />
    );
}
