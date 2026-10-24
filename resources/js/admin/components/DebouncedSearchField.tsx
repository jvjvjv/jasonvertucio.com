import TextField from "@mui/material/TextField";
import { useEffect, useImperativeHandle, useRef, useState } from "react";

import type { SxProps, Theme } from "@mui/material/styles";
import type { Ref } from "react";

export interface DebouncedSearchFieldHandle {
    /**
     * Cancels a search still waiting out its pause and returns the text in
     * the field. For a caller about to send its own request: it can include
     * what was just typed, and the pending search cannot land afterwards and
     * undo it.
     */
    takeText: () => string;
}

interface DebouncedSearchFieldProps {
    /**
     * The search the server last applied. Read once, on mount: afterwards the
     * field owns what is typed, so a slow response can never overwrite newer
     * keystrokes. Remount the field (change its `key`) to reset it.
     */
    initialValue: string;
    /** Called with the typed text once the user pauses. */
    onSearch: (value: string) => void;
    label?: string;
    placeholder?: string;
    delayMs?: number;
    sx?: SxProps<Theme>;
    ref?: Ref<DebouncedSearchFieldHandle>;
}

/**
 * A search box for server-filtered lists.
 *
 * The typed text is local state, so each keystroke re-renders only this field
 * rather than the list it filters, and the request is sent after a pause
 * instead of once per character.
 */
export default function DebouncedSearchField({
    initialValue,
    onSearch,
    label = "Search",
    placeholder,
    delayMs = 300,
    sx,
    ref,
}: DebouncedSearchFieldProps) {
    const [text, setText] = useState(initialValue);
    const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    // The timer fires after the render that started it, so it must call the
    // latest `onSearch`, not the one captured with that keystroke — otherwise
    // a search built from stale filters could undo one applied meanwhile.
    const onSearchRef = useRef(onSearch);
    useEffect(() => {
        onSearchRef.current = onSearch;
    });

    const cancelPending = () => {
        if (timerRef.current !== null) {
            clearTimeout(timerRef.current);
            timerRef.current = null;
        }
    };

    useEffect(() => cancelPending, []);

    useImperativeHandle(
        ref,
        () => ({
            takeText: () => {
                cancelPending();
                return text;
            },
        }),
        [text],
    );

    const handleChange = (value: string) => {
        setText(value);
        cancelPending();
        timerRef.current = setTimeout(() => {
            timerRef.current = null;
            onSearchRef.current(value);
        }, delayMs);
    };

    return (
        <TextField
            label={label}
            type="search"
            size="small"
            value={text}
            onChange={(e) => {
                handleChange(e.target.value);
            }}
            placeholder={placeholder}
            sx={sx}
        />
    );
}
