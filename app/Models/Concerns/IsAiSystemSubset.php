<?php

namespace App\Models\Concerns;

use Jvjvjv\CodeTalker\Models\AiSystem;

/**
 * Lets a subclass of code-talker's AiSystem stand in for it. The package model
 * names neither its table nor its foreign key, and Eloquent derives both from
 * the runtime class, so a subclass would otherwise read a `chat_systems` table
 * and join on `chat_system_id`. Both are pinned to the parent's, as is the
 * morph class, so a row saved through a subclass reads back as an AiSystem.
 */
trait IsAiSystemSubset
{
    public function getTable(): string
    {
        return 'ai_systems';
    }

    public function getForeignKey(): string
    {
        return 'ai_system_id';
    }

    public function getMorphClass(): string
    {
        return AiSystem::class;
    }
}
