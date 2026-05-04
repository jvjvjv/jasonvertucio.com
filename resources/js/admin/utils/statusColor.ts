export type ChipColor = 'primary' | 'secondary' |'success' | 'warning' | 'error' | 'info' | 'default';

export function statusColor(status: string): ChipColor {
    switch (status) {
        case 'finalized':
            return 'primary';
        case 'applied':
        case 'completed':
            return 'success';
        case 'active':
            return 'info';
        case 'pass':
            return 'secondary';
        default:
            return 'default';
    }
}
