import { Head, router } from "@inertiajs/react";
import DoneIcon from "@mui/icons-material/Done";
import NotStartedIcon from "@mui/icons-material/NotStarted";
import Alert from "@mui/material/Alert";
import Box from "@mui/material/Box";
import Card from "@mui/material/Card";
import CardContent from "@mui/material/CardContent";
import { useEffect, useState } from "react";

import AISystemWarmupDetector from "./AISystemWarmupDetector";
import AppliedConfirmDialog from "./AppliedConfirmDialog";
import JobDetailsForm from "./JobDetailsForm";
import JobURLInputSection from "./JobURLInputSection";
import ParseResultsDisplay from "./ParseResultsDisplay";

import type { AiSystem, ResumeVersionOption } from "@/types";
import type { SyntheticEvent } from "react";

import PageHeader from "@/admin/components/PageHeader";
import AdminLayout from "@/admin/layouts/AdminLayout";
import { api, apiErrorMessage, networkErrorMessage } from "@/api";
import ResponsiveButton from "@/components/ResponsiveButton";

const APPLICATIONS_API = "/api/admin/resume/applications";

interface ParseJobResponse {
    message?: string;
    job_title?: string;
    company_name?: string;
    job_location?: string;
    job_description?: string;
    job_url_id?: string | null;
    reasoning?: string;
    parser_id?: number | null;
    used_existing_parser?: boolean;
}

interface StoreApplicationResponse {
    application_id: number;
    redirect: string;
}

type ModelState = "idle" | "checking" | "warming" | "ready" | "unavailable";

/** Which of the two actions is in flight, so only its button shows progress. */
type SubmitIntent = "analyze" | "applied";

interface CreateProps {
    systems: Pick<AiSystem, "id" | "name" | "model">[];
    defaultSystemId: number | null;
    coverLetterDefaultId: number | null;
    resumeVersions: ResumeVersionOption[];
    currentResumeVersionId: number | null;
}

