{{--
    National retailers, rendered from the approved payload commerce collection.

    Retailer PRICES ARE SUPPRESSED while RP-PRICE-01 is unresolved - no truthful
    price-observation timestamp exists upstream, so no price is displayed.

    A `sponsored` flag present on a national entry is NOT converted into a
    customer-facing advertisement treatment here. Local and sponsored placement is
    a separate concern with its own region below this block.
--}}
@php $nat = array_values(array_filter((array) ($commerce['retailers'] ?? []))); @endphp

@if(!empty($nat))
    <h2>Product Buying Options</h2>

    @php $primaryNat = collect($nat)->firstWhere('primary', true) ?? ($nat[0] ?? null); @endphp
    @if($primaryNat && !empty($primaryNat['url']))
        <a class="cta" href="{{ $primaryNat['url'] }}" rel="nofollow sponsored noopener" target="_blank">
            {{ $qv['cta_text'] ?? 'Check Current Price' }} on {{ $primaryNat['name'] ?? 'Amazon' }}
        </a>
    @endif

    <table class="retail" data-retailers="national">
        @foreach($nat as $rt)
            <tr>
                <td><strong>{{ $rt['name'] ?? 'Retailer' }}</strong></td>
                <td>@if(!empty($rt['url']))<a href="{{ $rt['url'] }}" rel="nofollow sponsored noopener" target="_blank">View</a>@endif</td>
            </tr>
        @endforeach
    </table>

    <p class="note">{{ $commerce['commerce_disclosure'] ?? 'Affiliate links may earn BeastieRated a commission at no extra cost to you.' }}</p>
@endif
