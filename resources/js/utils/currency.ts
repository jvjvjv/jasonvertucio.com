/** Formats a USD cost value as `$1.23`, or an em dash when unset. */
export function formatCost(value: number | null | undefined): string {
    return value == null ? "—" : `$${value.toFixed(2)}`;
}
