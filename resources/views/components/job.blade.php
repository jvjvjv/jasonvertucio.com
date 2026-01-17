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
    <h3 class="font-heading text-xl mb-2 font-bold">{{ $job['date'] }}</h3>
    <p class="italic">{{ $job['location'] }}</p>
    <p class="mb-0">{{ $job['description'] }}</p>
    <ul>
        {!! show_nested_highlights($job['highlights']) !!}
    </ul>
</div>
