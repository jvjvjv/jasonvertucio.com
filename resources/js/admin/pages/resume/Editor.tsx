import { Head, router } from "@inertiajs/react";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Card from "@mui/material/Card";
import CardContent from "@mui/material/CardContent";
import Checkbox from "@mui/material/Checkbox";
import Dialog from "@mui/material/Dialog";
import DialogActions from "@mui/material/DialogActions";
import DialogContent from "@mui/material/DialogContent";
import DialogTitle from "@mui/material/DialogTitle";
import Fab from "@mui/material/Fab";
import FormControlLabel from "@mui/material/FormControlLabel";
import List from "@mui/material/List";
import ListItem from "@mui/material/ListItem";
import ListItemText from "@mui/material/ListItemText";
import Menu from "@mui/material/Menu";
import MenuItem from "@mui/material/MenuItem";
import Tab from "@mui/material/Tab";
import Tabs from "@mui/material/Tabs";
import Typography from "@mui/material/Typography";
import { useCallback, useState } from "react";

import PageHeader from "../../components/PageHeader";
import AdminLayout from "../../layouts/AdminLayout";

import EducationTab from "./tabs/EducationTab";
import ExperienceTab from "./tabs/ExperienceTab";
import PersonalTab from "./tabs/PersonalTab";
import ProjectsTab from "./tabs/ProjectsTab";
import SkillsTab from "./tabs/SkillsTab";
import VersionTab from "./tabs/VersionTab";

import type { EditorProps, Personal, ResumeData } from "./types";

import { api, apiErrorMessages, networkErrorMessage } from "@/api";

