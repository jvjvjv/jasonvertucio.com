import { Head } from "@inertiajs/react";

import NavGrid, { type NavBlock } from "../../components/NavGrid";
import PageHeader from "../../components/PageHeader";
import AdminLayout from "../../layouts/AdminLayout";
import { getIcon } from "../../utils/iconRegistry";

interface ServerNavBlock {
    href: string;
    icon: string;
    label: string;
    description: string;
    external?: boolean;
}

interface BlogHubProps {
    navBlocks: ServerNavBlock[];
}

export default function BlogHub({ navBlocks }: BlogHubProps) {
    const blocks: NavBlock[] = navBlocks.map((b) => ({
        ...b,
        icon: getIcon(b.icon),
    }));

    return (
        <AdminLayout>
            <Head title="Blog" />
            <PageHeader
                title="Blog"
                backHref="/admin"
                backLabel="Back to Admin"
            />
            <NavGrid blocks={blocks} />
        </AdminLayout>
    );
}
