<?php

namespace App\Services;

use App\Contracts\SessionSystemEligibility;
use App\Models\ChatSystem;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Jvjvjv\CodeTalker\Models\AiSystem;

/**
 * Which AI systems may run a targeted-resume session: active chat systems
 * that are the targeted-resume or cover-letter default, or are assigned the
 * Targeted Resume or Cover Letter prompt.
 */
class TargetedResumeSystems implements SessionSystemEligibility
{
    public const array FEATURES = ['targeted-resume', 'cover-letter'];

    public const string INELIGIBLE_MESSAGE = 'That AI system is not set up for targeted resumes.';

    public const array PROMPT_IDS = [
        TargetedResumeService::PROMPT_ID_TARGETED_RESUME,
        TargetedResumeService::PROMPT_ID_COVER_LETTER,
    ];

    /**
     * @return Collection<int, AiSystem>
     */
    public function selectable(): Collection
    {
        return ChatSystem::active()
            ->where(function ($query): void {
                $query->whereIn('system_prompt_id', self::PROMPT_IDS)
                    ->orWhereHas('featureDefaults', fn ($defaults) => $defaults->whereIn('feature', self::FEATURES));
            })
            ->orderBy('name')
            ->get();
    }

    public function isEligible(AiSystem $system): bool
    {
        return $this->selectable()->contains(fn (AiSystem $eligible): bool => $eligible->is($system));
    }

    /**
     * A validation rule refusing an existing AI system that is not eligible.
     * A missing one is left to the `exists` rule, so it keeps its own message.
     */
    public function validationRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $id = filter_var($value, FILTER_VALIDATE_INT);
            $system = $id === false ? null : AiSystem::find($id);

            if ($system !== null && ! $this->isEligible($system)) {
                $fail(self::INELIGIBLE_MESSAGE);
            }
        };
    }
}
