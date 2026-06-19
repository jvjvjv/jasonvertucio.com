import { Head } from "@inertiajs/react";
import NotStartedIcon from "@mui/icons-material/NotStarted";
import Alert from "@mui/material/Alert";
import Box from "@mui/material/Box";
import Card from "@mui/material/Card";
import CardContent from "@mui/material/CardContent";
import { useEffect, useState } from "react";

import AISystemWarmupDetector from "./AISystemWarmupDetector";
import JobDetailsForm from "./JobDetailsForm";
import JobURLInputSection from "./JobURLInputSection";
import ParseResultsDisplay from "./ParseResultsDisplay";

import type { AiSystem } from "@/types";
import type { SyntheticEvent } from "react";

import PageHeader from "@/admin/components/PageHeader";
import AdminLayout from "@/admin/layouts/AdminLayout";
import { api, ApiError } from "@/api";
import ResponsiveButton from "@/components/ResponsiveButton";

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
    redirect?: string;
}

interface CreateProps {
    systems: Pick<AiSystem, "id" | "name" | "model">[];
    defaultSystemId: number | null;
    coverLetterDefaultId: number | null;
}

export default function Create({
    systems,
    defaultSystemId,
    coverLetterDefaultId,
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
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [error, setError] = useState("");

    const separateModelsConfigured =
        defaultSystemId !== null &&
        coverLetterDefaultId !== null &&
        defaultSystemId !== coverLetterDefaultId;

    const [modelState, setModelState] = useState<
        "idle" | "checking" | "warming" | "ready" | "unavailable"
    >("idle");

    // Warm up the selected model in the background while the user fills the form
    useEffect(() => {
        if (!aiSystemId) return;

        let mounted = true;

        const run = async () => {
            setModelState("checking");

            try {
                const res = await api.get<{ status?: { state: string } }>(
                    `/api/admin/resume/targeted-builder/ai-systems/${aiSystemId}/model-status`,
                );
                if (!mounted) return;

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
                        `/api/admin/resume/targeted-builder/ai-systems/${aiSystemId}/model-warmup`,
                    );
                    setModelState(
                        warmupRes.status?.state === "loaded"
                            ? "ready"
                            : "unavailable",
                    );
                    return;
                }

                setModelState("unavailable");
            } catch {
                setModelState("unavailable");
            }
        };

        void run();
        return () => {
            mounted = false;
        };
    }, [aiSystemId]);

    const handleParseUrl = async () => {
        if (!jobUrl.trim()) return;
        setIsParsing(true);
        setParseError("");

        try {
            const result = await api.post<ParseJobResponse>(
                "/api/admin/resume/targeted-builder/parse-url",
                { url: jobUrl, ai_system_id: aiSystemId },
            );

            if (result.job_title) setJobTitle(result.job_title);
            if (result.company_name) setCompanyName(result.company_name);
            if (result.job_location) setJobLocation(result.job_location);
            if (result.job_description)
                setJobDescription(result.job_description);
            setJobUrlId(result.job_url_id ?? null);
            setParseReasoning(result.reasoning ?? "");
            if (result.parser_id) setParserId(result.parser_id);
            if (result.used_existing_parser) setUsedExistingParser(true);
        } catch (err) {
            if (err instanceof ApiError) {
                const result = err.data as ParseJobResponse;
                setParseError(result.message ?? "Failed to parse URL");
            } else {
                setParseError("Network error: " + (err as Error).message);
            }
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
                `/api/admin/resume/targeted-builder/parser/${parserId}/reparse`,
                { ai_system_id: aiSystemId, feedback: reparseFeedback },
            );

            if (result.job_title) setJobTitle(result.job_title);
            if (result.company_name) setCompanyName(result.company_name);
            if (result.job_location) setJobLocation(result.job_location);
            if (result.job_description)
                setJobDescription(result.job_description);
            setJobUrlId(result.job_url_id ?? null);
            setParseReasoning(result.reasoning ?? "");
            if (result.parser_id) setParserId(result.parser_id);
            setReparseFeedback("");
        } catch (err) {
            if (err instanceof ApiError) {
                const result = err.data as ParseJobResponse;
                setParseError(result.message ?? "Failed to re-parse URL");
            } else {
                setParseError("Network error: " + (err as Error).message);
            }
        } finally {
            setIsReparsing(false);
        }
    };

    const handleSubmit = async (e: SyntheticEvent) => {
        e.preventDefault();
        if (!aiSystemId) {
            setError("Please select an AI system.");
            return;
        }
        if (!jobDescription.trim()) {
            setError("Please provide a job description.");
            return;
        }

        setIsSubmitting(true);
        setError("");

        try {
            const result = await api.post<ParseJobResponse>(
                "/api/admin/resume/targeted-builder/start",
                {
                    ai_system_id: aiSystemId,
                    job_url_id: jobUrlId,
                    job_title: jobTitle,
                    job_location: jobLocation,
                    company_name: companyName,
                    job_description: jobDescription,
                },
            );

            window.location.href = result.redirect ?? "";
        } catch (err) {
            if (err instanceof ApiError) {
                const result = err.data as ParseJobResponse;
                setError(result.message ?? "Failed to start session");
            } else {
                setError("Network error: " + (err as Error).message);
            }
        } finally {
            setIsSubmitting(false);
        }
    };

    return (
        <AdminLayout>
            <Head title="New | Targeted Resumes" />
            <PageHeader
                title="New Targeted Resume"
                backHref="/admin/resume/targeted-builder"
                backLabel="Back to Targeted Resumes"
            />

            {separateModelsConfigured && (
                <Alert severity="warning" sx={{ mb: 2 }}>
                    Separate models for Targeted Resume and Cover Letter are
                    unsupported at this time.
                </Alert>
            )}

            {error && (
                <Alert severity="error" sx={{ mb: 2 }}>
                    {error}
                </Alert>
            )}

            <Card>
                <CardContent>
                    <Box component="form" onSubmit={handleSubmit}>
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
                                setParseReasoning("");
                            }}
                            onCompanyNameChange={(value) => {
                                setCompanyName(value);
                                setParseReasoning("");
                            }}
                            onJobLocationChange={(value) => {
                                setJobLocation(value);
                                setParseReasoning("");
                            }}
                            onJobDescriptionChange={(value) => {
                                setJobDescription(value);
                                setParseReasoning("");
                            }}
                        />

                        <Box
                            sx={{ display: "flex", justifyContent: "flex-end" }}
                        >
                            <ResponsiveButton
                                type="submit"
                                variant="contained"
                                disabled={
                                    isSubmitting || separateModelsConfigured
                                }
                                icon={<NotStartedIcon />}
                                label={
                                    isSubmitting
                                        ? "Starting..."
                                        : "Start Analysis"
                                }
                                onClick={(e) => {
                                    if (
                                        isSubmitting ||
                                        separateModelsConfigured
                                    ) {
                                        e.preventDefault();
                                        return;
                                    }
                                    void handleSubmit(e);
                                }}
                            ></ResponsiveButton>
                        </Box>
                    </Box>
                </CardContent>
            </Card>
        </AdminLayout>
    );
}
