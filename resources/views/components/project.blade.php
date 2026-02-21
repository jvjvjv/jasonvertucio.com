@props(['project'])

<div class="mt-2">
    <h3 class="font-heading text-xl mb-2 font-bold">{{ $project['projectName'] ?? $project['title'] }}</h3>
    @if(!empty($project['description']))
        <p>{!! $project['description'] !!}</p>
    @endif
    @if(!empty($project['bullets']))
        <ul class="list-disc list-inside mt-2 space-y-1 text-sm">
            @foreach($project['bullets'] as $bullet)
                <li>{{ $bullet }}</li>
            @endforeach
        </ul>
    @endif
</div>
