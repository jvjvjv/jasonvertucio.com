import type { ChipColor } from "@/admin/utils/statusColor";

export type IntelStatus = "queued" | "running" | "ready" | "failed";

export interface CompanyInsightGroup {
    category: string;
    label: string;
    items: {
        id: number;
        title: string;
        content: string;
        source_url: string | null;
    }[];
}

export interface IntelCompany {
    id: number;
    name: string;
    website_url: string | null;
    website_domain: string | null;
    headquarters: string | null;
    employee_count: string | null;
    revenue: string | null;
    industry: string | null;
    founded_year: number | null;
    ownership: string | null;
    ticker: string | null;
    summary: string | null;
    researched_at: string | null;
    insights: CompanyInsightGroup[];
}

export interface RoleFindings {
    summary: string | null;
    responsibilities: string | null;
    requirements: string | null;
    team: string | null;
    compensation: string | null;
    interview_process: string | null;
    concerns: string | null;
}

export interface RoleIntel {
    id: number;
    status: IntelStatus;
    failure_reason: string | null;
    application: {
        id: number;
        company_name: string | null;
        position: string | null;
        status: string;
    } | null;
    company: IntelCompany | null;
    role: RoleFindings;
    ai_system_name: string | null;
    updated_at: string | null;
}

export interface IntelSystemOption {
    id: number;
    name: string;
    model: string;
}

export interface CompanyOption {
    id: number;
    name: string;
    website_domain: string | null;
}

export const INTEL_API = "/api/admin/employment/company-intel";
export const INTEL_PAGES = "/admin/employment/company-intel";

export const INTEL_STATUS_LABELS: { [status in IntelStatus]: string } = {
    queued: "Queued",
    running: "Researching",
    ready: "Ready",
    failed: "Failed",
};

export const INTEL_STATUS_COLORS: { [status: string]: ChipColor } = {
    queued: "default",
    running: "info",
    ready: "success",
    failed: "error",
};

export function isIntelInProgress(status: string): boolean {
    return status === "queued" || status === "running";
}
