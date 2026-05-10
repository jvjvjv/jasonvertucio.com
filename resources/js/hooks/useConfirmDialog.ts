import { useCallback, useState } from "react";

import type { ConfirmDialogProps } from "../admin/components/ConfirmDialog";

type ConfirmOptions = Partial<
    Pick<ConfirmDialogProps, "title" | "confirmLabel" | "confirmColor">
>;

export default function useConfirmDialog() {
    const [state, setState] = useState<{
        open: boolean;
        message: string;
        options: ConfirmOptions;
        onConfirm: () => void;
    }>({
        open: false,
        message: "",
        options: {},
        onConfirm: () => null,
    });

    const confirm = useCallback(
        (
            message: string,
            onConfirm: () => void,
            options: ConfirmOptions = {},
        ) => {
            setState({ open: true, message, onConfirm, options });
        },
        [],
    );

    const handleConfirm = useCallback(() => {
        state.onConfirm();
        setState((prev) => ({ ...prev, open: false }));
    }, [state]);

    const handleCancel = useCallback(() => {
        setState((prev) => ({ ...prev, open: false }));
    }, []);

    const dialogProps: ConfirmDialogProps = {
        open: state.open,
        message: state.message,
        onConfirm: handleConfirm,
        onCancel: handleCancel,
        ...state.options,
    };

    return { dialogProps, confirm };
}
