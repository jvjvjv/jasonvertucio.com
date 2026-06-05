<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasOne;
use Jvjvjv\CodeTalker\Models\AiConversation as BaseAiConversation;

class AiConversation extends BaseAiConversation
{
    public function targetedResume(): HasOne
    {
        return $this->hasOne(TargetedResume::class);
    }
}
