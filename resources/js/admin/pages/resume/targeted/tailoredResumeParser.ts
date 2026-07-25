import type { ChatMessage } from "@/components/ChatInterface";
import type { TargetedResume } from "@/types";

export interface ParsedTailoredResumeBlock {
    title: string | null;
    content: string;
}

export interface LatestTailoredResumeData {
    rawContent: string;
    title: string | null;
    content: string;
    fitScore: number | null;
}

export function parseTailoredResumeBlock(
    raw: string,
): ParsedTailoredResumeBlock {
    const normalized = raw.trim().replace(/\r\n/g, "\n");
    const titleMatch = /^Title:\s*(.+)\n+/i.exec(normalized);
    if (!titleMatch) {
        return { title: null, content: normalized };
    }
    return {
        title: titleMatch[1].trim(),
        content: normalized.replace(/^Title:\s*.+\n+/i, "").trim(),
    };
}

export function getLatestTailoredResumeData(
    msgs: ChatMessage[],
): LatestTailoredResumeData | null {
    for (let i = msgs.length - 1; i >= 0; i--) {
        const msg = msgs[i];
        if (msg.role !== "assistant") {
            continue;
        }
        const contentMatch =
            /```tailored(?:-|\s+)resume\s*\n([\s\S]*?)```/i.exec(msg.content);
        if (!contentMatch) {
            continue;
        }
        const parsed = parseTailoredResumeBlock(contentMatch[1]);
        let fitScore: number | null = null;
        const scoreMatch =
            /(?:fit score|score)[:\s]*(\d{1,3})(?:\s*[/%]|\s*out of\s*100)?/i.exec(
                msg.content,
            );
        if (scoreMatch) {
            const s = parseInt(scoreMatch[1]);
            if (s <= 100) {
                fitScore = s;
            }
        }
        return { rawContent: contentMatch[1].trim(), ...parsed, fitScore };
    }
    return null;
}

export function getLatestCoverLetterContent(
    msgs: ChatMessage[],
): string | null {
    for (let i = msgs.length - 1; i >= 0; i--) {
        const msg = msgs[i];
        if (msg.role !== "assistant") {
            continue;
        }
        const m = /```cover[-\s]letter\s*\n([\s\S]*?)```/i.exec(msg.content);
        if (m) {
            return m[1].trim();
        }
    }
    return null;
}

export function hasNewerResume(
    latest: LatestTailoredResumeData | null,
    targetedResume: TargetedResume | null,
): boolean {
    if (!targetedResume || !latest) {
        return false;
    }
    const normalize = (s: string | null | undefined): string =>
        (s ?? "").trim().replace(/\r\n/g, "\n");
    return (
        normalize(latest.title) !== normalize(targetedResume.tailored_title) ||
        normalize(latest.content) !== normalize(targetedResume.tailored_content)
    );
}
