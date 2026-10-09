import { usePage } from "@inertiajs/react";

/**
 * The ghosted threshold the server resolved from `config('resume.ghosted_after_days')`,
 * sent with the Applications list and Discussion pages so the chips agree
 * with PHP `App\Support\ApplicationStatusResolver`. Undefined on a page that
 * does not send it, which leaves the resolver on its own default.
 */
export default function useGhostedAfterDays(): number | undefined {
    const { ghostedAfterDays } = usePage<{ ghostedAfterDays?: number }>().props;

    return ghostedAfterDays;
}
