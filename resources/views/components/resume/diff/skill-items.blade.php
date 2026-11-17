{{-- A skill category's items joined like SafeInlineHtml::joinList(), each marked when it was added or removed. --}}
@php
    $items = array_map(function (array $item) use ($category): string {
        $plain = e(\App\Services\Resume\Diff\WordDiff::plain($item['text']), false);

        return match (true) {
            $item['status'] === 'added' && ! $category->isAdded() => '<ins class="rd-ins">'.$plain.'</ins>',
            $item['status'] === 'removed' => '<del class="rd-del">'.$plain.'</del>',
            default => \App\Support\SafeInlineHtml::clean($item['text']),
        };
    }, $category->items);
@endphp
{!! implode(' &middot; ', $items) !!}
