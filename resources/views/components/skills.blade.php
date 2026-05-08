@props(['skills'])

<section class="site-section" id="skills">
    <div class="w-full">
        <h2 class="3xl text-3xl uppercase mb-5 font-bold">Skills</h2>

        @foreach([$skills['top'], $skills['other']] as $group)
            @foreach($group as $category)
                <div class="mb-6">
                    <h4 class="text-base uppercase font-medium tracking-wide text-dark! mb-2">
                        {{ $category['title'] }}
                    </h4>
                    <ul class="list-none pl-0 flex flex-wrap gap-2">
                        @foreach($category['list'] as $skill)
                            <li class="px-3 py-1 bg-dark/10 text-dark text-sm rounded-full">{{ $skill }}</li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        @endforeach
    </div>
</section>
