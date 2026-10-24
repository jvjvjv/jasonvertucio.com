<?php

namespace App\Contracts;

interface ResumeVersionServiceContract
{
    /**
     * Get the current version string.
     */
    public function getCurrentVersion(): string;

    /**
     * Set the version string.
     *
     * @throws \RuntimeException
     */
    public function setVersion(string $version): void;

    /**
     * Get the path to the latest DOCX file by current version.
     */
    public function getLatestDocxPath(): ?string;

    /**
     * Get the path to the latest PDF file by current version.
     */
    public function getLatestPdfPath(): ?string;

    /**
     * Get the DOCX filename for a given version.
     */
    public function getDocxFilename(string $version): string;

    /**
     * Check if a DOCX exists for the current version.
     */
    public function docxExistsForCurrentVersion(): bool;

    /**
     * Check if a PDF exists for the current version.
     */
    public function pdfExistsForCurrentVersion(): bool;

    /**
     * Generate a DOCX file for the current version.
     *
     * @return array{success: bool, path?: string, error?: string}
     */
    public function generateDocx(): array;

    /**
     * Generate a PDF from the DOCX file for the current version.
     *
     * @return array{success: bool, path?: string, error?: string}
     */
    public function generatePdf(): array;

    /**
     * Generate the DOCX only if no currently-valid one exists.
     *
     * @return array{success: bool, path?: string, error?: string, served_cached_document: bool}
     */
    public function ensureDocx(): array;

    /**
     * Generate the PDF only if no currently-valid one exists.
     *
     * @return array{success: bool, path?: string, error?: string, served_cached_document: bool}
     */
    public function ensurePdf(): array;

    /**
     * Get all available versions.
     *
     * @return array<array{version: string, path: string, created: int}>
     */
    public function getAvailableVersions(): array;
}
