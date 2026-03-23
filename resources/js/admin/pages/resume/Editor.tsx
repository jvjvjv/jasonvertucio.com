import { useState, useCallback } from 'react';
import { Head, router } from '@inertiajs/react';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Card from '@mui/material/Card';
import CardContent from '@mui/material/CardContent';
import Checkbox from '@mui/material/Checkbox';
import Chip from '@mui/material/Chip';
import Fab from '@mui/material/Fab';
import FormControlLabel from '@mui/material/FormControlLabel';
import IconButton from '@mui/material/IconButton';
import Menu from '@mui/material/Menu';
import MenuItem from '@mui/material/MenuItem';
import Tab from '@mui/material/Tab';
import Tabs from '@mui/material/Tabs';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';
import AdminLayout from '../../layouts/AdminLayout';
import PageHeader from '../../components/PageHeader';
import SkillsInput from '../../components/SkillsInput';
import DateInput from '../../components/DateInput';

// --- Type definitions ---

interface Salary {
    amount: number | null;
    period: string;
}

interface Job {
    jobTitle: string;
    company: string;
    location: string;
    dates: [string, string];
    bullets: string[];
    salaryStart: Salary;
    salaryEnd: Salary;
    isFreelance: boolean;
}

interface SkillCategory {
    title: string;
    list: string[];
}

interface Education {
    institution: string;
    degree: string;
    dates: [string, string];
    description: string;
}

interface Project {
    projectName: string;
    description: string;
    bullets: string[];
}

interface Personal {
    name: string;
    title: string;
    email: string;
    phone: string;
    linkedin: string;
    summary: string;
}

interface ResumeData {
    personal: Personal;
    skills: { top: SkillCategory[]; other: SkillCategory[] };
    experience: Job[];
    education: Education[];
    projects: Project[];
}

interface AvailableVersion {
    version: string;
    created: number;
}

interface EditorProps {
    data: ResumeData;
    version: string;
    docxExists: boolean;
    availableVersions: AvailableVersion[];
    mailConfigured: boolean;
    notificationRecipientCount: number;
}

// --- Helper: reorder array items ---
function moveItem<T>(arr: T[], index: number, direction: -1 | 1): T[] {
    const newArr = [...arr];
    const newIndex = index + direction;
    if (newIndex < 0 || newIndex >= newArr.length) return newArr;
    [newArr[index], newArr[newIndex]] = [newArr[newIndex], newArr[index]];
    return newArr;
}

const SALARY_PERIODS = [
    { value: '', label: 'Period' },
    { value: 'per_hour', label: 'per hour' },
    { value: 'per_month', label: 'per month' },
    { value: 'per_year', label: 'per year' },
];

// --- Component ---

