#!/usr/bin/env node
/**
 * Resume DOCX Generator - Server-side version
 *
 * Adapted from resources/js/resume.js for Node.js execution.
 * Uses docxtemplater, pizzip, and expression parser to generate DOCX files.
 *
 * Usage: node generate-resume.js <templatePath> <dataJsonPath> <outputPath>
 *
 * Arguments:
 *   templatePath  - Path to the DOCX template file
 *   dataJsonPath  - Path to JSON file containing resume data
 *   outputPath    - Path where the generated DOCX will be saved
 *
 * Output: JSON to stdout with success status and path or error details
 */

import Docxtemplater from 'docxtemplater';
import PizZip from 'pizzip';
import fs from 'fs';
import path from 'path';
import expressionParser from 'docxtemplater/expressions.js';

// Get command line arguments
const args = process.argv.slice(2);

if (args.length !== 3) {
    console.log(JSON.stringify({
        success: false,
        error: 'Invalid arguments. Usage: node generate-resume.js <templatePath> <dataJsonPath> <outputPath>'
    }));
    process.exit(1);
}

const [templatePath, dataJsonPath, outputPath] = args;

try {
    // Validate template file exists
    if (!fs.existsSync(templatePath)) {
        throw new Error(`Template file not found: ${templatePath}`);
    }

    // Validate data file exists
    if (!fs.existsSync(dataJsonPath)) {
        throw new Error(`Data file not found: ${dataJsonPath}`);
    }

    // Read template
    const templateContent = fs.readFileSync(templatePath);

    // Read and parse data
    const data = JSON.parse(fs.readFileSync(dataJsonPath, 'utf8'));

    // Create PizZip instance
    const zip = new PizZip(templateContent);

    // Create Docxtemplater instance with angular expression parser
    const doc = new Docxtemplater(zip, {
        paragraphLoop: true,
        linebreaks: false,
        parser: expressionParser,
    });

    // Render the document with data
    doc.render(data);

    // Post-process: convert plain-text URL into a Word hyperlink
    if (data.url) {
        const fullUrl = data.url;
        const displayUrl = fullUrl.replace(/^https?:\/\//, '');
        const renderedZip = doc.getZip();

        // Add hyperlink relationship to document rels
        const relsFile = 'word/_rels/document.xml.rels';
        let relsXml = renderedZip.file(relsFile).asText();
        const hyperlinkRelId = 'rIdUrl1';
        const newRel = `<Relationship Id="${hyperlinkRelId}" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="${fullUrl}" TargetMode="External"/>`;
        relsXml = relsXml.replace('</Relationships>', `${newRel}</Relationships>`);
        renderedZip.file(relsFile, relsXml);

        // Replace the rendered URL text run with a hyperlink element
        // The rendered URL may be split across XML runs; escape for regex use
        const escapedUrl = fullUrl.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        let docXml = renderedZip.file('word/document.xml').asText();

        // Match a <w:r> run containing the full URL text and replace with hyperlink
        const runPattern = new RegExp(
            `(<w:r(?:\\s[^>]*)?>(?:<w:rPr>[\\s\\S]*?</w:rPr>)?<w:t(?:[^>]*)?>)${escapedUrl}(</w:t></w:r>)`,
        );
        const hyperlinkXml = `<w:hyperlink r:id="${hyperlinkRelId}" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">$1${displayUrl}$2</w:hyperlink>`;
        docXml = docXml.replace(runPattern, hyperlinkXml);
        renderedZip.file('word/document.xml', docXml);
    }

    // Generate the output buffer
    const outputBuffer = doc.getZip().generate({
        type: 'nodebuffer',
        mimeType: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    });

    // Ensure output directory exists
    const outputDir = path.dirname(outputPath);
    if (!fs.existsSync(outputDir)) {
        fs.mkdirSync(outputDir, { recursive: true });
    }

    // Write the file
    fs.writeFileSync(outputPath, outputBuffer);

    // Output success
    console.log(JSON.stringify({
        success: true,
        path: outputPath,
        size: outputBuffer.length
    }));

} catch (error) {
    // Handle docxtemplater-specific errors
    let errorDetails = {
        success: false,
        error: error.message
    };

    if (error.properties && error.properties.errors) {
        errorDetails.templateErrors = error.properties.errors.map(err => ({
            message: err.message,
            id: err.properties?.id,
            explanation: err.properties?.explanation
        }));
    }

    console.log(JSON.stringify(errorDetails));
    process.exit(1);
}
