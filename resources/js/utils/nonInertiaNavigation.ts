import { router } from "@inertiajs/react";

/**
 * Follow non-Inertia responses as real navigations instead of showing them
 * in Inertia's error-modal iframe.
 *
 * When an Inertia visit lands on a Blade-rendered route the response has no
 * `x-inertia` header, so Inertia treats it as invalid and writes the HTML into
 * a modal iframe. The most common cause is session expiry redirecting to the
 * Blade `/login` page. The `invalid` event is cancelable — cancelling it skips
 * the modal, letting us navigate to the final URL for real.
 */
export function followNonInertiaResponses(): void {
    router.on("invalid", (event) => {
        const { response } = event.detail;
        const request = response.request as XMLHttpRequest | undefined;

        // responseURL reflects the final URL after any redirects (e.g. to
        // /login). It is an empty string rather than undefined when the
        // browser cannot supply it, so treat "" as absent too.
        const responseUrl = request?.responseURL;
        const destination =
            responseUrl === undefined || responseUrl === ""
                ? response.config.url
                : responseUrl;

        if (!destination) {
            return;
        }

        event.preventDefault();
        window.location.href = destination;
    });
}
