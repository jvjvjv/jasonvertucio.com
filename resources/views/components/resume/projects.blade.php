@props(['projects'])

@php
    // Same tolerance as the experience component: a draft revision may arrive
    // with a single entry where the list belongs, or with fields missing.
    $entries = array_is_list($projects ?? []) ? $projects : [$projects];
    $entries = array_filter($entries, 'is_array');
@endphp

<section class="resume-projects resume-section mb-8">
    <h2 class="text-2xl font-bold font-heading text-secondary mb-4">Selected Projects</h2>

    @foreach($entries as $project)
    <div class="project-entry mb-6">
        <h3 class="text-xl font-heading text-primary mb-1">{{ $project['projectName'] ?? '' }}</h3>
        @if(!empty($project['description']))
        <p class="text-dark mb-2">{{ $project['description'] }}</p>
        @endif
        @if(is_array($project['bullets'] ?? null) && count($project['bullets']) > 0)
        <ul class="project-bullets">
            @foreach($project['bullets'] as $bullet)
            <li class="text-sm">{{ $bullet }}</li>
            @endforeach
        </ul>
        @endif
    </div>
    @endforeach
</section>
