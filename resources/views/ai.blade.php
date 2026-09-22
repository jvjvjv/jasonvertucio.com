@extends("layout")
@section("title", $title)

@section("main")
    <div class="mx-auto max-w-4xl px-4">
        <article class="blog rounded-lg bg-white p-6 md:p-10">
            <div class="legal">
                {!! $content !!}
            </div>

            {{--
                Generated from App\Mcp\Servers\PublicServer's own tool roster,
                not written by hand, so it cannot describe tools the endpoint
                does not serve. Add or remove a tool there and this table
                follows on deploy.
            --}}
            <section class="mt-10 border-t border-gray-200 pt-8" id="mcp-tools">
                <h2>Available tools</h2>

                <p>
                    Connect an MCP client to
                    <code>{{ $endpointUrl }}</code>
                    and these tools are what it will find.
                </p>

                @forelse ($tools as $tool)
                    <div class="mt-6">
                        <h3 class="font-subheading text-xl">
                            <code>{{ $tool['name'] }}</code>
                        </h3>

                        @if ($tool['description'])
                            <p>{{ $tool['description'] }}</p>
                        @endif

                        @if ($tool['arguments'])
                            <ul>
                                @foreach ($tool['arguments'] as $argument)
                                    <li>
                                        <code>{{ $argument['name'] }}</code>
                                        @if ($argument['required'])
                                            <span class="text-sm uppercase tracking-wide">(required)</span>
                                        @endif
                                        @if ($argument['description'])
                                            — {{ $argument['description'] }}
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="text-sm">Takes no arguments.</p>
                        @endif
                    </div>
                @empty
                    <p>No tools are currently published.</p>
                @endforelse
            </section>
        </article>
    </div>
@endsection
