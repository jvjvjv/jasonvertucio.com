import { useCallback, useState } from "react";

import { api, apiErrorMessage } from "@/api";

interface UseUpdateTailoredMarkdownParams {
    targetedResumeId: number;
}

interface UseUpdateTailoredMarkdownResult {
    isSaving: boolean;
    saveError: string | null;
    saveSuccess: boolean;
    saveMarkdown: (markdown: string) => Promise<void>;
}

export default function useUpdateTailoredMarkdown({
    targetedResumeId,
}: UseUpdateTailoredMarkdownParams): UseUpdateTailoredMarkdownResult {
    const [isSaving, setIsSaving] = useState(false);
    const [saveError, setSaveError] = useState<string | null>(null);
    const [saveSuccess, setSaveSuccess] = useState(false);

    const saveMarkdown = useCallback(
        async (markdown: string): Promise<void> => {
            setIsSaving(true);
            setSaveError(null);
            setSaveSuccess(false);
            try {
                await api.put(
                    `/api/admin/resume/targeted-resume/${targetedResumeId}`,
                    { markdown },
                );
                setSaveSuccess(true);
                window.location.reload();
            } catch (error) {
                setSaveError(
                    apiErrorMessage(
                        error,
                        "Failed to save targeted resume.",
                        "Network error. Please try again.",
                    ),
                );
            } finally {
                setIsSaving(false);
            }
        },
        [targetedResumeId],
    );

    return { isSaving, saveError, saveSuccess, saveMarkdown };
}
