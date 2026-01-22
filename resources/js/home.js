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