export default function Editor({
    data: initialData,
    version: initialVersion,
    docxExists,
    availableVersions,
    mailConfigured,
    notificationRecipientCount,
}: EditorProps) {
    const [activeTab, setActiveTab] = useState(0);
    const [version, setVersion] = useState(initialVersion);
    const [data, setData] = useState<ResumeData>(() => {
        const d = { ...initialData };
        if (!d.skills || typeof d.skills !== 'object') {
            d.skills = { top: [], other: [] };
        } else {
            if (!Array.isArray(d.skills.top)) d.skills.top = [];
            if (!Array.isArray(d.skills.other)) d.skills.other = [];
        }
        if (!Array.isArray(d.experience)) d.experience = [];
        if (!Array.isArray(d.education)) d.education = [];
        if (!Array.isArray(d.projects)) d.projects = [];
        d.experience = d.experience.map((job) => ({
            ...job,
            dates: job.dates || ['', ''],
            bullets: job.bullets || [],
            salaryStart: job.salaryStart || { amount: null, period: '' },
            salaryEnd: job.salaryEnd || { amount: null, period: '' },
            isFreelance: job.isFreelance ?? false,
        }));
        d.education = d.education.map((edu) => ({
            ...edu,
            dates: edu.dates || ['', ''],
        }));
        d.projects = d.projects.map((proj) => ({
            ...proj,
            bullets: proj.bullets || [],
        }));
        return d as ResumeData;
    });
    const [saving, setSaving] = useState(false);
    const [errors, setErrors] = useState<string[]>([]);
    const [notifyRecipients, setNotifyRecipients] = useState(false);
    const [optionsAnchor, setOptionsAnchor] = useState<null | HTMLElement>(null);

    // --- Updaters ---

    const updatePersonal = useCallback((key: keyof Personal, value: string) => {
        setData((prev) => ({
            ...prev,
            personal: { ...prev.personal, [key]: value },
        }));
    }, []);

    const updateSkillCategories = useCallback(
        (type: 'top' | 'other', categories: SkillCategory[]) => {
            setData((prev) => ({
                ...prev,
                skills: { ...prev.skills, [type]: categories },
            }));
        },
        [],
    );

    const updateExperience = useCallback((experience: Job[]) => {
        setData((prev) => ({ ...prev, experience }));
    }, []);

    const updateEducation = useCallback((education: Education[]) => {
        setData((prev) => ({ ...prev, education }));
    }, []);

    const updateProjects = useCallback((projects: Project[]) => {
        setData((prev) => ({ ...prev, projects }));
    }, []);

    // --- Save ---

    const handleSave = async () => {
        setSaving(true);
        setErrors([]);

        try {
            const response = await fetch('/admin/resume/editor', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN':
                        document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify({
                    version,
                    data,
                    notify_recipients: notifyRecipients,
                }),
            });

            const result = await response.json();

            if (!response.ok) {
                if (result.errors) {
                    setErrors(Object.values(result.errors).flat() as string[]);
                } else {
                    setErrors([result.message || 'Failed to save']);
                }
                return;
            }

            router.reload();
        } catch (error) {
            setErrors(['Network error: ' + (error as Error).message]);
        } finally {
            setSaving(false);
        }
    };

    // --- Tab panels ---

    const tabLabels = ['Version', 'Personal', 'Skills', 'Experience', 'Projects', 'Education'];

    return (
        <AdminLayout>
            <Head title="Resume Builder" />
            <PageHeader title="Resume Builder" backHref="/admin/resume" backLabel="Back to Resume Management" />

            {errors.length > 0 && (
                <Card sx={{ mb: 2, bgcolor: 'error.light', color: 'error.contrastText' }}>
                    <CardContent sx={{ py: 1.5, '&:last-child': { pb: 1.5 } }}>
                        <ul style={{ margin: 0, paddingLeft: 20 }}>
                            {errors.map((err, i) => (
                                <li key={i}>{err}</li>
                            ))}
                        </ul>
                    </CardContent>
                </Card>
            )}

            <Tabs value={activeTab} onChange={(_, v) => setActiveTab(v)} sx={{ mb: 2 }}>
                {tabLabels.map((label) => (
                    <Tab key={label} label={label} />
                ))}
            </Tabs>

            {/* Version Tab */}
            {activeTab === 0 && (
                <Card>
                    <CardContent>
                        <Typography variant="h6" gutterBottom>
                            Version Information
                        </Typography>
                        <Box sx={{ display: 'grid', gap: 2, gridTemplateColumns: { xs: '1fr', md: '1fr 1fr' } }}>
                            <TextField
                                label="Version Number"
                                size="small"
                                value={version}
                                onChange={(e) => setVersion(e.target.value)}
                                placeholder="2026.1.0"
                                helperText="Format: YYYY.MAJOR.MINOR (e.g., 2026.1.0)"
                            />
                            <Box sx={{ display: 'flex', alignItems: 'center' }}>
                                <Chip
                                    label={docxExists ? 'DOCX exists for current version' : 'No DOCX for current version'}
                                    color={docxExists ? 'success' : 'warning'}
                                    variant="outlined"
                                />
                            </Box>
                        </Box>
                        {availableVersions.length > 0 && (
                            <Box sx={{ mt: 3 }}>
                                <Typography variant="body2" color="text.secondary" gutterBottom>
                                    Available Versions
                                </Typography>
                                <Box sx={{ display: 'flex', flexWrap: 'wrap', gap: 1 }}>
                                    {availableVersions.map((v) => (
                                        <Chip
                                            key={v.version}
                                            label={`${v.version} (${new Date(v.created * 1000).toLocaleDateString()})`}
                                            size="small"
                                            variant="outlined"
                                        />
                                    ))}
                                </Box>
                            </Box>
                        )}
                    </CardContent>
                </Card>
            )}

            {/* Personal Tab */}
            {activeTab === 1 && (
                <Card>
                    <CardContent>
                        <Typography variant="h6" gutterBottom>
                            Personal Information
                        </Typography>
                        <Box sx={{ display: 'grid', gap: 2, gridTemplateColumns: { xs: '1fr', md: '1fr 1fr' } }}>
                            <TextField
                                label="Full Name"
                                required
                                size="small"
                                value={data.personal.name ?? ''}
                                onChange={(e) => updatePersonal('name', e.target.value)}
                            />
                            <TextField
                                label="Professional Title"
                                required
                                size="small"
                                value={data.personal.title ?? ''}
                                onChange={(e) => updatePersonal('title', e.target.value)}
                            />
                            <TextField
                                label="Email"
                                required
                                size="small"
                                type="email"
                                value={data.personal.email ?? ''}
                                onChange={(e) => updatePersonal('email', e.target.value)}
                            />
                            <TextField
                                label="Phone"
                                size="small"
                                value={data.personal.phone ?? ''}
                                onChange={(e) => updatePersonal('phone', e.target.value)}
                            />
                            <TextField
                                label="LinkedIn URL"
                                size="small"
                                fullWidth
                                value={data.personal.linkedin ?? ''}
                                onChange={(e) => updatePersonal('linkedin', e.target.value)}
                                sx={{ gridColumn: { md: '1 / -1' } }}
                            />
                            <TextField
                                label="Professional Summary"
                                size="small"
                                fullWidth
                                multiline
                                rows={4}
                                value={data.personal.summary ?? ''}
                                onChange={(e) => updatePersonal('summary', e.target.value)}
                                sx={{ gridColumn: { md: '1 / -1' } }}
                            />
                        </Box>
                    </CardContent>
                </Card>
            )}

            {/* Skills Tab */}
            {activeTab === 2 && (
                <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
                    {(['top', 'other'] as const).map((type) => (
                        <Card key={type}>
                            <CardContent>
                                <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 2 }}>
                                    <Typography variant="h6">
                                        {type === 'top' ? 'Top Skills' : 'Other Skills'}
                                    </Typography>
                                    <Button
                                        size="small"
                                        onClick={() =>
                                            updateSkillCategories(type, [
                                                ...data.skills[type],
                                                { title: '', list: [] },
                                            ])
                                        }
                                    >
                                        + Add Category
                                    </Button>
                                </Box>
                                {data.skills[type].map((category, catIdx) => (
                                    <Box key={catIdx} sx={{ mb: 3, p: 2, bgcolor: 'grey.50', borderRadius: 1 }}>
                                        <Box sx={{ display: 'flex', alignItems: 'center', gap: 2, mb: 2 }}>
                                            <TextField
                                                size="small"
                                                placeholder="Category Title"
                                                value={category.title}
                                                onChange={(e) => {
                                                    const updated = [...data.skills[type]];
                                                    updated[catIdx] = { ...updated[catIdx], title: e.target.value };
                                                    updateSkillCategories(type, updated);
                                                }}
                                                sx={{ flex: 1 }}
                                                slotProps={{ input: { sx: { fontWeight: 'bold' } } }}
                                            />
                                            <IconButton
                                                color="error"
                                                size="small"
                                                onClick={() => {
                                                    const updated = data.skills[type].filter((_, i) => i !== catIdx);
                                                    updateSkillCategories(type, updated);
                                                }}
                                            >
                                                ✕
                                            </IconButton>
                                        </Box>
                                        <SkillsInput
                                            skills={category.list}
                                            onChange={(newList) => {
                                                const updated = [...data.skills[type]];
                                                updated[catIdx] = { ...updated[catIdx], list: newList };
                                                updateSkillCategories(type, updated);
                                            }}
                                        />
                                    </Box>
                                ))}
                            </CardContent>
                        </Card>
                    ))}
                </Box>
            )}

            {/* Experience Tab */}
            {activeTab === 3 && (
                <Card>
                    <CardContent>
                        <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 2 }}>
                            <Typography variant="h6">Work Experience</Typography>
                            <Button
                                size="small"
                                onClick={() =>
                                    updateExperience([
                                        ...data.experience,
                                        {
                                            jobTitle: '',
                                            company: '',
                                            location: '',
                                            dates: ['', ''],
                                            bullets: [''],
                                            salaryStart: { amount: null, period: '' },
                                            salaryEnd: { amount: null, period: '' },
                                            isFreelance: false,
                                        },
                                    ])
                                }
                            >
                                + Add Job
                            </Button>
                        </Box>
                        {data.experience.map((job, jobIdx) => (
                            <Box key={jobIdx} sx={{ mb: 3, p: 2, bgcolor: 'grey.50', borderRadius: 1 }}>
                                <Box sx={{ display: 'flex', justifyContent: 'space-between', mb: 2 }}>
                                    <Box sx={{ display: 'flex', gap: 0.5 }}>
                                        <IconButton
                                            size="small"
                                            disabled={jobIdx === 0}
                                            onClick={() => updateExperience(moveItem(data.experience, jobIdx, -1))}
                                        >
                                            ↑
                                        </IconButton>
                                        <IconButton
                                            size="small"
                                            disabled={jobIdx === data.experience.length - 1}
                                            onClick={() => updateExperience(moveItem(data.experience, jobIdx, 1))}
                                        >
                                            ↓
                                        </IconButton>
                                    </Box>
                                    <IconButton
                                        size="small"
                                        color="error"
                                        onClick={() => updateExperience(data.experience.filter((_, i) => i !== jobIdx))}
                                    >
                                        ✕
                                    </IconButton>
                                </Box>
                                <Box sx={{ display: 'grid', gap: 2, gridTemplateColumns: { xs: '1fr', md: '1fr 1fr' } }}>
                                    <TextField
                                        label="Job Title"
                                        required
                                        size="small"
                                        value={job.jobTitle}
                                        onChange={(e) => {
                                            const updated = [...data.experience];
                                            updated[jobIdx] = { ...updated[jobIdx], jobTitle: e.target.value };
                                            updateExperience(updated);
                                        }}
                                    />
                                    <TextField
                                        label="Company"
                                        required
                                        size="small"
                                        value={job.company}
                                        onChange={(e) => {
                                            const updated = [...data.experience];
                                            updated[jobIdx] = { ...updated[jobIdx], company: e.target.value };
                                            updateExperience(updated);
                                        }}
                                    />
                                    <TextField
                                        label="Location"
                                        size="small"
                                        value={job.location}
                                        onChange={(e) => {
                                            const updated = [...data.experience];
                                            updated[jobIdx] = { ...updated[jobIdx], location: e.target.value };
                                            updateExperience(updated);
                                        }}
                                    />
                                    <Box sx={{ display: 'flex', gap: 2, gridColumn: { md: '1 / -1' } }}>
                                        <DateInput
                                            label="Start Date"
                                            value={job.dates[0] ?? ''}
                                            onChange={(val) => {
                                                const updated = [...data.experience];
                                                updated[jobIdx] = {
                                                    ...updated[jobIdx],
                                                    dates: [val, job.dates[1] ?? ''],
                                                };
                                                updateExperience(updated);
                                            }}
                                        />
                                        <DateInput
                                            label="End Date"
                                            value={job.dates[1] ?? ''}
                                            allowPresent
                                            onChange={(val) => {
                                                const updated = [...data.experience];
                                                updated[jobIdx] = {
                                                    ...updated[jobIdx],
                                                    dates: [job.dates[0] ?? '', val],
                                                };
                                                updateExperience(updated);
                                            }}
                                        />
                                    </Box>
                                    <FormControlLabel
                                        control={
                                            <Checkbox
                                                checked={job.isFreelance}
                                                onChange={(e) => {
                                                    const updated = [...data.experience];
                                                    updated[jobIdx] = { ...updated[jobIdx], isFreelance: e.target.checked };
                                                    updateExperience(updated);
                                                }}
                                            />
                                        }
                                        label="Freelance / Contract"
                                        sx={{ gridColumn: { md: '1 / -1' } }}
                                    />
                                </Box>

                                {/* Salary (private) */}
                                <Box sx={{ mt: 2, pt: 2, borderTop: 1, borderColor: 'divider' }}>
                                    <Typography variant="caption" color="text.secondary" sx={{ display: 'block', mb: 1 }}>
                                        Salary data is private and will not appear on the public resume.
                                    </Typography>
                                    <Box sx={{ display: 'grid', gap: 2, gridTemplateColumns: { xs: '1fr', md: '1fr 1fr' } }}>
                                        {(['salaryStart', 'salaryEnd'] as const).map((salaryKey) => (
                                            <Box key={salaryKey} sx={{ display: 'flex', gap: 1 }}>
                                                <TextField
                                                    label={salaryKey === 'salaryStart' ? 'Starting Salary' : 'Ending Salary'}
                                                    type="number"
                                                    size="small"
                                                    value={job[salaryKey].amount ?? ''}
                                                    onChange={(e) => {
                                                        const updated = [...data.experience];
                                                        updated[jobIdx] = {
                                                            ...updated[jobIdx],
                                                            [salaryKey]: {
                                                                ...job[salaryKey],
                                                                amount: e.target.value ? parseFloat(e.target.value) : null,
                                                            },
                                                        };
                                                        updateExperience(updated);
                                                    }}
                                                    slotProps={{ htmlInput: { min: 0, step: 0.01 } }}
                                                    sx={{ flex: 1 }}
                                                />
                                                <TextField
                                                    select
                                                    size="small"
                                                    value={job[salaryKey].period}
                                                    onChange={(e) => {
                                                        const updated = [...data.experience];
                                                        updated[jobIdx] = {
                                                            ...updated[jobIdx],
                                                            [salaryKey]: {
                                                                ...job[salaryKey],
                                                                period: e.target.value,
                                                            },
                                                        };
                                                        updateExperience(updated);
                                                    }}
                                                    sx={{ minWidth: 120 }}
                                                >
                                                    {SALARY_PERIODS.map((p) => (
                                                        <MenuItem key={p.value} value={p.value}>
                                                            {p.label}
                                                        </MenuItem>
                                                    ))}
                                                </TextField>
                                            </Box>
                                        ))}
                                    </Box>
                                </Box>

                                {/* Bullets */}
                                <Box sx={{ mt: 2 }}>
                                    <Typography variant="body2" fontWeight={600} gutterBottom>
                                        Bullet Points
                                    </Typography>
                                    {job.bullets.map((bullet, bulletIdx) => (
                                        <Box key={bulletIdx} sx={{ display: 'flex', gap: 1, mb: 1 }}>
                                            <TextField
                                                size="small"
                                                fullWidth
                                                multiline
                                                rows={2}
                                                value={bullet}
                                                onChange={(e) => {
                                                    const updated = [...data.experience];
                                                    const bullets = [...job.bullets];
                                                    bullets[bulletIdx] = e.target.value;
                                                    updated[jobIdx] = { ...updated[jobIdx], bullets };
                                                    updateExperience(updated);
                                                }}
                                            />
                                            <IconButton
                                                size="small"
                                                color="error"
                                                onClick={() => {
                                                    const updated = [...data.experience];
                                                    updated[jobIdx] = {
                                                        ...updated[jobIdx],
                                                        bullets: job.bullets.filter((_, i) => i !== bulletIdx),
                                                    };
                                                    updateExperience(updated);
                                                }}
                                            >
                                                ✕
                                            </IconButton>
                                        </Box>
                                    ))}
                                    <Button
                                        size="small"
                                        onClick={() => {
                                            const updated = [...data.experience];
                                            updated[jobIdx] = { ...updated[jobIdx], bullets: [...job.bullets, ''] };
                                            updateExperience(updated);
                                        }}
                                    >
                                        + Add Bullet
                                    </Button>
                                </Box>
                            </Box>
                        ))}
                    </CardContent>
                </Card>
            )}

            {/* Projects Tab */}
            {activeTab === 4 && (
                <Card>
                    <CardContent>
                        <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 2 }}>
                            <Typography variant="h6">Selected Projects</Typography>
                            <Button
                                size="small"
                                onClick={() =>
                                    updateProjects([
                                        ...data.projects,
                                        { projectName: '', description: '', bullets: [''] },
                                    ])
                                }
                            >
                                + Add Project
                            </Button>
                        </Box>
                        {data.projects.map((project, projIdx) => (
                            <Box key={projIdx} sx={{ mb: 3, p: 2, bgcolor: 'grey.50', borderRadius: 1 }}>
                                <Box sx={{ display: 'flex', justifyContent: 'space-between', mb: 2 }}>
                                    <Box sx={{ display: 'flex', gap: 0.5 }}>
                                        <IconButton
                                            size="small"
                                            disabled={projIdx === 0}
                                            onClick={() => updateProjects(moveItem(data.projects, projIdx, -1))}
                                        >
                                            ↑
                                        </IconButton>
                                        <IconButton
                                            size="small"
                                            disabled={projIdx === data.projects.length - 1}
                                            onClick={() => updateProjects(moveItem(data.projects, projIdx, 1))}
                                        >
                                            ↓
                                        </IconButton>
                                    </Box>
                                    <IconButton
                                        size="small"
                                        color="error"
                                        onClick={() => updateProjects(data.projects.filter((_, i) => i !== projIdx))}
                                    >
                                        ✕
                                    </IconButton>
                                </Box>
                                <TextField
                                    label="Project Name"
                                    required
                                    size="small"
                                    fullWidth
                                    value={project.projectName}
                                    onChange={(e) => {
                                        const updated = [...data.projects];
                                        updated[projIdx] = { ...updated[projIdx], projectName: e.target.value };
                                        updateProjects(updated);
                                    }}
                                    sx={{ mb: 2 }}
                                />
                                <TextField
                                    label="Description"
                                    size="small"
                                    fullWidth
                                    multiline
                                    rows={2}
                                    value={project.description}
                                    onChange={(e) => {
                                        const updated = [...data.projects];
                                        updated[projIdx] = { ...updated[projIdx], description: e.target.value };
                                        updateProjects(updated);
                                    }}
                                    sx={{ mb: 2 }}
                                />
                                <Typography variant="body2" fontWeight={600} gutterBottom>
                                    Bullet Points
                                </Typography>
                                {project.bullets.map((bullet, bulletIdx) => (
                                    <Box key={bulletIdx} sx={{ display: 'flex', gap: 1, mb: 1 }}>
                                        <TextField
                                            size="small"
                                            fullWidth
                                            multiline
                                            rows={2}
                                            value={bullet}
                                            onChange={(e) => {
                                                const updated = [...data.projects];
                                                const bullets = [...project.bullets];
                                                bullets[bulletIdx] = e.target.value;
                                                updated[projIdx] = { ...updated[projIdx], bullets };
                                                updateProjects(updated);
                                            }}
                                        />
                                        <IconButton
                                            size="small"
                                            color="error"
                                            onClick={() => {
                                                const updated = [...data.projects];
                                                updated[projIdx] = {
                                                    ...updated[projIdx],
                                                    bullets: project.bullets.filter((_, i) => i !== bulletIdx),
                                                };
                                                updateProjects(updated);
                                            }}
                                        >
                                            ✕
                                        </IconButton>
                                    </Box>
                                ))}
                                <Button
                                    size="small"
                                    onClick={() => {
                                        const updated = [...data.projects];
                                        updated[projIdx] = { ...updated[projIdx], bullets: [...project.bullets, ''] };
                                        updateProjects(updated);
                                    }}
                                >
                                    + Add Bullet
                                </Button>
                            </Box>
                        ))}
                    </CardContent>
                </Card>
            )}

            {/* Education Tab */}
            {activeTab === 5 && (
                <Card>
                    <CardContent>
                        <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 2 }}>
                            <Typography variant="h6">Education</Typography>
                            <Button
                                size="small"
                                onClick={() =>
                                    updateEducation([
                                        ...data.education,
                                        { institution: '', degree: '', dates: ['', ''], description: '' },
                                    ])
                                }
                            >
                                + Add Education
                            </Button>
                        </Box>
                        {data.education.map((edu, eduIdx) => (
                            <Box key={eduIdx} sx={{ mb: 3, p: 2, bgcolor: 'grey.50', borderRadius: 1 }}>
                                <Box sx={{ display: 'flex', justifyContent: 'space-between', mb: 2 }}>
                                    <Box sx={{ display: 'flex', gap: 0.5 }}>
                                        <IconButton
                                            size="small"
                                            disabled={eduIdx === 0}
                                            onClick={() => updateEducation(moveItem(data.education, eduIdx, -1))}
                                        >
                                            ↑
                                        </IconButton>
                                        <IconButton
                                            size="small"
                                            disabled={eduIdx === data.education.length - 1}
                                            onClick={() => updateEducation(moveItem(data.education, eduIdx, 1))}
                                        >
                                            ↓
                                        </IconButton>
                                    </Box>
                                    <IconButton
                                        size="small"
                                        color="error"
                                        onClick={() => updateEducation(data.education.filter((_, i) => i !== eduIdx))}
                                    >
                                        ✕
                                    </IconButton>
                                </Box>
                                <Box sx={{ display: 'grid', gap: 2, gridTemplateColumns: { xs: '1fr', md: '1fr 1fr' } }}>
                                    <TextField
                                        label="Institution"
                                        required
                                        size="small"
                                        value={edu.institution}
                                        onChange={(e) => {
                                            const updated = [...data.education];
                                            updated[eduIdx] = { ...updated[eduIdx], institution: e.target.value };
                                            updateEducation(updated);
                                        }}
                                    />
                                    <TextField
                                        label="Degree"
                                        size="small"
                                        value={edu.degree}
                                        onChange={(e) => {
                                            const updated = [...data.education];
                                            updated[eduIdx] = { ...updated[eduIdx], degree: e.target.value };
                                            updateEducation(updated);
                                        }}
                                    />
                                    <Box sx={{ display: 'flex', gap: 2, gridColumn: { md: '1 / -1' } }}>
                                        <DateInput
                                            label="Start Date"
                                            value={edu.dates[0] ?? ''}
                                            onChange={(val) => {
                                                const updated = [...data.education];
                                                updated[eduIdx] = {
                                                    ...updated[eduIdx],
                                                    dates: [val, edu.dates[1] ?? ''],
                                                };
                                                updateEducation(updated);
                                            }}
                                        />
                                        <DateInput
                                            label="End Date"
                                            value={edu.dates[1] ?? ''}
                                            allowPresent
                                            onChange={(val) => {
                                                const updated = [...data.education];
                                                updated[eduIdx] = {
                                                    ...updated[eduIdx],
                                                    dates: [edu.dates[0] ?? '', val],
                                                };
                                                updateEducation(updated);
                                            }}
                                        />
                                    </Box>
                                    <TextField
                                        label="Description"
                                        size="small"
                                        fullWidth
                                        multiline
                                        rows={2}
                                        value={edu.description}
                                        onChange={(e) => {
                                            const updated = [...data.education];
                                            updated[eduIdx] = { ...updated[eduIdx], description: e.target.value };
                                            updateEducation(updated);
                                        }}
                                        sx={{ gridColumn: { md: '1 / -1' } }}
                                    />
                                </Box>
                            </Box>
                        ))}
                    </CardContent>
                </Card>
            )}

            {/* FAB Save with Options */}
            <Box sx={{ position: 'fixed', bottom: 32, right: 32, display: 'flex', flexDirection: 'column', alignItems: 'flex-end', gap: 1 }}>
                <Button
                    variant="outlined"
                    size="small"
                    onClick={(e) => setOptionsAnchor(e.currentTarget)}
                    sx={{ bgcolor: 'background.paper' }}
                >
                    Options
                </Button>
                <Menu
                    anchorEl={optionsAnchor}
                    open={Boolean(optionsAnchor)}
                    onClose={() => setOptionsAnchor(null)}
                    anchorOrigin={{ vertical: 'top', horizontal: 'right' }}
                    transformOrigin={{ vertical: 'bottom', horizontal: 'right' }}
                >
                    <MenuItem disableRipple sx={{ '&:hover': { bgcolor: 'transparent' } }}>
                        <FormControlLabel
                            control={
                                <Checkbox
                                    checked={notifyRecipients}
                                    onChange={(e) => setNotifyRecipients(e.target.checked)}
                                    disabled={!mailConfigured}
                                    size="small"
                                />
                            }
                            label={
                                <Box>
                                    <Typography variant="body2">Notify share code recipients</Typography>
                                    {!mailConfigured && (
                                        <Typography variant="caption" color="text.secondary">
                                            (mail not configured)
                                        </Typography>
                                    )}
                                    {mailConfigured && notificationRecipientCount > 0 && (
                                        <Typography variant="caption" color="primary">
                                            Will notify {notificationRecipientCount} recipient(s)
                                        </Typography>
                                    )}
                                </Box>
                            }
                        />
                    </MenuItem>
                </Menu>
                <Fab
                    color="primary"
                    variant="extended"
                    disabled={saving}
                    onClick={handleSave}
                >
                    {saving ? 'Saving...' : 'Save Changes'}
                </Fab>
            </Box>
        </AdminLayout>
    );
}
