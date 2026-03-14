<?php

namespace App\Enums;

enum SalaryPeriod: string
{
    case PerHour = 'per_hour';
    case PerMonth = 'per_month';
    case PerYear = 'per_year';
}
