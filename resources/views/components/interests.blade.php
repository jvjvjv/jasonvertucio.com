<section class="site-section" id="interests">
    <div class="w-full">
        <h2 class="3xl font-heading text-3xl uppercase mb-5 font-bold">Interests</h2>
        @foreach($interests as $interest)
            <p>{!! $interest !!}</p>
        @endforeach
        @if($btc)
            <p>Oh. And as of {{ $btc->time->updateduk}}, the price of BTC is:</p>
            <ul>
                @foreach($btc->bpi as $item)
                    <li>
                        <span class="font-mono"><strong>{{ $item->code }}:</strong></span>
                        <span class="font-mono">{!! $item->symbol !!}{{ $item->rate_float }}</span>
                    </li>
                @endforeach
            </ul>
            <p><small>Powered by <a href="https://www.coindesk.com/price/bitcoin" target="_blank">Coindesk</a>.
                    {{ $btc->disclaimer }}</small></p>
        @endif
    </div>
</section>
