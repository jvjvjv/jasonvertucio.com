/**
 * Resume Builder - DOCX Generation Module
 *
 * Uses docxtemplater, pizzip, and file-saver to generate DOCX files
 * from a template and JSON data.
 */

import Docxtemplater from 'docxtemplater';
import PizZip from 'pizzip';
import { saveAs } from 'file-saver';
import expressionParser from 'docxtemplater/expressions.js';
import { api } from './api';

/**
 * Generate filename with timestamp: YYMMDDHHMMSS - Jasonvertucio.docx
 */
function generateFilename() {
    const now = new Date();
    const yyyy = String(now.getFullYear());
    const mm = String(now.getMonth() + 1).padStart(2, '0');
    const dd = String(now.getDate()).padStart(2, '0');
    const hh = String(now.getHours()).padStart(2, '0');
    const mi = String(now.getMinutes()).padStart(2, '0');
    const ss = String(now.getSeconds()).padStart(2, '0');

    return `${yyyy}${mm}${dd}-${hh}${mi}${ss} - Jasonvertucio.docx`;
}

/**
 * Convert base64 string to array buffer
 */
function base64ToArrayBuffer(base64) {
    const binaryString = atob(base64);
    const bytes = new Uint8Array(binaryString.length);
    for (let i = 0; i < binaryString.length; i++) {
        bytes[i] = binaryString.charCodeAt(i);
    }
    return bytes;
}

/**
 * Upload generated file back to server
 */
async function uploadGeneratedFile(blob, filename) {
    const formData = new FormData();
    formData.append('file', blob, filename);

    try {
        const result = await api.upload('/resume/docx/completed', formData);
        console.log('Resume copy saved to server:', result);
    } catch (error) {
        console.warn('Error uploading generated resume:', error);
    }
}

/**
 * Display error details on screen (only when debug is true)
 * @param {Error} error - The error object
 * @param {boolean} debug - Whether debug mode is enabled
 */
function displayErrorDetails(error, debug) {
    if (!debug) return;

    const errorDetailsEl = document.getElementById('error-details');
    const errorContentEl = document.getElementById('error-content');

    if (!errorDetailsEl || !errorContentEl) return;

    let errorText = `Error: ${error.message}\n\n`;

    // Add stack trace
    if (error.stack) {
        errorText += `Stack Trace:\n${error.stack}\n\n`;
    }

    // Add docxtemplater-specific errors if available
    if (error.properties) {
        errorText += `Properties:\n${JSON.stringify(error.properties, null, 2)}\n\n`;

        if (error.properties.errors && error.properties.errors.length > 0) {
            errorText += `Template Errors:\n`;
            error.properties.errors.forEach((err, index) => {
                errorText += `\n[${index + 1}] ${err.message}\n`;
                if (err.properties) {
                    errorText += `    File: ${err.properties.file || 'unknown'}\n`;
                    errorText += `    ID: ${err.properties.id || 'unknown'}\n`;
                    if (err.properties.explanation) {
                        errorText += `    Explanation: ${err.properties.explanation}\n`;
                    }
                }
            });
        }
    }

    errorContentEl.textContent = errorText;
    errorDetailsEl.classList.remove('hidden');
}

/**
 * Main function to generate and download the resume
 * @param {string} templateBase64 - Base64 encoded DOCX template
 * @param {object} data - Resume data for template
 * @param {boolean} debug - Whether to show detailed errors on screen
 */
export async function generateAndDownloadResume(templateBase64, data, debug = false) {
    const statusEl = document.getElementById('status-message');

    try {
        if (statusEl) statusEl.textContent = 'Loading template...';

        // Decode base64 template
        const templateContent = base64ToArrayBuffer(templateBase64);

        // Create PizZip instance
        const zip = new PizZip(templateContent);

        // Create Docxtemplater instance with angular expression parser
        const doc = new Docxtemplater(zip, {
            paragraphLoop: true,
            linebreaks: false,
            parser: expressionParser,
        });

        if (statusEl) statusEl.textContent = 'Generating document...';

        // Render the document with data
        doc.render(data);

        // Generate the output blob
        const outputBlob = doc.getZip().generate({
            type: 'blob',
            mimeType: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        });

        const filename = generateFilename();

        if (statusEl) statusEl.textContent = 'Downloading...';

        // Trigger download
        saveAs(outputBlob, filename);

        // Upload copy to server (async, don't wait)
        uploadGeneratedFile(outputBlob, filename);

        if (statusEl) statusEl.textContent = 'Download complete!';

    } catch (error) {
        console.error('Error generating document:', error);
        if (statusEl) {
            statusEl.textContent = 'Error generating document. Please try again.';
        }

        // Log detailed errors if available
        if (error.properties && error.properties.errors) {
            console.error('Template errors:', error.properties.errors);
        }

        // Display errors on screen if debug mode is enabled
        displayErrorDetails(error, debug);
    }
}

// Export for use in Blade templates via window global
window.generateAndDownloadResume = generateAndDownloadResume;
