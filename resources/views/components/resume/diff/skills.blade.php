@props(['section'])

@php
    $topGroups = $section->group('top');
    $otherGroups = $section->group('other');
@endphp

<section class="resume-skills resume-section">
    <h2 class="text-2xl font-bold font-heading text-secondary mb-4">Technical Skills</h2>

    @if(count($topGroups) > 0)
        <div class="mb-4">
            @foreach($topGroups as $category)
                <x-resume.diff.entry :entry="$category" class="skill-category mb-3">
                    <h3 class="text-lg font-bold text-primary mb-1"><x-resume.diff.text :entry="$category" field="title" html /></h3>
                    <p class="skill-list">@include('components.resume.diff.skill-items', ['category' => $category])</p>
                </x-resume.diff.entry>
            @endforeach
        </div>
    @endif

    @if(count($otherGroups) > 0)
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        @foreach($otherGroups as $category)
        <x-resume.diff.entry :entry="$category" class="skill-category">
            <h3 class="text-base font-bold text-primary mb-1"><x-resume.diff.text :entry="$category" field="title" html /></h3>
            <p class="text-sm skill-list">@include('components.resume.diff.skill-items', ['category' => $category])</p>
        </x-resume.diff.entry>
        @endforeach
    </div>
    @endif
</section>
