<x-mail::message>
# A follow-up is ready to review

A follow-up for **{{ $position }}** at **{{ $company }}** has been drafted. Nothing has been sent: read it, edit it if you like, then approve or discard it.

<x-mail::button :url="$url">
Review the follow-up
</x-mail::button>

</x-mail::message>
