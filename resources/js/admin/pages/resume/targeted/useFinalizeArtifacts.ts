import { router } from "@inertiajs/react";
import { useCallback, useState } from "react";

import type { TargetedResume } from "@/types";

import { api, apiErrorMessage } from "@/api";

/**
 * Minimal structural view of the parsed tailored resume block this hook needs.
 * Compatible with `LatestTailoredResumeData` from ./tailoredResumeParser.
 */
interface FinalizableResumeData {
    rawContent: string;
    fitScore: number | null;
}

interface UseFinalizeArtifactsParams {
    conversationId: number;
    targetedResume: TargetedResume | null;
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
    conversationId,
    targetedResume,
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
        if (!canFinalizeResume && targetedResume) {
            router.post(
                `/admin/resume/targeted-resume/${targetedResume.id}/regenerate`,
            );
            return;
        }
        if (!latestResumeData) {
            return;
        }
        setIsFinalizing(true);
        setFinalizeError(null);
        try {
            await api.post(
                `/api/admin/resume/targeted-builder/${conversationId}/finalize`,
                {
                    tailored_content: latestResumeData.rawContent,
                    fit_score: latestResumeData.fitScore,
                },
            );
            window.location.reload();
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
    }, [canFinalizeResume, conversationId, latestResumeData, targetedResume]);

    const finalizeCoverLetter = useCallback(async (): Promise<void> => {
        if (!canFinalizeCoverLetter || !latestCoverLetterContent) {
            return;
        }
        setIsFinalizingCoverLetter(true);
        setFinalizeCoverLetterError(null);
        try {
            await api.post(
                `/api/admin/resume/targeted-builder/${conversationId}/finalize-cover-letter`,
                { cover_letter_content: latestCoverLetterContent },
            );
            window.location.reload();
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
    }, [canFinalizeCoverLetter, conversationId, latestCoverLetterContent]);

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
