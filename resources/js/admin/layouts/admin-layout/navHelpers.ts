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
 * The dashboard's tiles are the navigation's areas, in tile order. Its tiles
 * include areas with no pages of their own, such as Mail Preview, which appear
 * nowhere else in `adminNav`.
 */
export function buildNavAreas(adminNav: AppBarItem[]): NavArea[] {
    const dashboard = adminNav.find((item) => item.href === DASHBOARD_HREF);
    const others = adminNav.filter((item) => item !== dashboard);
    const tiles = dashboard?.children ?? [];
    const tileHrefs = new Set(tiles.map((tile) => tile.href));

    return [
        ...(dashboard ? [{ ...dashboard, pages: [] }] : []),
        ...tiles.map((tile) => ({
            ...tile,
            pages:
                others.find((item) => item.href === tile.href)?.children ?? [],
        })),
        ...others
            .filter((item) => !tileHrefs.has(item.href))
            .map((item) => ({ ...item, pages: item.children })),
    ];
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
