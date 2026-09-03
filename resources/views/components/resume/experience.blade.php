@props(['experience'])

@php
    // A draft revision's snapshot is rendered here unvalidated so a reviewer can
    // see what a persona actually proposed. A section sent as one entry instead
    // of the list of entries is shown as that single entry rather than blowing
    // up the page; missing fields render empty.
    $jobs = array_is_list($experience ?? []) ? $experience : [$experience];
    $jobs = array_filter($jobs, 'is_array');
@endphp

<section class="resume-experience resume-section mb-8">
    <h2 class="text-2xl font-bold font-heading text-secondary mb-4">Experience</h2>

    @foreach($jobs as $job)
    <div class="job-entry mb-6">
        <h3 class="text-xl font-heading text-primary mb-1">{{ $job['jobTitle'] ?? '' }}</h3>
        <p class="font-medium text-dark">{{ $job['company'] ?? '' }}</p>
        <p class="text-sm text-gray-600 mb-2">
            @if(is_array($job['dates'] ?? null))
                {{ implode(' - ', $job['dates']) }}
            @else
                {{ $job['dates'] ?? '' }}
            @endif
            @if(!empty($job['location']))
                &bull; {{ $job['location'] }}
            @endif
        </p>
        @if(is_array($job['bullets'] ?? null) && count($job['bullets']) > 0)
        <ul class="job-bullets">
            @foreach($job['bullets'] as $bullet)
            <li class="text-sm">{{ $bullet }}</li>
            @endforeach
        </ul>
        @endif
    </div>
    @endforeach
</section>
