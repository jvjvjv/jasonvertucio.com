/**
 * Home page specific functionality
 */

(function ($) {
    "use strict";

    // SHIFT + L keyboard shortcut to log in
    $(document).on('keydown', function (e) {
        const { key, shiftKey, ctrlKey } = e;

        if (shiftKey && key.toUpperCase() === 'L' && !ctrlKey) {
            window.location.href = '/login';
        }
    });

})(jQuery);
