@props(['job'])

<?php
if (!function_exists('show_nested_highlights')) {
    function show_nested_highlights($description, $tag = "li") {
        if (is_array($description)) {
            foreach ($description as $desc) {
                if (is_array($desc)) {
                    show_nested_highlights($desc);
                } else {
                    echo "<{$tag}>$desc</{$tag}>";
                }
            }
        } else {
            echo "<{$tag}>$description</{$tag}>";
        }
    }
}
?>

<div>
    <h3 class="font-heading text-xl mb-2 font-bold">{{ $job['company'] }}</h3>
    <p class="italic mb-2">{{ $job['date'] }} &middot; {{  $job['location'] }}</p>
    @if($job['description'])
        <p class="mb-2">{{ $job['description'] }}</p>
    @endif
    <ul class="mt-4 pl-4 list-disc">
        {!! show_nested_highlights($job['highlights']) !!}
    </ul>
</div>
