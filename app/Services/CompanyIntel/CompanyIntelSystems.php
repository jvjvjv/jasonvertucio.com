<?php

namespace App\Services\CompanyIntel;

use App\Contracts\SessionSystemEligibility;
use App\Models\ChatSystem;
use Illuminate\Database\Eloquent\Collection;
use Jvjvjv\CodeTalker\Models\AiSystem;

/**
 * Which AI systems may gather intel: the `company-intel` default, and the
 * active chat systems assigned the Company Intel prompt.
 */
class CompanyIntelSystems implements SessionSystemEligibility
{
    public function default(): ?AiSystem
    {
        $system = AiSystem::defaultForFeature(CompanyIntelService::FEATURE);

        return $system?->is_active ? $system : null;
    }

    /**
     * @return Collection<int, AiSystem>
     */
    public function eligible(): Collection
    {
        return ChatSystem::active()
            ->where('system_prompt_id', CompanyIntelService::PROMPT_ID_COMPANY_INTEL)
            ->orderBy('name')
            ->get();
    }

    /**
     * The eligible systems including the default, whatever its prompt.
     *
     * @return Collection<int, AiSystem>
     */
    public function selectable(): Collection
    {
        $default = $this->default();
        $eligible = $this->eligible();

        if ($default !== null && ! $eligible->contains(fn (AiSystem $system): bool => $system->is($default))) {
            $eligible->push($default);
        }

        return $eligible->sortBy('name')->values();
    }

    public function isEligible(AiSystem $system): bool
    {
        if ($this->default()?->is($system)) {
            return true;
        }

        return $this->eligible()->contains(fn (AiSystem $eligible): bool => $eligible->is($system));
    }

    /**
     * The system to use: the one asked for, which must be eligible, or else
     * the default. Null when neither is available.
     */
    public function resolve(?int $requestedId): ?AiSystem
    {
        if ($requestedId === null) {
            return $this->default();
        }

        $system = AiSystem::find($requestedId);

        return $system !== null && $this->isEligible($system) ? $system : null;
    }
}
