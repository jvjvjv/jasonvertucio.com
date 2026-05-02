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

// Back to top button visibility
const backToTop = document.getElementById('back-to-top');
if (backToTop) {
    window.addEventListener('scroll', function () {
        if (window.scrollY > 200) {
            backToTop.classList.remove('opacity-0', 'pointer-events-none');
            backToTop.classList.add('opacity-100');
        } else {
            backToTop.classList.add('opacity-0', 'pointer-events-none');
            backToTop.classList.remove('opacity-100');
        }
    }, { passive: true });
}

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
