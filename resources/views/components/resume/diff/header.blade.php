@props(['section', 'revisionLabel' => null])

@php
    $personal = $section->entries[0];
@endphp

<section class="resume-header mb-8">
    <div class="flex items-start justify-between gap-4">
        <h1 class="text-4xl sm:text-6xl font-heading uppercase tracking-tight text-secondary mb-2">
            <x-resume.diff.text :entry="$personal" field="name" />
        </h1>
        @if($revisionLabel)
        <span class="text-xs text-gray-500 whitespace-nowrap mt-1">{{ $revisionLabel }}</span>
        @endif
    </div>
    <p class="resume-title text-xl text-primary font-subheading uppercase mb-4"><x-resume.diff.text :entry="$personal" field="title" /></p>

    <h2 class="text-2xl font-bold font-heading text-secondary mb-2">Summary</h2>
    <p class="text-lg text-dark leading-relaxed resume-summary"><x-resume.diff.text :entry="$personal" field="summary" html /></p>
</section>