export default function Create({
    systems,
    defaultSystemId,
    coverLetterDefaultId,
    resumeVersions,
    currentResumeVersionId,
}: CreateProps) {
    const [aiSystemId, setAiSystemId] = useState<number | "">(
        defaultSystemId ?? "",
    );
    const [jobUrl, setJobUrl] = useState("");
    const [jobUrlId, setJobUrlId] = useState<string | null>(null);
    const [jobTitle, setJobTitle] = useState("");
    const [companyName, setCompanyName] = useState("");
    const [jobLocation, setJobLocation] = useState("");
    const [jobDescription, setJobDescription] = useState("");
    const [isParsing, setIsParsing] = useState(false);
    const [parseError, setParseError] = useState("");
    const [parseReasoning, setParseReasoning] = useState("");
    const [parserId, setParserId] = useState<number | null>(null);
    const [usedExistingParser, setUsedExistingParser] = useState<
        boolean | null
    >(null);
    const [reparseFeedback, setReparseFeedback] = useState("");
    const [isReparsing, setIsReparsing] = useState(false);
    const [submitting, setSubmitting] = useState<SubmitIntent | null>(null);
    const [error, setError] = useState("");
    const [appliedDialogOpen, setAppliedDialogOpen] = useState(false);
    const [appliedError, setAppliedError] = useState<string | null>(null);

    const separateModelsConfigured =
        defaultSystemId !== null &&
        coverLetterDefaultId !== null &&
        defaultSystemId !== coverLetterDefaultId;

    const [modelState, setModelState] = useState<ModelState>("idle");

    // Warm up the selected model in the background while the user fills the form
    useEffect(() => {
        if (!aiSystemId) return;

        let cancelled = false;
        // Read through a function: the flag is flipped by the cleanup below
        // while `run` is suspended, which control-flow narrowing cannot see.
        const isCancelled = () => cancelled;

        const run = async () => {
            setModelState("checking");

            try {
                const res = await api.get<{ status?: { state: string } }>(
                    `${APPLICATIONS_API}/ai-systems/${aiSystemId}/model-status`,
                );
                if (isCancelled()) return;

                const state = res.status?.state;

                if (state === "loaded") {
                    setModelState("ready");
                    return;
                }

                if (state === "not_loaded") {
                    setModelState("warming");
                    const warmupRes = await api.post<{
                        status?: { state: string };
                    }>(
                        `${APPLICATIONS_API}/ai-systems/${aiSystemId}/model-warmup`,
                    );
                    // A warm-up can outlive the selection that started it; a
                    // late answer must not overwrite the newer system's state.
                    if (isCancelled()) return;
                    setModelState(
                        warmupRes.status?.state === "loaded"
                            ? "ready"
                            : "unavailable",
                    );
                    return;
                }

                setModelState("unavailable");
            } catch {
                if (isCancelled()) return;
                setModelState("unavailable");
            }
        };

        void run();
        return () => {
            cancelled = true;
        };
    }, [aiSystemId]);

    /** Autofill the form from a parse result; every field stays editable. */
    const applyParseResult = (result: ParseJobResponse) => {
        if (result.job_title) setJobTitle(result.job_title);
        if (result.company_name) setCompanyName(result.company_name);
        if (result.job_location) setJobLocation(result.job_location);
        if (result.job_description) setJobDescription(result.job_description);
        setJobUrlId(result.job_url_id ?? null);
        setParseReasoning(result.reasoning ?? "");
        if (result.parser_id) setParserId(result.parser_id);
    };

    const handleParseUrl = async () => {
        if (!jobUrl.trim()) return;
        setIsParsing(true);
        setParseError("");

        try {
            const result = await api.post<ParseJobResponse>(
                `${APPLICATIONS_API}/parse-url`,
                { url: jobUrl, ai_system_id: aiSystemId },
            );

            applyParseResult(result);
            if (result.used_existing_parser) setUsedExistingParser(true);
        } catch (err) {
            setParseError(
                apiErrorMessage(
                    err,
                    "Failed to parse URL",
                    networkErrorMessage(err),
                ),
            );
        } finally {
            setIsParsing(false);
        }
    };

    const handleReparse = async () => {
        if (!parserId || !reparseFeedback.trim()) return;
        setIsReparsing(true);
        setParseError("");

        try {
            const result = await api.post<ParseJobResponse>(
                `${APPLICATIONS_API}/parser/${parserId}/reparse`,
                { ai_system_id: aiSystemId, feedback: reparseFeedback },
            );

            applyParseResult(result);
            setReparseFeedback("");
        } catch (err) {
            setParseError(
                apiErrorMessage(
                    err,
                    "Failed to re-parse URL",
                    networkErrorMessage(err),
                ),
            );
        } finally {
            setIsReparsing(false);
        }
    };

    /** Both actions need a job description; reports the error when it is missing. */
    const requireJobDescription = (): boolean => {
        if (jobDescription.trim()) {
            return true;
        }
        setError("Please provide a job description.");
        return false;
    };

    const jobPayload = () => ({
        job_url_id: jobUrlId,
        job_title: jobTitle,
        job_location: jobLocation,
        company_name: companyName,
        job_description: jobDescription,
    });

    const handleBeginAnalysis = async (e: SyntheticEvent) => {
        e.preventDefault();
        if (submitting !== null || separateModelsConfigured) {
            return;
        }
        if (!aiSystemId) {
            setError("Please select an AI system.");
            return;
        }
        if (!requireJobDescription()) {
            return;
        }

        setSubmitting("analyze");
        setError("");

        try {
            const result = await api.post<StoreApplicationResponse>(
                APPLICATIONS_API,
                {
                    ...jobPayload(),
                    intent: "analyze",
                    ai_system_id: aiSystemId,
                },
            );

            router.visit(result.redirect);
        } catch (err) {
            setError(
                apiErrorMessage(
                    err,
                    "Failed to start session",
                    networkErrorMessage(err),
                ),
            );
            setSubmitting(null);
        }
    };

    const handleOpenAppliedDialog = () => {
        if (!requireJobDescription()) {
            return;
        }
        setError("");
        setAppliedError(null);
        setAppliedDialogOpen(true);
    };

    const handleConfirmApplied = async (resumeVersionId: number | null) => {
        setSubmitting("applied");
        setAppliedError(null);

        try {
            const result = await api.post<StoreApplicationResponse>(
                APPLICATIONS_API,
                {
                    ...jobPayload(),
                    intent: "applied",
                    resume_version_id: resumeVersionId,
                },
            );

            router.visit(result.redirect);
        } catch (err) {
            setAppliedError(
                apiErrorMessage(
                    err,
                    "Failed to record the application",
                    networkErrorMessage(err),
                ),
            );
            setSubmitting(null);
        }
    };

    const clearParseReasoning = () => {
        setParseReasoning("");
    };

    return (
        <AdminLayout>
            <Head title="New Session | Applications" />
            <PageHeader
                title="New Session"
                backHref="/admin/resume/applications"
                backLabel="Back to Applications"
            />

            {separateModelsConfigured && (
                <Alert severity="warning" sx={{ mb: 2 }}>
                    Separate models for Targeted Resume and Cover Letter are
                    unsupported at this time. You can still record a job you
                    already applied to.
                </Alert>
            )}

            {error && (
                <Alert severity="error" sx={{ mb: 2 }}>
                    {error}
                </Alert>
            )}

            <Card>
                <CardContent>
                    {/* noValidate: the browser's own "required" check would
                        swallow the submit before the description error below
                        could be shown the same way for both actions. */}
                    <Box
                        component="form"
                        noValidate
                        onSubmit={handleBeginAnalysis}
                    >
                        <AISystemWarmupDetector
                            systems={systems}
                            aiSystemId={aiSystemId}
                            modelState={modelState}
                            onAiSystemChange={setAiSystemId}
                        />

                        <JobURLInputSection
                            jobUrl={jobUrl}
                            isParsing={isParsing}
                            onJobUrlChange={setJobUrl}
                            onParseUrl={() => {
                                void handleParseUrl();
                            }}
                        />

                        <ParseResultsDisplay
                            parseError={parseError}
                            jobUrlId={jobUrlId}
                            parseReasoning={parseReasoning}
                            usedExistingParser={usedExistingParser}
                            parserId={parserId}
                            reparseFeedback={reparseFeedback}
                            isReparsing={isReparsing}
                            onReparseFeedbackChange={setReparseFeedback}
                            onReparse={() => {
                                void handleReparse();
                            }}
                        />

                        <JobDetailsForm
                            jobTitle={jobTitle}
                            companyName={companyName}
                            jobLocation={jobLocation}
                            jobDescription={jobDescription}
                            onJobTitleChange={(value) => {
                                setJobTitle(value);
                                clearParseReasoning();
                            }}
                            onCompanyNameChange={(value) => {
                                setCompanyName(value);
                                clearParseReasoning();
                            }}
                            onJobLocationChange={(value) => {
                                setJobLocation(value);
                                clearParseReasoning();
                            }}
                            onJobDescriptionChange={(value) => {
                                setJobDescription(value);
                                clearParseReasoning();
                            }}
                        />

                        <Box
                            sx={{
                                display: "flex",
                                justifyContent: "flex-end",
                                gap: 1,
                            }}
                        >
                            <ResponsiveButton
                                type="button"
                                color="success"
                                variant="outlined"
                                disabled={submitting !== null}
                                icon={<DoneIcon />}
                                label="I Applied"
                                title="Record a job you already applied to"
                                onClick={handleOpenAppliedDialog}
                            />
                            <ResponsiveButton
                                type="submit"
                                variant="contained"
                                disabled={
                                    submitting !== null ||
                                    separateModelsConfigured
                                }
                                icon={<NotStartedIcon />}
                                label={
                                    submitting === "analyze"
                                        ? "Starting..."
                                        : "Begin Analysis"
                                }
                                title="Begin Analysis"
                            />
                        </Box>
                    </Box>
                </CardContent>
            </Card>

            <AppliedConfirmDialog
                open={appliedDialogOpen}
                hasTargetedResume={false}
                resumeVersions={resumeVersions}
                currentResumeVersionId={currentResumeVersionId}
                isSubmitting={submitting === "applied"}
                error={appliedError}
                onConfirm={(resumeVersionId) => {
                    void handleConfirmApplied(resumeVersionId);
                }}
                onCancel={() => {
                    setAppliedDialogOpen(false);
                }}
            />
        </AdminLayout>
    );
}
