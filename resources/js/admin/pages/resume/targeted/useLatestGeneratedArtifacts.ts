import { useMemo } from "react";

import {
    getLatestCoverLetterContent,
    getLatestTailoredResumeData,
    hasNewerResume as computeHasNewerResume,
} from "./tailoredResumeParser";

import type { LatestTailoredResumeData } from "./tailoredResumeParser";
import type { ChatMessage } from "@/components/ChatInterface";
import type { TargetedResume } from "@/types";

export interface LatestGeneratedArtifacts {
    latestResumeData: LatestTailoredResumeData | null;
    latestCoverLetterContent: string | null;
    hasNewerResume: boolean;
    canFinalizeResume: boolean;
    canFinalizeCoverLetter: boolean;
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
        canFinalizeResume: latestResumeData !== null,
        canFinalizeCoverLetter: latestCoverLetterContent !== null,
    };
}
