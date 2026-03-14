#!/usr/bin/env node

import Docxtemplater from 'docxtemplater';
import PizZip from 'pizzip';
import fs from 'fs';
import path from 'path';
import expressionParser from 'docxtemplater/expressions.js';

const args = process.argv.slice(2);

if (args.length !== 3) {
    console.log(JSON.stringify({
        success: false,
        error: 'Invalid arguments. Usage: node generate-targeted-resume.js <templatePath> <dataJsonPath> <outputPath>'
    }));
    process.exit(1);
}

const [templatePath, dataJsonPath, outputPath] = args;

try {
    if (!fs.existsSync(templatePath)) {
        throw new Error(`Template file not found: ${templatePath}`);
    }

    if (!fs.existsSync(dataJsonPath)) {
        throw new Error(`Data file not found: ${dataJsonPath}`);
    }

    const templateContent = fs.readFileSync(templatePath);
    const data = JSON.parse(fs.readFileSync(dataJsonPath, 'utf8'));
    const zip = new PizZip(templateContent);

    const doc = new Docxtemplater(zip, {
        paragraphLoop: true,
        linebreaks: true,
        parser: expressionParser,
    });

    doc.render(data);

    const outputBuffer = doc.getZip().generate({
        type: 'nodebuffer',
        mimeType: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    });

    const outputDir = path.dirname(outputPath);
    if (!fs.existsSync(outputDir)) {
        fs.mkdirSync(outputDir, { recursive: true });
    }

    fs.writeFileSync(outputPath, outputBuffer);

    console.log(JSON.stringify({
        success: true,
        path: outputPath,
        size: outputBuffer.length,
    }));
} catch (error) {
    const errorDetails = {
        success: false,
        error: error.message,
    };

    if (error.properties && error.properties.errors) {
        errorDetails.templateErrors = error.properties.errors.map(err => ({
            message: err.message,
            id: err.properties?.id,
            explanation: err.properties?.explanation,
        }));
    }

    console.log(JSON.stringify(errorDetails));
    process.exit(1);
}
