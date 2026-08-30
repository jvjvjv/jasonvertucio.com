@props(['education'])

@php
    // Same tolerance as the experience component: a draft revision may arrive
    // with a single entry where the list belongs, or with fields missing.
    $entries = array_is_list($education ?? []) ? $education : [$education];
    $entries = array_filter($entries, 'is_array');
@endphp

<section class="resume-education resume-section mb-8">
    <h2 class="text-2xl font-bold font-heading text-secondary mb-4">Education</h2>

    @foreach($entries as $edu)
    <div class="education-entry mb-6">
        <h3 class="text-xl font-heading text-primary mb-1">{{ $edu['institution'] ?? '' }}</h3>
        @if(!empty($edu['degree']) || !empty($edu['level']))
        <p class="font-medium text-dark">{{ trim(($edu['degree'] ?? '').' '.($edu['level'] ?? '')) }}</p>
        @endif
        <p class="text-sm text-gray-600 mb-2">
            @if(is_array($edu['dates'] ?? null) && count($edu['dates']) > 0)
                {{ implode(' - ', $edu['dates']) }}
            @endif
            @if(!empty($edu['location']))
                &bull; {{ $edu['location'] }}
            @endif
        </p>
        @if(!empty($edu['description']))
        <p class="text-sm">{{ $edu['description'] }}</p>
        @endif
    </div>
    @endforeach
</section>
