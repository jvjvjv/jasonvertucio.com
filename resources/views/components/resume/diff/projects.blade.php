@props(['section'])

<section class="resume-projects resume-section">
    <h2 class="text-2xl font-bold font-heading text-secondary mb-4">Selected Projects</h2>

    @foreach($section->entries as $project)
    <x-resume.diff.entry :entry="$project" class="project-entry mb-6">
        <h3 class="text-xl font-heading text-primary mb-1"><x-resume.diff.text :entry="$project" field="projectName" /></h3>
        @if($project->value('description') !== '' || $project->field('description'))
        <p class="text-dark mb-2"><x-resume.diff.text :entry="$project" field="description" /></p>
        @endif
        <x-resume.diff.bullets :entry="$project" list-class="project-bullets" />
    </x-resume.diff.entry>
    @endforeach
</section>
