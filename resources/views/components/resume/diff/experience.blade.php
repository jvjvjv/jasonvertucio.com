@props(['section'])

<section class="resume-experience resume-section">
    <h2 class="text-2xl font-bold font-heading text-secondary mb-4">Professional Experience</h2>

    @foreach($section->entries as $job)
    <x-resume.diff.entry :entry="$job" class="job-entry mb-6">
        <h3 class="text-xl font-heading text-primary mb-1"><x-resume.diff.text :entry="$job" field="jobTitle" /></h3>
        <p class="font-medium text-dark"><x-resume.diff.text :entry="$job" field="company" /></p>
        <p class="text-sm text-gray-600 mb-2">
            <x-resume.diff.text :entry="$job" field="dates" />
            @if($job->value('location') !== '' || $job->field('location'))
                &bull; <x-resume.diff.text :entry="$job" field="location" />
            @endif
        </p>
        <x-resume.diff.bullets :entry="$job" list-class="job-bullets" />
    </x-resume.diff.entry>
    @endforeach
</section>
