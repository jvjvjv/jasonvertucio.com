<section class="site-section" id="projects">
    <div class="w-full">
        <h2 class="3xl text-3xl uppercase mb-5 font-bold">Projects</h2>
        <p>What you see on the left are a few personal projects I've made for work purposes, or for personal
            purposes.
            I have a few more projects that are not listed here, but if you want to see them, please reach out
            to me.
            I am always open to new projects, and I am always looking for new opportunities to learn and grow.
        </p>
        @foreach ($projects as $project)
            <x-project :project="$project" />
        @endforeach
    </div>
</section>
