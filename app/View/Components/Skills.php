<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Skills extends Component
{
    public $icons;
    public $workflow;

    public function __construct($icons, $workflow)
    {
        $this->icons = $icons;
        $this->workflow = $workflow;
    }

    public function title(string $title): string {
        switch ($title) {
            case "core_languages":
                return "Core Languages";
            case "frameworks_libraries":
                return "Frameworks &amp; Libraries";
            case "platforms_runtime":
                return "Platforms/Runtime";
            case "devops_infrastructure":
                return "DevOps/Infrastructure";
            case "tools_workflow":
                return "Tools &amp; Workflow";
            case "integrations":
                return "Integrations";
            default:
                return $title;
        }
    }

    public function render(): View|Closure|string
    {
        return view('components.skills');
    }
}
