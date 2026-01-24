@props(['route', 'icon' => null, 'label', 'description'])

<a href="{{ route($route) }}"
    class="block p-6 bg-white rounded-lg hover:shadow-sm active:bg-gray-100 transition-all border border-gray-200">
    <div class="flex items-center">
        @if($icon)
            <div class="shrink-0 w-12 h-12 bg-primary/10 rounded-lg flex items-center justify-center">
                <i class="text-2xl fa-classic fa-{{ $icon }}"></i>
            </div>
        @endif
        <div class="ml-4">
            <h2 class="text-lg font-semibold text-gray-900">{{ $label }}</h2>
            <p class="text-sm text-gray-500">{{{ $description}}}</p>
        </div>
    </div>
</a>
