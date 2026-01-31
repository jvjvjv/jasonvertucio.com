@props(['skills'])

<section class="resume-skills resume-section mb-8">
    <h2 class="text-2xl font-bold font-heading text-secondary mb-4">Technical Skills</h2>

    @if(isset($skills['top']) && count($skills['top']) > 0)
        <div class="mb-4">
            @foreach($skills['top'] as $category)
                <div class="skill-category mb-3">
                    <h3 class="text-lg font-bold text-primary mb-1">{!! $category['title'] !!}</h3>
                    <p class="skill-list">{!! implode(' &middot; ', $category['list']) !!}</p>
                </div>
            @endforeach
        </div>
    @endif

    @if(isset($skills['other']) && count($skills['other']) > 0)
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        @foreach($skills['other'] as $category)
        <div class="skill-category">
            <h3 class="text-base font-bold text-primary mb-1">{!! $category['title'] !!}</h3>
            <p class="text-sm skill-list">{!! implode(' &middot; ', $category['list']) !!}</p>
        </div>
        @endforeach
    </div>
    @endif
</section>
