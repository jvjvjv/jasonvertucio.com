<?php

namespace App\Enums;

enum TargetedResumeStatus: string {
    case Draft = 'draft';
    case Finalized = 'finalized';
    case Applied = 'applied';
    case Interviewing = 'interviewing';
    case Interviewed = 'interviewed';
    case Offered = 'offered';
    case Accepted = 'accepted';
    case Hired = 'hired';
    case Rejected = 'rejected';
}
