import Box from "@mui/material/Box";
import ButtonBase from "@mui/material/ButtonBase";

import LogoutForm from "./LogoutForm";
import { currentDestination, linkComponent, type NavArea } from "./navHelpers";
import { navRowSx } from "./navRowSx";

import { border, color, font, typeScale } from "@/admin/theme/tokens";

interface NavRowProps {
    href: string;
    label: string;
    external?: boolean;
    level: "area" | "page";
    current: boolean;
    onNavigate?: () => void;
}

function NavRow({
    href,
    label,
    external,
    level,
    current,
    onNavigate,
}: NavRowProps) {
    return (
        <ButtonBase
            component={linkComponent(external)}
            href={href}
            aria-current={current ? "page" : undefined}
            onClick={onNavigate}
            sx={navRowSx({ level, current })}
        >
            {label}
        </ButtonBase>
    );
}

interface NavListProps {
    areas: NavArea[];
    currentPath: string;
    onNavigate?: () => void;
}

const listSx = { listStyle: "none", m: 0, p: 0 };

/**
 * The navigation's contents, for both the sidebar and the narrow-screen panel.
 * There is nothing to expand or collapse: choosing an area opens its page, and
 * the current path alone decides whose pages are listed.
 */
export default function NavList({
    areas,
    currentPath,
    onNavigate,
}: NavListProps) {
    const current = currentDestination(currentPath, areas);

    return (
        <Box
            sx={{
                display: "flex",
                flexDirection: "column",
                minHeight: "100%",
                bgcolor: color.blue,
            }}
        >
            <Box
                component="a"
                href="/"
                sx={{
                    display: "block",
                    px: 2.5,
                    pt: 2.5,
                    pb: 2,
                    color: color.onBlue,
                    fontFamily: font.heading,
                    fontSize: typeScale.title,
                    fontWeight: 700,
                    lineHeight: 1.2,
                    textDecoration: "none",
                    "&:focus-visible": {
                        outline: border.focusOnBlue,
                        outlineOffset: -3,
                    },
                }}
            >
                Jason Vertucio
            </Box>
            <Box component="nav" aria-label="Admin" sx={{ flexGrow: 1 }}>
                <Box component="ul" sx={listSx}>
                    {areas.map((area) => (
                        <li key={area.href}>
                            <NavRow
                                href={area.href}
                                label={area.label}
                                external={area.external}
                                level="area"
                                current={current?.href === area.href}
                                onNavigate={onNavigate}
                            />
                            {current?.areaHref === area.href &&
                                area.pages.length > 0 && (
                                    <Box
                                        component="ul"
                                        sx={{ ...listSx, pb: 1 }}
                                    >
                                        {area.pages.map((page) => (
                                            <li key={page.href}>
                                                <NavRow
                                                    href={page.href}
                                                    label={page.label}
                                                    external={page.external}
                                                    level="page"
                                                    current={
                                                        current.href ===
                                                        page.href
                                                    }
                                                    onNavigate={onNavigate}
                                                />
                                            </li>
                                        ))}
                                    </Box>
                                )}
                        </li>
                    ))}
                </Box>
            </Box>
            <Box
                sx={{
                    py: 1,
                    borderTop: `1px solid ${color.onBlueHover}`,
                }}
            >
                <LogoutForm />
            </Box>
        </Box>
    );
}
