import { Head } from "@inertiajs/react";
import DescriptionIcon from "@mui/icons-material/Description";
import DriveFileRenameOutlineIcon from "@mui/icons-material/DriveFileRenameOutline";
import InboxIcon from "@mui/icons-material/Inbox";
import PushPinIcon from "@mui/icons-material/PushPin";
import SmartToyIcon from "@mui/icons-material/SmartToy";

import NavGrid, { type NavBlock } from "../components/NavGrid";
import AdminLayout from "../layouts/AdminLayout";

import type { ReactNode } from "react";

interface ServerNavBlock {
    href: string;
    icon: string;
    label: string;
    description: string;
}

interface DashboardProps {
    navBlocks: ServerNavBlock[];
}

const iconMap: { [key: string]: ReactNode } = {
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
