import OpenInNewIcon from "@mui/icons-material/OpenInNew";
import Box from "@mui/material/Box";
import Card from "@mui/material/Card";
import CardContent from "@mui/material/CardContent";
import Divider from "@mui/material/Divider";
import Grid from "@mui/material/Grid";
import Link from "@mui/material/Link";
import Typography from "@mui/material/Typography";
import { memo } from "react";

import type { IntelCompany, RoleFindings } from "./types";
import type { ReactNode } from "react";

import MarkdownContent from "@/components/chat-message-bubble/MarkdownContent";

const ROLE_SECTIONS: { key: keyof RoleFindings; label: string }[] = [
    { key: "summary", label: "Summary" },
    { key: "responsibilities", label: "Responsibilities" },
    { key: "requirements", label: "What it asks for" },
    { key: "team", label: "Team and reporting" },
    { key: "compensation", label: "Compensation" },
    { key: "interview_process", label: "Interview process" },
    { key: "concerns", label: "Worth asking about" },
];

const OWNERSHIP_LABELS: { [key: string]: string } = {
    public: "Public",
    private: "Private",
    nonprofit: "Nonprofit",
    government: "Government",
};

/** Markdown from the agent, sized for reading inside a card. */
const markdownSx = {
    typography: "body2",
    "& p": { mt: 0, mb: 1 },
    "& p:last-child": { mb: 0 },
    "& ul, & ol": { mt: 0, mb: 1, pl: 3 },
    "& li": { mb: 0.5 },
    "& > div > :last-child": { mb: 0 },
} as const;

/**
 * The URL only when it is http(s): these links come from the research
 * agent, so anything else (a `javascript:` URL) is not rendered as a link.
 */
function safeHref(url: string | null): string | null {
    if (url === null) {
        return null;
    }
    try {
        const parsed = new URL(url);
        return parsed.protocol === "http:" || parsed.protocol === "https:"
            ? parsed.href
            : null;
    } catch {
        return null;
    }
}

function hostOf(url: string): string {
    try {
        return new URL(url).hostname.replace(/^www\./, "");
    } catch {
        return url;
    }
}

function Fact({ label, value }: { label: string; value: ReactNode }) {
    return (
        <Box>
            <Typography variant="caption" color="text.secondary" component="dt">
                {label}
            </Typography>
            <Typography variant="body2" component="dd" sx={{ m: 0 }}>
                {value ?? (
                    <Box component="span" sx={{ color: "text.disabled" }}>
                        Unknown
                    </Box>
                )}
            </Typography>
        </Box>
    );
}

