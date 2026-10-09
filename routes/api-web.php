<?php

use App\Http\Controllers\Admin\AiChatBotController;
use App\Http\Controllers\Admin\AiSystemController;
use App\Http\Controllers\Admin\AiSystemPromptController;
use App\Http\Controllers\Admin\ApplicationController;
use App\Http\Controllers\Admin\ApplicationSessionController;
use App\Http\Controllers\Admin\ApplicationStatusUpdateController;
use App\Http\Controllers\Admin\JobUrlParseController;
use App\Http\Controllers\Admin\JobUrlParserController;
use App\Http\Controllers\Admin\ResumeEditorController;
use App\Http\Controllers\Admin\SiteSettingsController;
use App\Http\Controllers\Admin\TargetedResumeController;

/*
|--------------------------------------------------------------------------
| API Web Routes
|--------------------------------------------------------------------------
|
| JSON-returning endpoints called via fetch from React components.
| Lives in routes/web.php (web middleware) so session auth and CSRF work.
| All routes are prefixed with /api to distinguish them from Inertia routes.
|
*/

Route::middleware(['auth', 'can:manage-ai-tools'])
    ->prefix('api/admin/ai')
    ->group(function () {
        Route::get('/personas/mcp-tools', [AiChatBotController::class, 'mcpTools']);
        Route::put('/system-prompts/{aiSystemPrompt}', [AiSystemPromptController::class, 'apiUpdate']);
        Route::post('/systems/fetch-models', [AiSystemController::class, 'fetchModels']);
        Route::get('/systems/{aiSystem}/model-status', [AiSystemController::class, 'modelStatus']);
        Route::post('/systems/{aiSystem}/model-warmup', [AiSystemController::class, 'modelWarmup']);
        Route::post('/job-url-parsers/{jobUrlParser}/preview', [JobUrlParserController::class, 'preview']);
    });

Route::middleware(['auth', 'can:edit-resume'])
    ->prefix('api/admin/resume')
    ->group(function () {
        Route::post('/editor', [ResumeEditorController::class, 'update'])->name('admin.resume.editor.save');

        Route::put('/targeted-resume/{targetedResume}', [TargetedResumeController::class, 'updateMarkdown'])
            ->whereNumber('targetedResume')
            ->name('admin.resume.targeted-resume.update-markdown');

        // {application} is numeric-only so the literal segments (ai-systems,
        // parse-url, parser) can never be taken for one.
        Route::prefix('applications')
            ->name('admin.resume.applications.')
            ->group(function () {
                Route::get('/ai-systems/{aiSystem}/model-status', [AiSystemController::class, 'modelStatus']);
                Route::post('/ai-systems/{aiSystem}/model-warmup', [AiSystemController::class, 'modelWarmup']);
                Route::post('/parse-url', [JobUrlParseController::class, 'parse'])->name('parse-url');
                Route::post('/parser/{parser}/reparse', [JobUrlParseController::class, 'reparse'])->name('parser.reparse');

                Route::post('/', [ApplicationController::class, 'store'])->name('store');

                Route::prefix('{application}')
                    ->whereNumber('application')
                    ->group(function () {
                        Route::put('/', [ApplicationController::class, 'update'])->name('update');
                        Route::post('/apply', [ApplicationController::class, 'apply'])->name('apply');
                        Route::post('/pass', [ApplicationController::class, 'pass'])->name('pass');

                        Route::post('/analysis', [ApplicationSessionController::class, 'analysis'])->name('analysis');
                        Route::post('/chat', [ApplicationSessionController::class, 'chat'])->name('chat');
                        Route::post('/finalize', [ApplicationSessionController::class, 'finalize'])->name('finalize');
                        Route::post('/finalize-cover-letter', [ApplicationSessionController::class, 'finalizeCoverLetter'])->name('finalize-cover-letter');

                        Route::post('/status-updates', [ApplicationStatusUpdateController::class, 'store'])->name('status-updates.store');
                        Route::put('/status-updates/{statusUpdate}', [ApplicationStatusUpdateController::class, 'update'])
                            ->whereNumber('statusUpdate')
                            ->name('status-updates.update');
                        Route::delete('/status-updates/{statusUpdate}', [ApplicationStatusUpdateController::class, 'destroy'])
                            ->whereNumber('statusUpdate')
                            ->name('status-updates.destroy');
                    });
            });
    });

Route::middleware(['auth', 'can:manage-unauthenticated-viewers'])
    ->prefix('api/admin')
    ->group(function () {
        Route::post('/site-settings', [SiteSettingsController::class, 'update']);
    });
