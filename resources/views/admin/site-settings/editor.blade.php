@extends('layout')

@section('title', 'Site Settings — Navigation')

@section('main')
<div class="max-w-4xl mx-auto px-4 py-8"
     x-data="navEditor({{ \Illuminate\Support\Js::from($links) }})"
     x-init="init()">

    <a href="{{ route('admin.index') }}" class="text-sm text-primary hover:underline">&larr; Back to Admin</a>
    <h1 class="text-3xl font-heading font-bold text-primary mt-2 mb-2">Site Settings</h1>
    <p class="text-sm text-gray-500 mb-8">Manage the sidebar navigation links. Drag rows to reorder.</p>

    @if(session('success'))
        <div class="mb-6 px-4 py-3 bg-green-50 border border-green-200 text-green-800 rounded-lg text-sm">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="mb-6 px-4 py-3 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm">
            {{ session('error') }}
        </div>
    @endif

    <form method="POST" action="{{ route('admin.site-settings.update') }}" @submit.prevent="submit">
        @csrf

        {{-- Link rows --}}
        <div x-ref="sortableContainer" class="space-y-2 mb-6">
            <template x-for="(link, index) in links" :key="link._id">
                <div class="bg-white border border-gray-200 rounded-lg overflow-hidden"
                     :class="link._open ? 'border-primary/40' : ''"
                     :data-id="link._id">

                    {{-- Header row — click to toggle, drag handle on left, delete on right --}}
                    <div class="flex items-center gap-3 px-4 py-3 cursor-pointer select-none"
                         :class="link._open ? 'bg-gray-50 border-b border-gray-200' : 'hover:bg-gray-50'"
                         @click="toggleCard(index)">

                        {{-- Drag handle: mousedown collapses all before Sortable takes over --}}
                        <span class="drag-handle cursor-grab text-gray-300 hover:text-gray-500 shrink-0"
                              title="Drag to reorder"
                              @mousedown.stop="collapseAll()">
                            <i class="fa-solid fa-grip-vertical"></i>
                        </span>

                        {{-- Title --}}
                        <span class="flex-1 font-medium text-sm truncate"
                              :class="link.divider ? 'text-gray-400 italic' : 'text-gray-700'"
                              x-text="link.divider ? '— divider —' : (link.label || '(new link)')"></span>

                        {{-- Permission badge --}}
                        <span x-show="!link.divider && link.can"
                              class="hidden sm:inline-flex items-center gap-1 px-2 py-0.5 bg-amber-50 border border-amber-200 text-amber-700 text-xs rounded-full shrink-0"
                              x-text="link.can"></span>

                        {{-- Chevron --}}
                        <i class="fa-solid text-gray-400 text-xs shrink-0 transition-transform duration-200"
                           :class="link._open ? 'fa-chevron-up' : 'fa-chevron-down'"></i>

                        {{-- Delete — stop propagation so it doesn't toggle the card --}}
                        <button type="button"
                                @click.stop="remove(index)"
                                class="shrink-0 text-xs text-red-400 hover:text-red-600 transition-colors"
                                title="Remove link">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>

                    {{-- Expandable body --}}
                    <div x-show="link._open"
                         x-transition:enter="transition-all duration-150 ease-out"
                         x-transition:enter-start="opacity-0 -translate-y-1"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         x-transition:leave="transition-all duration-100 ease-in"
                         x-transition:leave-start="opacity-100 translate-y-0"
                         x-transition:leave-end="opacity-0 -translate-y-1"
                         class="px-4 pb-4 pt-3">

                        {{-- Divider body --}}
                        <template x-if="link.divider">
                            <p class="text-sm text-gray-400 italic">This is a visual divider. It renders as a horizontal rule in the navigation dropdown.</p>
                        </template>

                        {{-- Link fields --}}
                        <template x-if="!link.divider">
                            <div class="grid gap-3 md:grid-cols-2">
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Label <span class="text-red-400">*</span></label>
                                    <input type="text" x-model="link.label"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary/50"
                                        placeholder="#Skills">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">URL / Href <span class="text-red-400">*</span></label>
                                    <input type="text" x-model="link.href"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary/50"
                                        placeholder="/#skills or https://...">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Aria Label</label>
                                    <input type="text" x-model="link.ariaLabel"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary/50"
                                        placeholder="Accessible label for screen readers">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Hover text</label>
                                    <input type="text" x-model="link.hover"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary/50"
                                        placeholder="Tooltip shown on hover">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Target</label>
                                    <select x-model="link.target"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary/50 bg-white">
                                        <option value="">Same tab</option>
                                        <option value="_blank">New tab (_blank)</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Required permission</label>
                                    <p class="text-xs text-gray-400 mb-1">Hide from users without this permission. Leave blank for everyone.</p>
                                    <select x-model="link.can"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary/50 bg-white">
                                        <option value="">Public (no restriction)</option>
                                        <option value="authenticated">Authenticated users only</option>
                                        @foreach(\Spatie\Permission\Models\Permission::orderBy('name')->pluck('name') as $perm)
                                            <option value="{{ $perm }}">{{ $perm }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </template>
        </div>

        {{-- Add link / divider buttons --}}
        <div class="flex items-center gap-3 mb-8">
            <button type="button" @click="add"
                class="inline-flex items-center gap-2 px-4 py-2 border-2 border-dashed border-gray-300 text-gray-500 rounded-lg text-sm hover:border-primary hover:text-primary transition-colors">
                <i class="fa-solid fa-plus"></i> Add navigation link
            </button>
            <button type="button" @click="addDivider"
                class="inline-flex items-center gap-2 px-4 py-2 border-2 border-dashed border-gray-300 text-gray-500 rounded-lg text-sm hover:border-gray-400 hover:text-gray-600 transition-colors">
                <i class="fa-solid fa-minus"></i> Add divider
            </button>
        </div>

        {{-- Save --}}
        <div class="flex items-center gap-4">
            <button type="submit"
                :disabled="saving"
                class="inline-flex items-center gap-2 px-6 py-2.5 bg-primary text-white text-sm font-medium rounded-lg hover:bg-primary/90 disabled:opacity-60 transition-colors">
                <i class="fa-solid fa-floppy-disk"></i>
                <span x-text="saving ? 'Saving…' : 'Save Navigation'"></span>
            </button>
            <span x-show="savedMessage" x-transition class="text-sm text-green-600">
                <i class="fa-solid fa-check"></i> Saved!
            </span>
            <span x-show="errorMessage" x-transition class="text-sm text-red-600" x-text="errorMessage"></span>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>
<script>
function navEditor(initialLinks) {
    return {
        links: [],
        saving: false,
        savedMessage: false,
        errorMessage: '',
        _idCounter: 0,
        _sortable: null,

        init() {
            // _open tracks collapsed/expanded state; not persisted
            this.links = initialLinks.map(link => ({ _id: this._idCounter++, _open: false, ...link }));
            this.$nextTick(() => this.initSortable());
        },

        toggleCard(index) {
            this.links[index]._open = !this.links[index]._open;
        },

        collapseAll() {
            this.links.forEach(link => { link._open = false; });
        },

        add() {
            this.links.push({ _id: this._idCounter++, _open: true, label: '', href: '', ariaLabel: '', hover: '', target: '', can: '' });
        },

        addDivider() {
            this.links.push({ _id: this._idCounter++, _open: false, divider: true });
        },

        remove(index) {
            this.links.splice(index, 1);
        },

        initSortable() {
            const el = this.$refs.sortableContainer;
            if (!el || typeof Sortable === 'undefined') { return; }

            if (this._sortable) {
                this._sortable.destroy();
                this._sortable = null;
            }

            this._sortable = Sortable.create(el, {
                handle: '.drag-handle',
                animation: 150,
                onEnd: (evt) => {
                    if (evt.oldIndex === evt.newIndex) { return; }

                    // Undo what SortableJS physically did to the DOM so Alpine's re-render
                    // is the sole authority on DOM order — otherwise they fight each other.
                    if (evt.oldIndex < evt.newIndex) {
                        evt.from.insertBefore(evt.item, evt.from.children[evt.oldIndex]);
                    } else {
                        evt.from.insertBefore(evt.item, evt.from.children[evt.oldIndex + 1]);
                    }

                    // Now update the Alpine array — Alpine re-renders and produces the correct DOM.
                    const moved = this.links.splice(evt.oldIndex, 1)[0];
                    this.links.splice(evt.newIndex, 0, moved);
                },
            });
        },

        async submit() {
            this.saving = true;
            this.savedMessage = false;
            this.errorMessage = '';

            const token = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
            const body = new FormData();

            this.links.forEach((link, i) => {
                if (link.divider) {
                    body.append(`links[${i}][divider]`, '1');
                } else {
                    body.append(`links[${i}][href]`,      link.href      ?? '');
                    body.append(`links[${i}][label]`,     link.label     ?? '');
                    body.append(`links[${i}][ariaLabel]`, link.ariaLabel ?? '');
                    body.append(`links[${i}][hover]`,     link.hover     ?? '');
                    body.append(`links[${i}][target]`,    link.target    ?? '');
                    body.append(`links[${i}][can]`,       link.can       ?? '');
                }
            });

            try {
                const res = await fetch('{{ route('admin.site-settings.update') }}', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
                    body,
                });
                const json = await res.json();

                if (json.status === 'success') {
                    this.savedMessage = true;
                    setTimeout(() => { this.savedMessage = false; }, 3000);
                } else {
                    this.errorMessage = json.message ?? 'Save failed.';
                }
            } catch (e) {
                this.errorMessage = 'Network error.';
            } finally {
                this.saving = false;
            }
        },
    };
}
</script>
@endsection
