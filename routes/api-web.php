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
        Route::post('/editor', [ResumeEditorController::class, 'update']);
        Route::get('/targeted-builder/ai-systems/{aiSystem}/model-status', [AiSystemController::class, 'modelStatus']);
        Route::post('/targeted-builder/ai-systems/{aiSystem}/model-warmup', [AiSystemController::class, 'modelWarmup']);
        Route::post('/targeted-builder/start', [TargetedResumeController::class, 'start']);
        Route::post('/targeted-builder/{conversation}/chat', [TargetedResumeController::class, 'chat']);
        Route::post('/targeted-builder/{conversation}/finalize', [TargetedResumeController::class, 'finalize']);
        Route::post('/targeted-builder/{conversation}/finalize-cover-letter', [TargetedResumeController::class, 'finalizeCoverLetter']);
        Route::post('/targeted-builder/{conversation}/status-update', [TargetedResumeController::class, 'addStatusUpdate']);
        Route::post('/targeted-builder/parse-url', [JobUrlParseController::class, 'parse']);
        Route::post('/targeted-builder/parser/{parser}/reparse', [JobUrlParseController::class, 'reparse']);
    });

Route::middleware(['auth', 'can:manage-unauthenticated-viewers'])
    ->prefix('api/admin')
    ->group(function () {
        Route::post('/site-settings', [SiteSettingsController::class, 'update']);
    });
