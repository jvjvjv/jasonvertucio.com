export interface AdminNavigationItem {
    slug: string;
    label: string;
}

export const ADMIN_NAVIGATION_ITEMS: AdminNavigationItem[] = [
    {
        slug: "/admin",
        label: "Admin Dashboard",
    },
    {
        slug: "/admin/ai",
        label: "AI Tools",
    },
    {
        slug: "/admin/site-settings",
        label: "Site Navigation",
    },
];
