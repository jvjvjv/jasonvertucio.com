@props(['section'])

<section class="resume-education resume-section">
    <h2 class="text-2xl font-bold font-heading text-secondary mb-4">Education</h2>

    @foreach($section->entries as $edu)
    <x-resume.diff.entry :entry="$edu" class="education-entry mb-6">
        <h3 class="text-xl font-heading text-primary mb-1"><x-resume.diff.text :entry="$edu" field="institution" /></h3>
        @if($edu->value('degree') !== '' || $edu->value('level') !== '' || $edu->field('degree') || $edu->field('level'))
        <p class="font-medium text-dark"><x-resume.diff.text :entry="$edu" field="degree" /> <x-resume.diff.text :entry="$edu" field="level" /></p>
        @endif
        <p class="text-sm text-gray-600 mb-2">
            <x-resume.diff.text :entry="$edu" field="dates" />
            @if($edu->value('location') !== '' || $edu->field('location'))
                &bull; <x-resume.diff.text :entry="$edu" field="location" />
            @endif
        </p>
        @if($edu->value('description') !== '' || $edu->field('description'))
        <p class="text-sm"><x-resume.diff.text :entry="$edu" field="description" /></p>
        @endif
    </x-resume.diff.entry>
    @endforeach
</section>
