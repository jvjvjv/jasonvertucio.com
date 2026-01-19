/**
 * Font Loading Progress Bar
 *
 * Uses the Font Loading API and NProgress to display a progress bar
 * while custom fonts are loading, then reveals content once ready.
 */

import NProgress from 'nprogress';
import 'nprogress/nprogress.css';

// Configuration
const FONT_LOAD_TIMEOUT = 4000; // 4 seconds
const PROGRESS_INCREMENT_INTERVAL = 200; // milliseconds
const PROGRESS_INCREMENT_AMOUNT = 0.1;

/**
 * Initialize font loading progress bar
 * @param {string} contentSelector - CSS selector for the content container(s) to hide/show
 *                                    Can be an ID (#main-content), class (.fonts-loading), or any valid CSS selector
 */
function initFontLoader(contentSelector = '.fonts-loading') {
    const elements = document.querySelectorAll(contentSelector);

    if (elements.length === 0) {
        console.warn(`Font loader: No elements found matching "${contentSelector}"`);
        return;
    }

    // Check if Font Loading API is supported
    if (!('fonts' in document)) {
        console.warn('Font Loading API not supported, showing content immediately');
        elements.forEach(el => showContent(el));
        return;
    }

    // Configure NProgress
    NProgress.configure({
        showSpinner: false,
        easing: 'ease',
        speed: 300,
        trickle: true,
        trickleSpeed: 200,
        minimum: 0.1
    });

    // Start progress bar
    NProgress.start();

    let fontsLoaded = false;
    let timeoutTriggered = false;

    // Manual progress incrementer for realistic animation
    const progressInterval = setInterval(() => {
        if (!fontsLoaded && !timeoutTriggered) {
            NProgress.inc(PROGRESS_INCREMENT_AMOUNT);
        }
    }, PROGRESS_INCREMENT_INTERVAL);

    // Timeout fallback - show content after 4 seconds regardless
    const timeout = setTimeout(() => {
        if (!fontsLoaded) {
            console.log('Font loading timeout reached, showing content with fallback fonts');
            timeoutTriggered = true;
            clearInterval(progressInterval);
            completeLoading(elements);
        }
    }, FONT_LOAD_TIMEOUT);

    // Wait for all fonts to be loaded
    document.fonts.ready.then(() => {
        if (!timeoutTriggered) {
            console.log('All fonts loaded successfully');
            fontsLoaded = true;
            clearInterval(progressInterval);
            clearTimeout(timeout);
            completeLoading(elements);
        }
    }).catch((error) => {
        console.error('Error loading fonts:', error);
        if (!timeoutTriggered) {
            clearInterval(progressInterval);
            clearTimeout(timeout);
            completeLoading(elements);
        }
    });
}

/**
 * Complete the loading process and show content
 * @param {NodeList|HTMLElement} elements - The content container element(s)
 */
function completeLoading(elements) {
    // Finish progress bar to 100%
    NProgress.done();

    // Small delay to let progress bar complete its animation
    setTimeout(() => {
        if (elements instanceof NodeList) {
            elements.forEach(el => showContent(el));
        } else {
            showContent(elements);
        }
    }, 100);
}

/**
 * Show the content by removing loading class and adding loaded class
 * @param {HTMLElement} element - The content container element
 */
function showContent(element) {
    element.classList.remove('fonts-loading');
    element.classList.add('fonts-loaded');
}

// Expose globally for Blade template inline scripts
window.initFontLoader = initFontLoader;
