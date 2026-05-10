import { Head } from "@inertiajs/react";
import CodeIcon from "@mui/icons-material/Code";
import EditNoteIcon from "@mui/icons-material/EditNote";
import TrackChangesIcon from "@mui/icons-material/TrackChanges";
import VisibilityIcon from "@mui/icons-material/Visibility";

import NavGrid, { type NavBlock } from "../../components/NavGrid";
import PageHeader from "../../components/PageHeader";
import AdminLayout from "../../layouts/AdminLayout";

import type { ReactNode } from "react";

interface ServerNavBlock {
    href: string;
    icon: string;
    label: string;
    description: string;
}

interface ResumeHubProps {
    navBlocks: ServerNavBlock[];
}

const iconMap: { [key: string]: ReactNode } = {
    EditNote: <EditNoteIcon fontSize="large" />,
    TrackChanges: <TrackChangesIcon fontSize="large" />,
    Visibility: <VisibilityIcon fontSize="large" />,
    Code: <CodeIcon fontSize="large" />,
};

export default function ResumeHub({ navBlocks }: ResumeHubProps) {
    const blocks: NavBlock[] = navBlocks.map((b) => ({
        href: b.href,
        icon: iconMap[b.icon] ?? <EditNoteIcon fontSize="large" />,
        label: b.label,
        description: b.description,
    }));

    return (
        <AdminLayout>
            <Head title="Resume Management" />
            <PageHeader
                title="Resume Management"
                backHref="/admin"
                backLabel="Back to Admin"
            />
            <NavGrid blocks={blocks} />
        </AdminLayout>
    );
}
