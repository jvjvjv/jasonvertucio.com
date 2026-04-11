import { useState, useCallback } from 'react';
import { Head, router } from '@inertiajs/react';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Card from '@mui/material/Card';
import CardContent from '@mui/material/CardContent';
import Checkbox from '@mui/material/Checkbox';
import Fab from '@mui/material/Fab';
import FormControlLabel from '@mui/material/FormControlLabel';
import Menu from '@mui/material/Menu';
import MenuItem from '@mui/material/MenuItem';
import Tab from '@mui/material/Tab';
import Tabs from '@mui/material/Tabs';
import Typography from '@mui/material/Typography';
import AdminLayout from '../../layouts/AdminLayout';
import PageHeader from '../../components/PageHeader';
import type { ResumeData, Personal, EditorProps } from './types';
import VersionTab from './tabs/VersionTab';
import PersonalTab from './tabs/PersonalTab';
import SkillsTab from './tabs/SkillsTab';
import ExperienceTab from './tabs/ExperienceTab';
import ProjectsTab from './tabs/ProjectsTab';
import EducationTab from './tabs/EducationTab';

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
        (type: 'top' | 'other', categories: typeof data.skills.top) => {
            setData((prev) => ({
                ...prev,
                skills: { ...prev.skills, [type]: categories },
            }));
        },
        [],
    );

    const updateExperience = useCallback((experience: ResumeData['experience']) => {
        setData((prev) => ({ ...prev, experience }));
    }, []);

    const updateEducation = useCallback((education: ResumeData['education']) => {
        setData((prev) => ({ ...prev, education }));
    }, []);

    const updateProjects = useCallback((projects: ResumeData['projects']) => {
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

            {activeTab === 0 && (
                <VersionTab
                    version={version}
                    onVersionChange={setVersion}
                    docxExists={docxExists}
                    availableVersions={availableVersions}
                />
            )}
            {activeTab === 1 && <PersonalTab personal={data.personal} onChange={updatePersonal} />}
            {activeTab === 2 && <SkillsTab skills={data.skills} onChange={updateSkillCategories} />}
            {activeTab === 3 && <ExperienceTab experience={data.experience} onChange={updateExperience} />}
            {activeTab === 4 && <ProjectsTab projects={data.projects} onChange={updateProjects} />}
            {activeTab === 5 && <EducationTab education={data.education} onChange={updateEducation} />}

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
                <Fab color="primary" variant="extended" disabled={saving} onClick={handleSave}>
                    {saving ? 'Saving...' : 'Save Changes'}
                </Fab>
            </Box>
        </AdminLayout>
    );
}
