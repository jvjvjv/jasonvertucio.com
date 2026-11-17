/**
 * Load utility libraries (jQuery, lodash, etc.)
 */

import "./bootstrap";

import Alpine from "alpinejs";

window.Alpine = Alpine;

const RESUME_DIFF_SHOW_KEY = "resume-diff-show";

/**
 * The "Show changes" switch on a draft revision's review page. On by
 * default; the choice is remembered in this browser when storage allows.
 */
Alpine.data("resumeDiffToggle", () => ({
    show: true,

    init() {
        try {
            const stored = window.localStorage.getItem(RESUME_DIFF_SHOW_KEY);

            if (stored !== null) {
                this.show = stored === "1";
            }
        } catch {
            // Storage is unavailable (private window, blocked site data).
        }

        this.$watch("show", (value) => {
            try {
                window.localStorage.setItem(
                    RESUME_DIFF_SHOW_KEY,
                    value ? "1" : "0",
                );
            } catch {
                // The choice simply isn't remembered.
            }
        });
    },
}));

Alpine.start();
