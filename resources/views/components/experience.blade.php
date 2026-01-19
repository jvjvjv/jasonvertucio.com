<section class="site-section" id="experience">
    <div class="w-full">
        <h2 class="3xl font-heading text-3xl uppercase mb-5 font-bold">Experience</h2>
        <p>
            A selected portion of experience is shown below. For more information, you can reach out to me
            directly.
        </p>
        @foreach ($experience as $job)
            <x-job :job="$job" />
        @endforeach
    </div>
</section>
