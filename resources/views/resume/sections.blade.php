{{-- The résumé's sections as the public page renders them. --}}
{{-- Header with name, title, and summary --}}
<x-resume.header
    :name="$personal['name'] ?? ''"
    :title="$personal['title'] ?? ''"
    :summary="$personal['summary'] ?? ''"
    :revision-label="$revisionLabel"
/>

{{-- Technical Skills --}}
<x-resume.skills :skills="$data['skills'] ?? []" />

{{-- Experience --}}
<x-resume.experience :experience="$data['experience'] ?? []" />

{{-- Education (only present for authorized viewers or a candidate revision) --}}
@if(!empty($data['education']))
<x-resume.education :education="$data['education']" />
@endif

{{-- Selected Projects --}}
<x-resume.projects :projects="$data['projects'] ?? []" />
