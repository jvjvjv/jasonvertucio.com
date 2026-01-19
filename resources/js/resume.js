(function ($) {
    "use strict";

    // Close mobile menu when an anchor link is clicked
    $('a[href*="#"]').click(function () {
        $('#navbarSupportedContent').addClass('hidden');
    });

    $(document).on('keydown', function (e) {
        const { key, shiftKey } = e

        if (shiftKey && key.toUpperCase() == 'C') {
            window.location.href = '/canvas';
        }
    })

})(jQuery);
