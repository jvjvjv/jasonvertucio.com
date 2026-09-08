@extends("layout")
@section("title", $title)

@section("main")
    <div class="mx-auto max-w-4xl px-4">
        <article class="blog rounded-lg bg-white p-6 md:p-10">
            <div class="legal">
                {!! $content !!}
            </div>
        </article>
    </div>
@endsection
