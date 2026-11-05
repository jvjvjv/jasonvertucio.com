import { useMemo } from "react";

import type { LatestTailoredResumeData } from "@/admin/pages/resume/targeted/tailoredResumeParser";
import type { ChatMessage } from "@/components/ChatInterface";
import type { TargetedResume } from "@/types";

import {
    getLatestCoverLetterContent,
    getLatestTailoredResumeData,
    hasNewerResume as computeHasNewerResume,
} from "@/admin/pages/resume/targeted/tailoredResumeParser";

export interface LatestGeneratedArtifacts {
    latestResumeData: LatestTailoredResumeData | null;
    latestCoverLetterContent: string | null;
    hasNewerResume: boolean;
}

export default function useLatestGeneratedArtifacts(
    messages: ChatMessage[],
    targetedResume: TargetedResume | null,
): LatestGeneratedArtifacts {
    const latestResumeData = useMemo<LatestTailoredResumeData | null>(
        () => getLatestTailoredResumeData(messages),
        [messages],
    );

    const latestCoverLetterContent = useMemo<string | null>(
        () => getLatestCoverLetterContent(messages),
        [messages],
    );

    const hasNewerResume = useMemo<boolean>(
        () => computeHasNewerResume(latestResumeData, targetedResume),
        [latestResumeData, targetedResume],
    );

    return {
        latestResumeData,
        latestCoverLetterContent,
        hasNewerResume,
    };
}
