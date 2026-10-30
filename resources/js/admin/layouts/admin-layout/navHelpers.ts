import { Link as InertiaLink } from "@inertiajs/react";

import type { AppBarItem, AppBarNavChild } from "@/types";

const DASHBOARD_HREF = "/admin";

export interface NavArea {
    href: string;
    label: string;
    external?: boolean;
    pages: AppBarNavChild[];
}

export interface CurrentDestination {
    href: string;
    areaHref: string;
}

/**
 * Blade-rendered routes must use a plain anchor: an Inertia visit to them
 * returns HTML without the `x-inertia` header, which Inertia displays in an
 * error-modal iframe instead of navigating.
 */
export function linkComponent(external?: boolean) {
    return external ? "a" : InertiaLink;
}

/**
 * Each entry is an area, with its children as its pages. The dashboard's
 * children are the other areas again, so it has no pages of its own.
 */
export function buildNavAreas(adminNav: AppBarItem[]): NavArea[] {
    return adminNav.map((item) => ({
        ...item,
        pages: item.href === DASHBOARD_HREF ? [] : item.children,
    }));
}

function matches(path: string, href: string): boolean {
    if (href === DASHBOARD_HREF) {
        return path === href;
    }

    return path === href || path.startsWith(`${href}/`);
}

/**
 * The one destination whose address most specifically matches the path, so
 * `/admin/resume/applications/new` is Applications, not Resume Management at
 * `/admin/resume`.
 */
export function currentDestination(
    path: string,
    areas: NavArea[],
): CurrentDestination | null {
    let current: CurrentDestination | null = null;

    for (const area of areas) {
        for (const { href } of [area, ...area.pages]) {
            if (
                matches(path, href) &&
                href.length > (current?.href.length ?? -1)
            ) {
                current = { href, areaHref: area.href };
            }
        }
    }

    return current;
}
