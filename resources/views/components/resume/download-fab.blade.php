<form method="POST" action="{{ route('resume.docx.initiate') }}">
    @csrf
    <button
        type="submit"
        class="fab-download"
        title="Download Resume as DOCX"
        aria-label="Download Resume as DOCX"
    >
        <i class="fa-solid fa-download text-xl"></i>
    </button>
</form>
