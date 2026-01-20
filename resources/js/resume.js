(function ($) {
    "use strict";

    // Close mobile menu when an anchor link is clicked
    $('a[href*="#"]').click(function () {
        $('#navbarSupportedContent').addClass('hidden');
    });

})(jQuery);
