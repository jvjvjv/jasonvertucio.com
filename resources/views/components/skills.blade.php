<section class="site-section" id="skills">
    <div class="w-full">
        <h2 class="3xl text-3xl uppercase mb-5 font-bold">Skills</h2>

        <h3 class="mb-2 text-2xl !text-primary">Programming Languages
            &amp; Tools</h3>
        <div class="mb-3 flex flex-col gap-y-4">
            @foreach($icons as $set_title => $icon_set)
                <h4 class="text-base uppercase font-medium tracking-wide !text-dark">{!! $title($set_title) !!}</h4>
                <ul class="list-none pl-0 flex flex-wrap gap-x-4">
                    @foreach ($icon_set as $icon)
                    <x-tech-skill
                        :icon="$icon" />
                    @endforeach
                </ul>
            @endforeach

        </div>

        <h3 class="mb-2 text-2xl !text-primary">Workflow</h3>
        <ul class="list-none pl-0 ml-10">
            @foreach ($workflow as $line)
                <li class="relative"><i class="fa fa-check absolute -left-10"></i> {!! $line !!}</li>
            @endforeach
        </ul>
    </div>
</section>
