{{ $data['personal']['name'] }}
{{ $data['personal']['title'] }}

SUMMARY
{{ str_repeat('=', 60) }}
{{ $data['personal']['summary'] }}

TECHNICAL SKILLS
{{ str_repeat('=', 60) }}
@foreach($data['skills']['top'] ?? [] as $category)
{{ $category['title'] }}:
{{ implode(', ', $category['list']) }}

@endforeach
@foreach($data['skills']['other'] ?? [] as $category)
{{ $category['title'] }}:
{{ implode(', ', $category['list']) }}

@endforeach

EXPERIENCE
{{ str_repeat('=', 60) }}
@foreach($data['experience'] as $job)
{{ $job['jobTitle'] }}
{{ $job['company'] }} | {{ is_array($job['dates']) ? implode(' - ', $job['dates']) : $job['dates'] }}{{ isset($job['location']) ? ' | ' . $job['location'] : '' }}
@foreach($job['bullets'] ?? [] as $bullet)
  - {{ $bullet }}
@endforeach

@endforeach

SELECTED PROJECTS
{{ str_repeat('=', 60) }}
@foreach($data['projects'] as $project)
{{ $project['projectName'] }}
@if(isset($project['description']))
{{ $project['description'] }}
@endif
@foreach($project['bullets'] ?? [] as $bullet)
  - {{ $bullet }}
@endforeach

@endforeach
