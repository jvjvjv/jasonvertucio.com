import { Head } from '@inertiajs/react';
import DescriptionIcon from '@mui/icons-material/Description';
import DriveFileRenameOutlineIcon from '@mui/icons-material/DriveFileRenameOutline';
import SmartToyIcon from '@mui/icons-material/SmartToy';
import PushPinIcon from '@mui/icons-material/PushPin';
import InboxIcon from '@mui/icons-material/Inbox';
import AdminLayout from '../layouts/AdminLayout';
import NavGrid, { type NavBlock } from '../components/NavGrid';

interface ServerNavBlock {
    href: string;
    icon: string;
    label: string;
    description: string;
}

interface DashboardProps {
    navBlocks: ServerNavBlock[];
}

const iconMap: Record<string, React.ReactNode> = {
    Description: <DescriptionIcon fontSize="large" />,
    DriveFileRenameOutline: <DriveFileRenameOutlineIcon fontSize="large" />,
    SmartToy: <SmartToyIcon fontSize="large" />,
    PushPin: <PushPinIcon fontSize="large" />,
    Inbox: <InboxIcon fontSize="large" />,
};

export default function Dashboard({ navBlocks }: DashboardProps) {
    const blocks: NavBlock[] = navBlocks.map((b) => ({
        href: b.href,
        icon: iconMap[b.icon] ?? <DescriptionIcon fontSize="large" />,
        label: b.label,
        description: b.description,
    }));

    return (
        <AdminLayout title="Admin Dashboard">
            <Head title="Admin Dashboard" />
            <NavGrid blocks={blocks} />
        </AdminLayout>
    );
}
