@props(['skills'])

@php
    // A draft revision's snapshot renders here unvalidated, so a group missing
    // its title or list shows what it has instead of failing the page.
    $topGroups = array_filter(is_array($skills['top'] ?? null) ? $skills['top'] : [], 'is_array');
    $otherGroups = array_filter(is_array($skills['other'] ?? null) ? $skills['other'] : [], 'is_array');
@endphp

<section class="resume-skills resume-section mb-8">
    <h2 class="text-2xl font-bold font-heading text-secondary mb-4">Technical Skills</h2>

    @if(count($topGroups) > 0)
        <div class="mb-4">
            @foreach($topGroups as $category)
                <div class="skill-category mb-3">
                    <h3 class="text-lg font-bold text-primary mb-1">{!! $category['title'] ?? '' !!}</h3>
                    <p class="skill-list">{!! implode(' &middot; ', is_array($category['list'] ?? null) ? $category['list'] : []) !!}</p>
                </div>
            @endforeach
        </div>
    @endif

    @if(count($otherGroups) > 0)
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        @foreach($otherGroups as $category)
        <div class="skill-category">
            <h3 class="text-base font-bold text-primary mb-1">{!! $category['title'] ?? '' !!}</h3>
            <p class="text-sm skill-list">{!! implode(' &middot; ', is_array($category['list'] ?? null) ? $category['list'] : []) !!}</p>
        </div>
        @endforeach
    </div>
    @endif
</section>
