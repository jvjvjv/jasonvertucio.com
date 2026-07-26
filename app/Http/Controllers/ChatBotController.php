<?php

namespace App\Http\Controllers;

use Jvjvjv\CodeTalker\Http\Controllers\ChatBotController as PackageChatBotController;

/**
 * The host entry point for the chat-bot routes.
 *
 * Every action is the package's. The host's customizations — role-filtered bot
 * listings and the extra `allowed_roles` / `previousHref` props — live in
 * App\Services\ChatBot\* and reach the package controller through the container
 * bindings in AppServiceProvider.
 *
 * This subclass is kept so the published routes/codetalker-chatbots.php can go
 * on naming a host controller, and so future host-only actions have a home.
 */
class ChatBotController extends PackageChatBotController
{
}
