<?php

namespace App\Enums;

enum AiConversationStatus: string
{
    case Active = 'active';
    case Completed = 'completed';
    case Pass = 'pass';
}
