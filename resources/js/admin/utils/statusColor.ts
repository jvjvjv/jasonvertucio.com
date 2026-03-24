export type ChipColor = 'success' | 'warning' | 'error' | 'default';

export function statusColor(status: string): ChipColor {
    switch (status) {
        case 'finalized':
        case 'completed':
            return 'success';
        case 'active':
            return 'warning';
        case 'pass':
            return 'error';
        default:
            return 'default';
    }
}
