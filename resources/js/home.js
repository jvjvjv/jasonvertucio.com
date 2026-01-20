/**
 * Home page specific functionality
 */

(function ($) {
    "use strict";

    // SHIFT + C keyboard shortcut to navigate to Canvas
    $(document).on('keydown', function (e) {
        const { key, shiftKey, ctrlKey } = e;

        if (shiftKey && key.toUpperCase() === 'C' && !ctrlKey) {
            window.location.href = '/canvas';
        }
    });

})(jQuery);
