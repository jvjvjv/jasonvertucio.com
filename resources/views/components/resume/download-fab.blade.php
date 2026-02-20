@props(['docxExists' => false, 'pdfExists' => false])

@if($docxExists || $pdfExists)
<div x-data="{ open: false }" class="fixed bottom-6 right-6 flex flex-col-reverse items-end gap-2 print:hidden">
    {{-- Options grow upward above the FAB --}}
    <div x-show="open" x-transition class="flex flex-col items-end gap-2">
        @if($docxExists)
            <a href="{{ route('resume.download.docx') }}"
                class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-300 text-gray-800 text-sm font-medium rounded-full hover:bg-gray-50 transition-colors"
                title="Download DOCX">
                <i class="fa-classic fa-file-word text-blue-600"></i>
                Word Document
            </a>
        @endif

        @if($pdfExists)
            <a href="{{ route('resume.download.pdf') }}"
                class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-300 text-gray-800 text-sm font-medium rounded-full hover:bg-gray-50 transition-colors"
                title="Download PDF">
                <i class="fa-classic fa-file-pdf text-red-600"></i>
                PDF
            </a>
        @endif
    </div>

    {{-- FAB toggle button --}}
    <button
        @click="open = !open"
        class="fab-download"
        :title="open ? 'Close' : 'Download'"
        :aria-label="open ? 'Close download menu' : 'Download resume'">
        <i class="fa-solid text-xl transition-transform duration-200"
            :class="open ? 'fa-xmark' : 'fa-download'"></i>
    </button>
</div>
@endif
