export type ChipColor = 'primary' | 'secondary' |'success' | 'warning' | 'error' | 'info' | 'default';

export function statusColor(status: string): ChipColor {
    switch (status) {
        case 'finalized':
            return 'primary';
        case 'completed':
            return 'success';
        case 'active':
            return 'default';
        case 'pass':
            return 'info';
        default:
            return 'default';
    }
}
