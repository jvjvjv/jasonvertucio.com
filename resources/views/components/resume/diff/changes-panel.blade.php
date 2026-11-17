@props(['diff', 'baseline', 'revisionId'])

@php
    $count = $diff->count();
    $kindLabels = [
        'added' => 'Added',
        'removed' => 'Removed',
        'modified' => 'Changed',
        'formatting' => 'Formatting',
        'moved' => 'Moved',
        'order_changed' => 'Reordered',
    ];
@endphp

<section class="rd-panel" aria-labelledby="rd-panel-title">
    <div class="rd-panel__head">
        <div class="rd-panel__summary">
            <h2 id="rd-panel-title" class="rd-panel__title">
                @if($count === 0)
                    No changes
                @else
                    {{ $count }} {{ \Illuminate\Support\Str::plural('change', $count) }}
                @endif
            </h2>
            <p class="rd-panel__basis">
                @if($count === 0)
                    This revision matches {{ $baseline->label }}.
                @else
                    Compared with {{ $baseline->label }}.
                @endif
            </p>
            @if($baseline->fellBack)
            <p class="rd-panel__notice" role="status">
                That comparison isn't available for this revision, so it is compared with the version it branched from.
            </p>
            @endif
        </div>

        @if(count($baseline->available) > 1)
        <form method="GET" action="{{ route('resume.index') }}" class="rd-compare">
            <input type="hidden" name="revision" value="{{ $revisionId }}">
            <label for="rd-compare" class="rd-compare__label">Compare with</label>
            <select id="rd-compare" name="compare" class="rd-compare__select" onchange="this.form.submit()">
                @foreach($baseline->available as $mode => $optionLabel)
                <option value="{{ $mode }}" @selected($mode === $baseline->mode)>{{ $optionLabel }}</option>
                @endforeach
            </select>
            <noscript><button type="submit" class="rd-compare__submit">Compare</button></noscript>
        </form>
        @endif
    </div>

    @foreach($diff->changesBySection() as $sectionTitle => $changes)
    <div class="rd-panel__section">
        <h3 class="rd-panel__section-title">{{ $sectionTitle }}</h3>
        <ul class="rd-rows">
            @foreach($changes as $change)
            <li class="rd-row">
                <span class="rd-kind rd-kind--{{ $change['kind'] }}">{{ $kindLabels[$change['kind']] ?? $change['kind'] }}</span>
                <div class="rd-row__body">
                    <p class="rd-row__where">
                        @if($change['item'] !== $sectionTitle)
                        <span class="rd-row__item">{{ $change['item'] }}</span>
                        @endif
                        @if($change['field'] !== '')
                        <span class="rd-row__field">{{ $change['field'] }}</span>
                        @endif
                        @unless($change['shown'])
                        <span class="rd-row__hidden"><i class="fa-solid fa-eye-slash" aria-hidden="true"></i> Not shown on resume</span>
                        @endunless
                    </p>
                    <p class="rd-row__what">
                        @if($change['tokens'] !== null)
                            <x-resume.diff.tokens :tokens="$change['tokens']" elide />
                        @elseif($change['kind'] === 'added')
                            <ins class="rd-ins">{{ $change['after'] }}</ins>
                        @elseif($change['kind'] === 'removed')
                            <del class="rd-del">{{ $change['before'] }}</del>
                        @elseif($change['kind'] === 'moved')
                            Now in a different position.
                        @elseif($change['kind'] === 'order_changed')
                            Same skills in a new order.
                        @elseif($change['kind'] === 'formatting')
                            Bold or italic changed: {{ \App\Services\Resume\Diff\WordDiff::plain($change['after'] ?? '') }}
                        @elseif($change['before'] === null)
                            <ins class="rd-ins">{{ $change['after'] }}</ins>
                            <span class="rd-row__note">was empty</span>
                        @elseif($change['after'] === null)
                            <del class="rd-del">{{ $change['before'] }}</del>
                            <span class="rd-row__note">now empty</span>
                        @else
                            <del class="rd-del">{{ $change['before'] }}</del>
                            <span class="rd-row__arrow" aria-hidden="true">&rarr;</span>
                            <span class="sr-only">changed to</span>
                            <ins class="rd-ins">{{ $change['after'] }}</ins>
                        @endif
                    </p>
                </div>
            </li>
            @endforeach
        </ul>
    </div>
    @endforeach
</section>
