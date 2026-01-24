@props(['projects'])

<section class="resume-projects resume-section mb-8">
    <h2 class="text-2xl font-heading text-secondary mb-4">Selected Projects</h2>

    @foreach($projects as $project)
    <div class="project-entry mb-6">
        <h3 class="text-xl font-heading text-primary mb-1">{{ $project['projectName'] }}</h3>
        @if(isset($project['description']) && $project['description'])
        <p class="text-dark mb-2">{{ $project['description'] }}</p>
        @endif
        @if(isset($project['bullets']) && count($project['bullets']) > 0)
        <ul class="project-bullets">
            @foreach($project['bullets'] as $bullet)
            <li class="text-sm">{{ $bullet }}</li>
            @endforeach
        </ul>
        @endif
    </div>
    @endforeach
</section>
