import { router } from "@inertiajs/react";
import { useCallback, useState } from "react";

import { api, apiErrorMessage } from "@/api";

interface UseUpdateTailoredMarkdownParams {
    targetedResumeId: number;
}

interface UseUpdateTailoredMarkdownResult {
    isSaving: boolean;
    saveError: string | null;
    saveSuccess: boolean;
    /** Resolves true when the markdown was saved. */
    saveMarkdown: (markdown: string) => Promise<boolean>;
}

export default function useUpdateTailoredMarkdown({
    targetedResumeId,
}: UseUpdateTailoredMarkdownParams): UseUpdateTailoredMarkdownResult {
    const [isSaving, setIsSaving] = useState(false);
    const [saveError, setSaveError] = useState<string | null>(null);
    const [saveSuccess, setSaveSuccess] = useState(false);

    const saveMarkdown = useCallback(
        async (markdown: string): Promise<boolean> => {
            setIsSaving(true);
            setSaveError(null);
            setSaveSuccess(false);
            try {
                await api.put(
                    `/api/admin/resume/targeted-resume/${targetedResumeId}`,
                    { markdown },
                );
                setSaveSuccess(true);
                // Saving invalidates the rendered documents and may reparse
                // the title; refresh the document without losing the editor.
                router.reload({ only: ["targetedResume"] });
                return true;
            } catch (error) {
                setSaveError(
                    apiErrorMessage(
                        error,
                        "Failed to save targeted resume.",
                        "Network error. Please try again.",
                    ),
                );
                return false;
            } finally {
                setIsSaving(false);
            }
        },
        [targetedResumeId],
    );

    return { isSaving, saveError, saveSuccess, saveMarkdown };
}