function CompanyCard({ company }: { company: IntelCompany | null }) {
    if (company === null) {
        return (
            <Card variant="outlined">
                <CardContent>
                    <Typography variant="h6" component="h2" sx={{ mb: 1 }}>
                        Company
                    </Typography>
                    <Typography variant="body2" color="text.secondary">
                        The company has not been identified yet.
                    </Typography>
                </CardContent>
            </Card>
        );
    }

    const ownership =
        company.ownership !== null
            ? `${OWNERSHIP_LABELS[company.ownership] ?? company.ownership}${company.ticker ? ` (${company.ticker})` : ""}`
            : null;
    const websiteHref = safeHref(company.website_url);

    return (
        <Card variant="outlined">
            <CardContent>
                <Box
                    sx={{
                        display: "flex",
                        alignItems: "baseline",
                        justifyContent: "space-between",
                        gap: 2,
                        flexWrap: "wrap",
                        mb: 2,
                    }}
                >
                    <Typography variant="h5" component="h2" fontWeight="bold">
                        {company.name}
                    </Typography>
                    {websiteHref !== null ? (
                        <Link
                            href={websiteHref}
                            target="_blank"
                            rel="noopener noreferrer"
                            variant="body2"
                            underline="hover"
                            sx={{
                                display: "inline-flex",
                                alignItems: "center",
                                gap: 0.5,
                            }}
                        >
                            {company.website_domain ?? hostOf(websiteHref)}
                            <OpenInNewIcon sx={{ fontSize: 14 }} />
                        </Link>
                    ) : null}
                </Box>

                <Box
                    component="dl"
                    sx={{
                        display: "grid",
                        gridTemplateColumns: {
                            xs: "repeat(2, minmax(0, 1fr))",
                            sm: "repeat(3, minmax(0, 1fr))",
                        },
                        gap: 2,
                        m: 0,
                        mb: company.summary ? 2 : 0,
                    }}
                >
                    <Fact label="Headquarters" value={company.headquarters} />
                    <Fact label="Employees" value={company.employee_count} />
                    <Fact label="Revenue" value={company.revenue} />
                    <Fact label="Industry" value={company.industry} />
                    <Fact label="Founded" value={company.founded_year} />
                    <Fact label="Ownership" value={ownership} />
                </Box>

                {company.summary ? (
                    <Box sx={markdownSx}>
                        <MarkdownContent content={company.summary} />
                    </Box>
                ) : null}

                {company.insights.length > 0 ? (
                    <>
                        <Divider sx={{ my: 3 }} />
                        <Box sx={{ display: "grid", gap: 3 }}>
                            {company.insights.map((group) => (
                                <Box component="section" key={group.category}>
                                    <Typography
                                        variant="subtitle2"
                                        component="h3"
                                        color="primary"
                                        sx={{ mb: 1 }}
                                    >
                                        {group.label}
                                    </Typography>
                                    <Box sx={{ display: "grid", gap: 2 }}>
                                        {group.items.map((insight) => {
                                            const sourceHref = safeHref(
                                                insight.source_url,
                                            );

                                            return (
                                                <Box key={insight.id}>
                                                    <Typography
                                                        variant="body2"
                                                        fontWeight={600}
                                                        sx={{ mb: 0.5 }}
                                                    >
                                                        {insight.title}
                                                    </Typography>
                                                    <Box sx={markdownSx}>
                                                        <MarkdownContent
                                                            content={
                                                                insight.content
                                                            }
                                                        />
                                                    </Box>
                                                    {sourceHref !== null ? (
                                                        <Link
                                                            href={sourceHref}
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            variant="caption"
                                                            underline="hover"
                                                            color="text.secondary"
                                                        >
                                                            Source:{" "}
                                                            {hostOf(sourceHref)}
                                                        </Link>
                                                    ) : null}
                                                </Box>
                                            );
                                        })}
                                    </Box>
                                </Box>
                            ))}
                        </Box>
                    </>
                ) : null}
            </CardContent>
        </Card>
    );
}

function RoleCard({
    role,
    position,
}: {
    role: RoleFindings;
    position: string | null;
}) {
    const sections = ROLE_SECTIONS.filter(
        ({ key }) => (role[key] ?? "").trim() !== "",
    );

    return (
        <Card variant="outlined">
            <CardContent>
                <Typography
                    variant="h6"
                    component="h2"
                    sx={{ mb: sections.length === 0 ? 1 : 2 }}
                >
                    {position ?? "The role"}
                </Typography>
                {sections.length === 0 ? (
                    <Typography variant="body2" color="text.secondary">
                        Nothing has been recorded about the role yet.
                    </Typography>
                ) : (
                    <Box sx={{ display: "grid", gap: 2.5 }}>
                        {sections.map(({ key, label }) => (
                            <Box component="section" key={key}>
                                <Typography
                                    variant="subtitle2"
                                    component="h3"
                                    color="primary"
                                    sx={{ mb: 0.5 }}
                                >
                                    {label}
                                </Typography>
                                <Box sx={markdownSx}>
                                    <MarkdownContent
                                        content={role[key] ?? ""}
                                    />
                                </Box>
                            </Box>
                        ))}
                    </Box>
                )}
            </CardContent>
        </Card>
    );
}

interface IntelFindingsProps {
    company: IntelCompany | null;
    role: RoleFindings;
    position: string | null;
}

/**
 * What the research recorded: the company on the left, the role beside it,
 * stacked on a phone.
 */
export default memo(function IntelFindings({
    company,
    role,
    position,
}: IntelFindingsProps) {
    return (
        <Grid container spacing={3}>
            <Grid size={{ xs: 12, md: 7 }}>
                <CompanyCard company={company} />
            </Grid>
            <Grid size={{ xs: 12, md: 5 }}>
                <RoleCard role={role} position={position} />
            </Grid>
        </Grid>
    );
});
