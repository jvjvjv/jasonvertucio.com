<?php
if (!function_exists('show_nested_highlights')) {
    /**
     * Recursively show nested descriptions.
     *
     * @param mixed $description The description to show.
     * @param string $tag The HTML tag to use for the description.
     */
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
    <h4>{{ $job['date'] }}</h4>
    <p><em>{{ $job['location'] }}</em></p>
    <p class="mb-0">{{ $job['description'] }}</p>
    <ul class="">
        {{ show_nested_highlights($job['highlights']); }}
    </ul>
</div>
