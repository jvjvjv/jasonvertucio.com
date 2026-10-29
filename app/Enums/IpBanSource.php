<?php

namespace App\Enums;

enum IpBanSource: string
{
    case Honeypot = 'honeypot';
    case Admin = 'admin';
    case SpamEscalation = 'spam_escalation';
}
