@props(['experience'])

<section class="resume-experience resume-section mb-8">
    <h2 class="text-2xl font-bold font-heading text-secondary mb-4">Experience</h2>

    @foreach($experience as $job)
    <div class="job-entry mb-6">
        <h3 class="text-xl font-heading text-primary mb-1">{{ $job['jobTitle'] }}</h3>
        <p class="font-medium text-dark">{{ $job['company'] }}</p>
        <p class="text-sm text-gray-600 mb-2">
            @if(is_array($job['dates']))
                {{ implode(' - ', $job['dates']) }}
            @else
                {{ $job['dates'] }}
            @endif
            @if(isset($job['location']) && $job['location'])
                &bull; {{ $job['location'] }}
            @endif
        </p>
        @if(isset($job['bullets']) && count($job['bullets']) > 0)
        <ul class="job-bullets">
            @foreach($job['bullets'] as $bullet)
            <li class="text-sm">{{ $bullet }}</li>
            @endforeach
        </ul>
        @endif
    </div>
    @endforeach
</section>