export default function Editor({
    data: initialData,
    version: initialVersion,
    docxExists,
    availableVersions,
    mailConfigured,
    notificationRecipientCount,
    pendingCandidates,
}: EditorProps) {
    const [activeTab, setActiveTab] = useState(0);
    const [version, setVersion] = useState(initialVersion);
    const [data, setData] = useState<ResumeData>({ ...initialData });
    const [saving, setSaving] = useState(false);
    const [errors, setErrors] = useState<string[]>([]);
    const [notifyRecipients, setNotifyRecipients] = useState(false);
    const [optionsAnchor, setOptionsAnchor] = useState<null | HTMLElement>(
        null,
    );
    const [confirmSaveOpen, setConfirmSaveOpen] = useState(false);
    const [changedContent, setChangedContent] = useState<string[]>([]);

    // --- Updaters ---

    const updatePersonal = useCallback((key: keyof Personal, value: string) => {
        setData((prev) => ({
            ...prev,
            personal: { ...prev.personal, [key]: value },
        }));
    }, []);

    const updateSkillCategories = useCallback(
        (type: "top" | "other", categories: ResumeData["skills"]["top"]) => {
            setData((prev) => ({
                ...prev,
                skills: { ...prev.skills, [type]: categories },
            }));
        },
        [],
    );

    const updateExperience = useCallback(
        (experience: ResumeData["experience"]) => {
            setData((prev) => ({ ...prev, experience }));
        },
        [],
    );

    const updateEducation = useCallback(
        (education: ResumeData["education"]) => {
            setData((prev) => ({ ...prev, education }));
        },
        [],
    );

    const updateProjects = useCallback((projects: ResumeData["projects"]) => {
        setData((prev) => ({ ...prev, projects }));
    }, []);

    // --- Save ---

    const getChangedContent = useCallback((): string[] => {
        const changes: string[] = [];

        if (
            JSON.stringify(data.personal) !==
            JSON.stringify(initialData.personal)
        ) {
            changes.push("Personal");
        }

        if (
            JSON.stringify(data.skills) !== JSON.stringify(initialData.skills)
        ) {
            changes.push("Skills");
        }

        if (
            JSON.stringify(data.experience) !==
            JSON.stringify(initialData.experience)
        ) {
            changes.push("Experience");
        }

        if (
            JSON.stringify(data.projects) !==
            JSON.stringify(initialData.projects)
        ) {
            changes.push("Projects");
        }

        if (
            JSON.stringify(data.education) !==
            JSON.stringify(initialData.education)
        ) {
            changes.push("Education");
        }

        return changes;
    }, [data, initialData]);

    const submitSave = async () => {
        setSaving(true);
        setErrors([]);

        try {
            await api.post("/api/admin/resume/editor", {
                version,
                data,
                notify_recipients: notifyRecipients,
            });

            router.reload();
        } catch (error) {
            setErrors(
                apiErrorMessages(
                    error,
                    "Failed to save",
                    networkErrorMessage(error),
                ),
            );
        } finally {
            setSaving(false);
        }
    };

    const handleSave = () => {
        const changes = getChangedContent();

        if (changes.length > 0 && version === initialVersion) {
            setChangedContent(changes);
            setConfirmSaveOpen(true);
            return;
        }

        void submitSave();
    };

    const handleSaveAnyway = () => {
        setConfirmSaveOpen(false);
        void submitSave();
    };

    const tabLabels = [
        "Version",
        "Personal",
        "Skills",
        "Experience",
        "Projects",
        "Education",
    ];

    return (
        <AdminLayout>
            <Head title="Resume Builder | Resume" />
            <PageHeader
                title="Resume Builder"
                backHref="/admin/resume"
                backLabel="Back to Resume Management"
            />

            {pendingCandidates.length > 0 && (
                <Card
                    sx={{
                        mb: 2,
                        bgcolor: "warning.light",
                        color: "warning.contrastText",
                    }}
                >
                    <CardContent>
                        <Typography variant="subtitle1">
                            {pendingCandidates.length} AI-drafted revision
                            {pendingCandidates.length > 1
                                ? "s are"
                                : " is"}{" "}
                            pending review. Manual edits are disabled until
                            resolved.
                        </Typography>
                        <List dense disablePadding>
                            {pendingCandidates.map((c) => (
                                <ListItem key={c.id} sx={{ py: 0.25, pl: 0 }}>
                                    <Button
                                        size="small"
                                        onClick={() => {
                                            router.get(
                                                `/resume?revision=${c.id}`,
                                            );
                                        }}
                                    >
                                        Review revision #{c.revision_number}
                                    </Button>
                                </ListItem>
                            ))}
                        </List>
                    </CardContent>
                </Card>
            )}

            {errors.length > 0 && (
                <Card
                    sx={{
                        mb: 2,
                        bgcolor: "error.light",
                        color: "error.contrastText",
                    }}
                >
                    <CardContent sx={{ py: 1.5, "&:last-child": { pb: 1.5 } }}>
                        <ul style={{ margin: 0, paddingLeft: 20 }}>
                            {errors.map((err, i) => (
                                <li key={i}>{err}</li>
                            ))}
                        </ul>
                    </CardContent>
                </Card>
            )}

            <Tabs
                value={activeTab}
                onChange={(_, v: number) => {
                    setActiveTab(v);
                }}
                sx={{ mb: 2 }}
            >
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
            {activeTab === 1 && (
                <PersonalTab
                    personal={data.personal}
                    onChange={updatePersonal}
                />
            )}
            {activeTab === 2 && (
                <SkillsTab
                    skills={data.skills}
                    onChange={updateSkillCategories}
                />
            )}
            {activeTab === 3 && (
                <ExperienceTab
                    experience={data.experience}
                    onChange={updateExperience}
                />
            )}
            {activeTab === 4 && (
                <ProjectsTab
                    projects={data.projects}
                    onChange={updateProjects}
                />
            )}
            {activeTab === 5 && (
                <EducationTab
                    education={data.education}
                    onChange={updateEducation}
                />
            )}

            {/* FAB Save with Options */}
            <Box
                sx={{
                    position: "fixed",
                    bottom: 32,
                    right: 32,
                    display: "flex",
                    flexDirection: "column",
                    alignItems: "flex-end",
                    gap: 1,
                }}
            >
                <Button
                    variant="outlined"
                    size="small"
                    onClick={(e) => {
                        setOptionsAnchor(e.currentTarget);
                    }}
                    sx={{ bgcolor: "background.paper" }}
                >
                    Options
                </Button>
                <Menu
                    anchorEl={optionsAnchor}
                    open={Boolean(optionsAnchor)}
                    onClose={() => {
                        setOptionsAnchor(null);
                    }}
                    anchorOrigin={{ vertical: "top", horizontal: "right" }}
                    transformOrigin={{
                        vertical: "bottom",
                        horizontal: "right",
                    }}
                >
                    <MenuItem
                        disableRipple
                        sx={{ "&:hover": { bgcolor: "transparent" } }}
                    >
                        <FormControlLabel
                            control={
                                <Checkbox
                                    checked={notifyRecipients}
                                    onChange={(e) => {
                                        setNotifyRecipients(e.target.checked);
                                    }}
                                    disabled={!mailConfigured}
                                    size="small"
                                />
                            }
                            label={
                                <Box>
                                    <Typography variant="body2">
                                        Notify share code recipients
                                    </Typography>
                                    {!mailConfigured && (
                                        <Typography
                                            variant="caption"
                                            color="text.secondary"
                                        >
                                            (mail not configured)
                                        </Typography>
                                    )}
                                    {mailConfigured &&
                                        notificationRecipientCount > 0 && (
                                            <Typography
                                                variant="caption"
                                                color="primary"
                                            >
                                                Will notify{" "}
                                                {notificationRecipientCount}{" "}
                                                recipient(s)
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
                    disabled={saving || pendingCandidates.length > 0}
                    onClick={handleSave}
                >
                    {pendingCandidates.length > 0
                        ? "Resolve pending revision first"
                        : saving
                          ? "Saving..."
                          : "Save Changes"}
                </Fab>
            </Box>

            <Dialog
                open={confirmSaveOpen}
                onClose={() => {
                    setConfirmSaveOpen(false);
                }}
                maxWidth="sm"
                fullWidth
            >
                <DialogTitle>Content has changed.</DialogTitle>
                <DialogContent dividers>
                    <List dense disablePadding>
                        {changedContent.map((item) => (
                            <ListItem key={item} sx={{ py: 0.25 }}>
                                <ListItemText primary={item} />
                            </ListItem>
                        ))}
                    </List>
                    <Typography variant="body2" sx={{ mt: 2 }}>
                        Version is unchanged at {version}, but the content has
                        been modified. Do you want to save these changes anyway?
                    </Typography>
                </DialogContent>
                <DialogActions>
                    <Button
                        onClick={() => {
                            setConfirmSaveOpen(false);
                        }}
                    >
                        Cancel
                    </Button>
                    <Button
                        onClick={handleSaveAnyway}
                        variant="contained"
                        color="primary"
                        disabled={saving}
                    >
                        Save anyway
                    </Button>
                </DialogActions>
            </Dialog>
        </AdminLayout>
    );
}
