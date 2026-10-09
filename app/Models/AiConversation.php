<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Jvjvjv\CodeTalker\Models\AiConversation as BaseAiConversation;

class AiConversation extends BaseAiConversation
{
    /**
     * The tracked job this session analyzes, when it is a targeted-resume
     * session.
     */
    public function application(): HasOne
    {
        return $this->hasOne(Application::class, 'ai_conversation_id');
    }

    /**
     * The document built in this session, reached through its application.
     * The application holds both foreign keys, so the far key is the
     * document's own id rather than a column on `targeted_resumes`.
     */
    public function targetedResume(): HasOneThrough
    {
        return $this->hasOneThrough(
            TargetedResume::class,
            Application::class,
            'ai_conversation_id',
            'id',
            'id',
            'targeted_resume_id',
        );
    }

    public function aiPersona(): BelongsTo
    {
        return $this->belongsTo(AiChatBot::class);
    }
}
