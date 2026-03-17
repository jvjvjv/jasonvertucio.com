<?php

namespace App\Enums;

enum TargetedResumeStatus: string
{
    case Draft = 'draft';
    case Finalized = 'finalized';
}
