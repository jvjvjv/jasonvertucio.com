<?php

namespace App\Enums;

enum CompanyInsightCategory: string
{
    case Culture = 'culture';
    case Values = 'values';
    case Mission = 'mission';
    case Products = 'products';
    case Leadership = 'leadership';
    case News = 'news';
    case InterviewProcess = 'interview-process';
    case Compensation = 'compensation';
    case Risks = 'risks';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::InterviewProcess => 'Interview process',
            default => ucfirst($this->value),
        };
    }
}
