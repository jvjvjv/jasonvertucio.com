// --- Type definitions ---

export interface Salary {
    amount: number | null;
    period: string;
}

export interface Job {
    jobTitle: string;
    jobTitleLabel: string;
    company: string;
    location: string;
    dates: [string, string];
    bullets: string[];
    salaryStart: Salary;
    salaryEnd: Salary;
    isFreelance: boolean;
}

export interface SkillCategory {
    title: string;
    list: string[];
}

export interface Education {
    institution: string;
    location: string;
    degree: string;
    level: string;
    dates: [string, string];
    description: string;
}

export interface Project {
    projectName: string;
    description: string;
    bullets: string[];
}

export interface Personal {
    name: string;
    title: string;
    email: string;
    phone: string;
    linkedin: string;
    url: string;
    summary: string;
}

export interface ResumeData {
    personal: Personal;
    skills: { top: SkillCategory[]; other: SkillCategory[] };
    experience: Job[];
    education: Education[];
    projects: Project[];
}

export interface AvailableVersion {
    version: string;
    created: number;
}

export interface EditorProps {
    data: ResumeData;
    version: string;
    docxExists: boolean;
    availableVersions: AvailableVersion[];
    mailConfigured: boolean;
    notificationRecipientCount: number;
}

// --- Helper: reorder array items ---
export function moveItem<T>(arr: T[], index: number, direction: -1 | 1): T[] {
    const newArr = [...arr];
    const newIndex = index + direction;
    if (newIndex < 0 || newIndex >= newArr.length) return newArr;
    [newArr[index], newArr[newIndex]] = [newArr[newIndex], newArr[index]];
    return newArr;
}

export const SALARY_PERIODS = [
    { value: "", label: "Period" },
    { value: "per_hour", label: "per hour" },
    { value: "per_month", label: "per month" },
    { value: "per_year", label: "per year" },
];
