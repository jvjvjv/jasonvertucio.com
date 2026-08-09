import { Head } from "@inertiajs/react";

import NavGrid, { type NavBlock } from "../components/NavGrid";
import AdminLayout from "../layouts/AdminLayout";
import { getIcon } from "../utils/iconRegistry";

interface ServerNavBlock {
    href: string;
    icon: string;
    label: string;
    description: string;
    external?: boolean;
}

interface DashboardProps {
    navBlocks: ServerNavBlock[];
}

export default function Dashboard({ navBlocks }: DashboardProps) {
    const blocks: NavBlock[] = navBlocks.map((b) => ({
        ...b,
        icon: getIcon(b.icon),
    }));

    return (
        <AdminLayout title="Admin Dashboard">
            <Head title="Admin Dashboard" />
            <NavGrid blocks={blocks} />
        </AdminLayout>
    );
}
