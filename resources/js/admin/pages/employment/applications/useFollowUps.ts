import { router } from "@inertiajs/react";
import { useState } from "react";

import type { FollowUpState } from "@/types";

import { api, apiErrorMessage } from "@/api";

/** What the contact and follow-up endpoints answer. */
type FollowUpResponse = FollowUpState & { success?: boolean };

/** What the application update endpoint answers. */
interface ApplicationUpdateResponse {
    success?: boolean;
    message?: string;
    follow_ups?: FollowUpResponse;
}

export interface ContactInput {
    email: string;
    name: string;
    role: string;
    notes: string;
}

export interface UseFollowUpsResult {
    state: FollowUpState;
    /** The action in flight, so its own control can show it is busy. */
    busy: string | null;
    error: string | null;
    clearError: () => void;
    setEnabled: (enabled: boolean) => Promise<void>;
    /** Resolves null on success, otherwise the reason it failed. */
    saveContact: (
        contactId: number | null,
        input: ContactInput,
    ) => Promise<string | null>;
    deleteContact: (contactId: number) => Promise<void>;
    /** Resolves true when the draft was saved. */
    saveDraft: (
        followUpId: number,
        subject: string,
        body: string,
    ) => Promise<boolean>;
    approve: (followUpId: number) => Promise<void>;
    discard: (followUpId: number) => Promise<void>;
    /** Re-read the follow-ups after a change made elsewhere on the page. */
    refresh: () => void;
}

/**
 * Contacts and follow-up actions for one application.
 *
 * Like `useStatusUpdates`, the state is read from the `followUps` prop, and
 * an endpoint's answer is held as a short-lived override until this hook's
 * own partial reload lands, so the page updates at once and a reload already
 * in flight cannot snap it back.
 */
export default function useFollowUps(
    applicationId: number,
    followUps: FollowUpState,
): UseFollowUpsResult {
    const [override, setOverride] = useState<FollowUpState | null>(null);
    const [busy, setBusy] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);

    const baseUrl = `/api/admin/employment/applications/${applicationId}`;
    const state = override ?? followUps;

    const refresh = (snapshot: FollowUpState | null = null): void => {
        router.reload({
            only: ["followUps"],
            onFinish: () => {
                setOverride((current) =>
                    current === snapshot ? null : current,
                );
            },
        });
    };

    const applyResponse = (data: FollowUpResponse): void => {
        setOverride(data);
        refresh(data);
    };

    /** Run one request, reporting a failure through `error`. */
    const run = async (
        key: string,
        request: () => Promise<FollowUpResponse | undefined>,
        fallback: string,
    ): Promise<boolean> => {
        setBusy(key);
        setError(null);
        try {
            const data = await request();
            if (data) {
                applyResponse(data);
            }
            return true;
        } catch (caught) {
            setError(apiErrorMessage(caught, fallback));
            return false;
        } finally {
            setBusy(null);
        }
    };

    const setEnabled = async (enabled: boolean): Promise<void> => {
        await run(
            "switch",
            async () =>
                (
                    await api.put<ApplicationUpdateResponse>(baseUrl, {
                        follow_ups_enabled: enabled,
                    })
                ).follow_ups,
            enabled
                ? "Could not turn follow-ups on."
                : "Could not turn follow-ups off.",
        );
    };

    const saveContact = async (
        contactId: number | null,
        input: ContactInput,
    ): Promise<string | null> => {
        setBusy("contact");
        try {
            const payload = {
                email: input.email,
                name: input.name || null,
                role: input.role || null,
                notes: input.notes || null,
            };
            const data =
                contactId === null
                    ? await api.post<FollowUpResponse>(
                          `${baseUrl}/contacts`,
                          payload,
                      )
                    : await api.put<FollowUpResponse>(
                          `${baseUrl}/contacts/${contactId}`,
                          payload,
                      );
            applyResponse(data);
            return null;
        } catch (caught) {
            return apiErrorMessage(caught, "Could not save the contact.");
        } finally {
            setBusy(null);
        }
    };

    const deleteContact = async (contactId: number): Promise<void> => {
        await run(
            `contact-${contactId}`,
            () => api.del<FollowUpResponse>(`${baseUrl}/contacts/${contactId}`),
            "Could not remove the contact.",
        );
    };

    const saveDraft = (
        followUpId: number,
        subject: string,
        body: string,
    ): Promise<boolean> =>
        run(
            "save",
            () =>
                api.put<FollowUpResponse>(
                    `${baseUrl}/follow-ups/${followUpId}`,
                    { subject, body },
                ),
            "Could not save the draft.",
        );

    const approve = async (followUpId: number): Promise<void> => {
        await run(
            `approve-${followUpId}`,
            () =>
                api.post<FollowUpResponse>(
                    `${baseUrl}/follow-ups/${followUpId}/approve`,
                ),
            "Could not send the follow-up.",
        );
    };

    const discard = async (followUpId: number): Promise<void> => {
        await run(
            "discard",
            () =>
                api.post<FollowUpResponse>(
                    `${baseUrl}/follow-ups/${followUpId}/discard`,
                ),
            "Could not discard the draft.",
        );
    };

    return {
        state,
        busy,
        error,
        clearError: () => {
            setError(null);
        },
        setEnabled,
        saveContact,
        deleteContact,
        saveDraft,
        approve,
        discard,
        refresh: () => {
            refresh();
        },
    };
}
