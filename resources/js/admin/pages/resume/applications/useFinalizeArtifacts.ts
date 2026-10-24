import { router } from "@inertiajs/react";
import { useCallback, useState } from "react";

import { api, apiErrorMessage } from "@/api";

/**
 * Minimal structural view of the parsed tailored resume block this hook needs.
 * Compatible with `LatestTailoredResumeData` from ../targeted/tailoredResumeParser.
 */
interface FinalizableResumeData {
    rawContent: string;
    fitScore: number | null;
}

interface UseFinalizeArtifactsParams {
    applicationId: number;
    /** The application's targeted resume, when one has been finalized. */
    targetedResumeId: number | null;
    latestResumeData: FinalizableResumeData | null;
    latestCoverLetterContent: string | null;
}

interface UseFinalizeArtifactsResult {
    isFinalizing: boolean;
    finalizeError: string | null;
    isFinalizingCoverLetter: boolean;
    finalizeCoverLetterError: string | null;
    canFinalizeResume: boolean;
    canFinalizeCoverLetter: boolean;
    finalizeResume: () => Promise<void>;
    finalizeCoverLetter: () => Promise<void>;
}

export default function useFinalizeArtifacts({
    applicationId,
    targetedResumeId,
    latestResumeData,
    latestCoverLetterContent,
}: UseFinalizeArtifactsParams): UseFinalizeArtifactsResult {
    const [isFinalizing, setIsFinalizing] = useState(false);
    const [finalizeError, setFinalizeError] = useState<string | null>(null);
    const [isFinalizingCoverLetter, setIsFinalizingCoverLetter] =
        useState(false);
    const [finalizeCoverLetterError, setFinalizeCoverLetterError] = useState<
        string | null
    >(null);

    const canFinalizeResume = latestResumeData !== null;
    const canFinalizeCoverLetter = latestCoverLetterContent !== null;

    const finalizeResume = useCallback(async (): Promise<void> => {
        if (!latestResumeData) {
            if (targetedResumeId !== null) {
                router.post(
                    `/admin/resume/targeted-resume/${targetedResumeId}/regenerate`,
                );
            }
            return;
        }
        setIsFinalizing(true);
        setFinalizeError(null);
        try {
            await api.post(
                `/api/admin/resume/applications/${applicationId}/finalize`,
                {
                    tailored_content: latestResumeData.rawContent,
                    fit_score: latestResumeData.fitScore,
                },
            );
            // Finalizing writes the document, may update the fit score on the
            // application, and completes the session.
            router.reload({
                only: ["application", "targetedResume", "conversation"],
            });
        } catch (error) {
            setFinalizeError(
                apiErrorMessage(
                    error,
                    "Failed to save targeted resume.",
                    "Network error. Please try again.",
                ),
            );
        } finally {
            setIsFinalizing(false);
        }
    }, [applicationId, latestResumeData, targetedResumeId]);

    const finalizeCoverLetter = useCallback(async (): Promise<void> => {
        if (!latestCoverLetterContent) {
            return;
        }
        setIsFinalizingCoverLetter(true);
        setFinalizeCoverLetterError(null);
        try {
            await api.post(
                `/api/admin/resume/applications/${applicationId}/finalize-cover-letter`,
                { cover_letter_content: latestCoverLetterContent },
            );
            router.reload({ only: ["coverLetter", "conversation"] });
        } catch (error) {
            setFinalizeCoverLetterError(
                apiErrorMessage(
                    error,
                    "Failed to save cover letter.",
                    "Network error. Please try again.",
                ),
            );
        } finally {
            setIsFinalizingCoverLetter(false);
        }
    }, [applicationId, latestCoverLetterContent]);

    return {
        isFinalizing,
        finalizeError,
        isFinalizingCoverLetter,
        finalizeCoverLetterError,
        canFinalizeResume,
        canFinalizeCoverLetter,
        finalizeResume,
        finalizeCoverLetter,
    };
}
