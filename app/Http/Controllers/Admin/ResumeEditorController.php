<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ResumeDataService;
use App\Services\ResumeVersionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ResumeEditorController extends Controller
{
    protected ResumeDataService $dataService;
    protected ResumeVersionService $versionService;

    public function __construct(ResumeDataService $dataService, ResumeVersionService $versionService)
    {
        $this->dataService = $dataService;
        $this->versionService = $versionService;
    }

    /**
     * GET /admin/resume/editor
     * Show the resume editor with all JSON data
     */
    public function edit(): View
    {
        $data = $this->dataService->getAllEditableData();
        $version = $this->versionService->getCurrentVersion();
        $docxExists = $this->versionService->docxExistsForCurrentVersion();
        $availableVersions = $this->versionService->getAvailableVersions();

        return view('admin.resume.editor', [
            'data' => $data,
            'version' => $version,
            'docxExists' => $docxExists,
            'availableVersions' => $availableVersions,
        ]);
    }

    /**
     * POST /admin/resume/editor
     * Save all JSON data from the editor
     */
    public function update(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'version' => ['required', 'string', 'regex:/^\d{4}\.\d+\.\d+$/'],
            'data' => ['required', 'array'],
            'data.personal' => ['required', 'array'],
            'data.skills' => ['required', 'array'],
            'data.technicalProfile' => ['required', 'array'],
            'data.experience' => ['required', 'array'],
            'data.education' => ['required', 'array'],
            'data.projects' => ['required', 'array'],
        ]);

        try {
            // Save version
            $this->versionService->setVersion($validated['version']);

            // Save all data
            $this->dataService->saveAllEditableData($validated['data']);

            if ($request->wantsJson()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Resume data saved successfully.',
                ]);
            }

            return redirect()
                ->route('admin.resume.editor')
                ->with('success', 'Resume data saved successfully.');

        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ], 422);
            }

            return redirect()
                ->route('admin.resume.editor')
                ->with('error', $e->getMessage());
        }
    }

    /**
     * GET /admin/resume/preview
     * Show preview of the resume with current data
     */
    public function preview(): View
    {
        $data = $this->dataService->getDisplayData();
        $version = $this->versionService->getCurrentVersion();
        $docxExists = $this->versionService->docxExistsForCurrentVersion();

        return view('admin.resume.preview', [
            'data' => $data,
            'version' => $version,
            'docxExists' => $docxExists,
        ]);
    }

    /**
     * POST /admin/resume/generate
     * Generate DOCX for the current version
     */
    public function generate(Request $request): JsonResponse|RedirectResponse
    {
        $result = $this->versionService->generateDocx();

        if ($request->wantsJson()) {
            if ($result['success']) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'DOCX generated successfully.',
                    'path' => $result['path'],
                    'size' => $result['size'] ?? null,
                ]);
            }

            return response()->json([
                'status' => 'error',
                'message' => $result['error'] ?? 'Failed to generate DOCX.',
            ], 500);
        }

        if ($result['success']) {
            return redirect()
                ->route('admin.resume.preview')
                ->with('success', 'DOCX generated successfully.');
        }

        return redirect()
            ->route('admin.resume.preview')
            ->with('error', $result['error'] ?? 'Failed to generate DOCX.');
    }
}
