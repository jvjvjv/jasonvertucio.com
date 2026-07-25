import { Link as InertiaLink } from "@inertiajs/react";

import type { AppBarItem } from "@/types";

/**
 * Blade-rendered routes must use a plain anchor: an Inertia visit to them
 * returns HTML without the `x-inertia` header, which Inertia displays in an
 * error-modal iframe instead of navigating.
 */
export function linkComponent(external?: boolean) {
    return external ? "a" : InertiaLink;
}

export function isPathActive(currentPath: string, item: AppBarItem): boolean {
    return (
        currentPath === item.href ||
        currentPath.startsWith(`${item.href}/`) ||
        item.children.some(
            (c) =>
                currentPath === c.href || currentPath.startsWith(`${c.href}/`),
        )
    );
}
