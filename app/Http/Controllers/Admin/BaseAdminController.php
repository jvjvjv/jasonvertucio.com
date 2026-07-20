<?php

namespace App\Http\Controllers\Admin;

use App\Concerns\ProvidesAdminNavigation;
use App\Http\Controllers\Controller;

abstract class BaseAdminController extends Controller
{
    use ProvidesAdminNavigation;
}
