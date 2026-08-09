import type { SxProps, SystemStyleObject, Theme } from "@mui/system";

type SxItem<T extends object> =
    boolean | SystemStyleObject<T> | ((theme: T) => SystemStyleObject<T>);

const isSxArray = <T extends object>(
    value: SxProps<T>,
): value is readonly SxItem<T>[] => Array.isArray(value);

/**
 * Flattens any number of `sx` values into a single array-form `sx`, letting a
 * caller-supplied `sx` prop override a component's base styles. `undefined`
 * entries are dropped, and values already in array form are spread inline so
 * the result never nests.
 */
const mergeSx = <T extends object = Theme>(
    ...values: (SxProps<T> | undefined)[]
): SxProps<T> =>
    values.flatMap((value): SxItem<T>[] => {
        if (value === undefined) {
            return [];
        }

        return isSxArray(value) ? [...value] : [value];
    });

export default mergeSx;
