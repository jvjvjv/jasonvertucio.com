// Close mobile menu when an anchor link is clicked
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('a[href*="#"]').forEach(function(link) {
        link.addEventListener('click', function() {
            const navbar = document.getElementById('navbarSupportedContent');
            if (navbar) {
                navbar.classList.add('hidden');
            }
        });
    });
});
