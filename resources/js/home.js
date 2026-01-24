/**
 * Home page specific functionality
 */

// SHIFT + L keyboard shortcut to log in
document.addEventListener('keydown', function(e) {
    const { key, shiftKey, ctrlKey } = e;

    if (shiftKey && key.toUpperCase() === 'L' && !ctrlKey) {
        window.location.href = '/login';
    }
});

// Close mobile menu when an anchor link is clicked
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('a[href*="#"]').forEach(function (link) {
        link.addEventListener('click', function () {
            const navbar = document.getElementById('navbarSupportedContent');
            if (navbar) {
                navbar.classList.add('hidden');
            }
        });
    });
});
