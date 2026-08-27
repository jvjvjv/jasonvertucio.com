<?php

use App\Http\Controllers\Admin\AiChatBotController;
use App\Http\Controllers\Admin\AiConversationController;
use App\Http\Controllers\Admin\AiMemoryController;
use App\Http\Controllers\Admin\AiSystemController;
use App\Http\Controllers\Admin\AiSystemPromptController;
use App\Http\Controllers\Admin\AiToolsController;
use App\Http\Controllers\Admin\JobUrlParserController;
use App\Http\Middleware\HandleInertiaRequests;

// AI Tools routes - requires auth + manage-ai-tools permission
Route::middleware(['web', 'auth', 'can:manage-ai-tools', HandleInertiaRequests::class])
    ->prefix('admin/ai')
    ->name('admin.ai.')
    ->group(function () {
        Route::get('/', [AiToolsController::class, 'index'])->name('index');

        // AI Systems CRUD
        Route::get('/systems', [AiSystemController::class, 'index'])->name('systems.index');
        Route::get('/systems/new', [AiSystemController::class, 'create'])->name('systems.create');
        Route::post('/systems', [AiSystemController::class, 'store'])->name('systems.store');
        Route::get('/systems/{aiSystem}', [AiSystemController::class, 'edit'])->name('systems.edit');
        Route::put('/systems/{aiSystem}', [AiSystemController::class, 'update'])->name('systems.update');
        Route::post('/systems/{aiSystem}/duplicate', [AiSystemController::class, 'duplicate'])->name('systems.duplicate');
        Route::delete('/systems/{aiSystem}', [AiSystemController::class, 'destroy'])->name('systems.destroy');
        Route::get('/systems/{aiSystem}/logs', [AiSystemController::class, 'logs'])->name('systems.logs');

        // AI System Prompts CRUD
        Route::get('/system-prompts', [AiSystemPromptController::class, 'index'])->name('system-prompts.index');
        Route::get('/system-prompts/new', [AiSystemPromptController::class, 'create'])->name('system-prompts.create');
        Route::post('/system-prompts', [AiSystemPromptController::class, 'store'])->name('system-prompts.store');
        Route::get('/system-prompts/{aiSystemPrompt}', [AiSystemPromptController::class, 'edit'])->name('system-prompts.edit');
        Route::put('/system-prompts/{aiSystemPrompt}', [AiSystemPromptController::class, 'update'])->name('system-prompts.update');
        Route::delete('/system-prompts/{aiSystemPrompt}', [AiSystemPromptController::class, 'destroy'])->name('system-prompts.destroy');

        // AI Memories CRUD
        Route::get('/memories', [AiMemoryController::class, 'index'])->name('memories.index');
        Route::get('/memories/new', [AiMemoryController::class, 'create'])->name('memories.create');
        Route::post('/memories', [AiMemoryController::class, 'store'])->name('memories.store');
        Route::get('/memories/{memory}', [AiMemoryController::class, 'edit'])->name('memories.edit');
        Route::put('/memories/{memory}', [AiMemoryController::class, 'update'])->name('memories.update');
        Route::delete('/memories/{memory}', [AiMemoryController::class, 'destroy'])->name('memories.destroy');
        Route::post('/memories/rebuild/{feature}', [AiMemoryController::class, 'rebuild'])->name('memories.rebuild');

        // AI Conversations
        Route::get('/conversations', [AiConversationController::class, 'index'])->name('conversations.index');
        Route::post('/conversations/backfill-usage', [AiConversationController::class, 'queueUsageBackfill'])->name('conversations.backfill-usage');
        Route::get('/conversations/{conversation}', [AiConversationController::class, 'show'])->name('conversations.show');
        Route::delete('/conversations/{conversation}', [AiConversationController::class, 'destroy'])->name('conversations.destroy');

        // AI Chat Bots CRUD
        // The literal mcp-tools route must precede the {aiChatBot} wildcard so
        // it is not matched as a bot slug.
        Route::get('/personas', [AiChatBotController::class, 'index'])->name('bots.index');
        Route::get('/personas/new', [AiChatBotController::class, 'create'])->name('bots.create');
        Route::get('/personas/mcp-tools', [AiChatBotController::class, 'mcpTools'])->name('bots.mcp-tools');

        Route::post('/personas', [AiChatBotController::class, 'store'])->name('bots.store');
        Route::get('/personas/{aiChatBot}', [AiChatBotController::class, 'edit'])->name('bots.edit');
        Route::put('/personas/{aiChatBot}', [AiChatBotController::class, 'update'])->name('bots.update');
        Route::delete('/personas/{aiChatBot}', [AiChatBotController::class, 'destroy'])->name('bots.destroy');

        // Job URL Parsers
        Route::get('/job-url-parsers', [JobUrlParserController::class, 'index'])->name('job-url-parsers.index');
        Route::get('/job-url-parsers/{jobUrlParser}', [JobUrlParserController::class, 'edit'])->name('job-url-parsers.edit');
        Route::put('/job-url-parsers/{jobUrlParser}', [JobUrlParserController::class, 'update'])->name('job-url-parsers.update');

        Route::post('/job-url-parsers/{jobUrlParser}/approve', [JobUrlParserController::class, 'approve'])->name('job-url-parsers.approve');
        Route::post('/job-url-parsers/{jobUrlParser}/reject', [JobUrlParserController::class, 'reject'])->name('job-url-parsers.reject');
        Route::delete('/job-url-parsers/{jobUrlParser}', [JobUrlParserController::class, 'destroy'])->name('job-url-parsers.destroy');
    });
