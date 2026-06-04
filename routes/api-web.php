<?php

use App\Http\Controllers\Admin\AiChatBotController;
use App\Http\Controllers\Admin\AiSystemController;
use App\Http\Controllers\Admin\AiSystemPromptController;
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
        Route::get('/chat-bots/mcp-tools', [AiChatBotController::class, 'mcpTools']);
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

        Route::prefix('targeted-builder')
            ->name('admin.resume.targeted.')
            ->group(function () {
                Route::get('/ai-systems/{aiSystem}/model-status', [AiSystemController::class, 'modelStatus']);
                Route::post('/ai-systems/{aiSystem}/model-warmup', [AiSystemController::class, 'modelWarmup']);
                Route::post('/start', [TargetedResumeController::class, 'start'])->name('start');
                Route::post('/{conversation}/chat', [TargetedResumeController::class, 'chat'])->name('chat');
                Route::post('/{conversation}/finalize', [TargetedResumeController::class, 'finalize']);
                Route::post('/{conversation}/finalize-cover-letter', [TargetedResumeController::class, 'finalizeCoverLetter']);
                Route::post('/{conversation}/status-update', [TargetedResumeController::class, 'addStatusUpdate'])->name('status-update');
                Route::post('/parse-url', [JobUrlParseController::class, 'parse'])->name('parse-url');
                Route::post('/parser/{parser}/reparse', [JobUrlParseController::class, 'reparse'])->name('parser.reparse');
            });
    });

Route::middleware(['auth', 'can:manage-unauthenticated-viewers'])
    ->prefix('api/admin')
    ->group(function () {
        Route::post('/site-settings', [SiteSettingsController::class, 'update']);
    });
