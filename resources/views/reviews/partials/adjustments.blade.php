{{--
    D3 - curated public adjustment disclosure.

    Renders ONLY beastiescore.public_score_adjustments, the curated public contract.
    beastiescore.raw is internal working data and is never read here, and no
    adjustment is ever reconstructed from internal fields. Absence of the curated
    block means the producer supplied no disclosure, so nothing is asserted.
--}}
@php $adj = $bs['public_score_adjustments'] ?? null; @endphp

@if(is_array($adj))
    @php
        $items   = array_values(array_filter((array) ($adj['adjustments'] ?? [])));
        $none    = (bool) ($adj['no_adjustment_occurred'] ?? false);
        $cancels = (bool) ($adj['adjustments_cancel_to_zero'] ?? false);
        $net     = $adj['net_adjustment'] ?? null;
        $signed  = fn ($v) => ($v > 0 ? '+' : '') . rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
    @endphp

    <h2>How This Score Was Adjusted</h2>

    @if(($adj['base_public_rating'] ?? null) !== null)
        <p>Starting point: <strong>public rating {{ $adj['base_public_rating'] }} / 5</strong>.</p>
    @endif

    @if($none && empty($items))
        <p data-adjustment-state="none"><strong>No adjustment was applied.</strong> This score is the public rating as published.</p>
    @else
        <ul class="tight" data-adjustment-state="{{ $cancels ? 'cancelling' : 'applied' }}">
            @foreach($items as $a)
                <li>
                    <strong>{{ $signed($a['amount'] ?? 0) }}</strong>
                    @if(!empty($a['kind'])) · {{ ucfirst(str_replace('_', ' ', $a['kind'])) }}@endif
                    @if(!empty($a['reason'])) — {{ $a['reason'] }}@endif
                    @if(!empty($a['evidence_references']))
                        <div class="note">Evidence:
                            @foreach($a['evidence_references'] as $ref)
                                <a href="{{ $ref }}" rel="nofollow noopener" target="_blank">{{ $ref }}</a>@if(!$loop->last), @endif
                            @endforeach
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>

        @if($cancels)
            <p class="note" data-adjustment-cancels="1">These adjustments cancel out: the net effect on the score is zero.</p>
        @elseif($net !== null)
            <p class="note">Net adjustment applied: <strong>{{ $signed($net) }}</strong>.</p>
        @endif
    @endif

    @if(($adj['final_beastie_score'] ?? null) !== null)
        <p><strong>Final BeastieScore: {{ $adj['final_beastie_score'] }} / 5</strong></p>
    @endif

    @if(!empty($adj['basis_summary']))<p class="note">{{ $adj['basis_summary'] }}</p>@endif
@endif
