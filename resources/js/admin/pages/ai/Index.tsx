import { Head } from '@inertiajs/react';
import MemoryIcon from '@mui/icons-material/Memory';
import PsychologyIcon from '@mui/icons-material/Psychology';
import TrackChangesIcon from '@mui/icons-material/TrackChanges';
import AdminLayout from '../../layouts/AdminLayout';
import PageHeader from '../../components/PageHeader';
import NavGrid, { type NavBlock } from '../../components/NavGrid';

interface ServerNavBlock {
    href: string;
    icon: string;
    label: string;
    description: string;
}

interface AiIndexProps {
    navBlocks: ServerNavBlock[];
}

const iconMap: Record<string, React.ReactNode> = {
    Memory: <MemoryIcon fontSize="large" />,
    Psychology: <PsychologyIcon fontSize="large" />,
    TrackChanges: <TrackChangesIcon fontSize="large" />,
};

export default function AiIndex({ navBlocks }: AiIndexProps) {
    const blocks: NavBlock[] = navBlocks.map((b) => ({
        href: b.href,
        icon: iconMap[b.icon] ?? <MemoryIcon fontSize="large" />,
        label: b.label,
        description: b.description,
    }));

    return (
        <AdminLayout>
            <Head title="AI Tools" />
            <PageHeader title="AI Tools" backHref="/admin" backLabel="Back to Admin" />
            <NavGrid blocks={blocks} />
        </AdminLayout>
    );
}
