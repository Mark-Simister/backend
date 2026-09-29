@php
    // Primary source: the pipeline's pre-built review_page_json ($rp). Fall back
    // to the payload's flat columns where a section is missing.
    $seo      = $rp['seo'] ?? [];
    $identity = $rp['page_identity'] ?? [];
    $prod     = $rp['product'] ?? [];
    $hero     = $rp['hero'] ?? [];
    $qv       = $rp['quick_verdict'] ?? [];
    $facts    = $rp['review_facts'] ?? [];
    $take     = $rp['character_take'] ?? [];
    $bs       = $rp['beastiescore'] ?? [];
    $fit      = $rp['buyer_fit'] ?? [];
    $pc       = $rp['pros_cons'] ?? [];
    $fcr      = $rp['full_character_review'] ?? [];
    $owner    = $rp['owner_consensus'] ?? [];
    $traits   = $rp['trait_breakdown'] ?? [];
    $qa       = $rp['qa'] ?? [];
    $transcript = $rp['transcript'] ?? [];
    $video    = $rp['video'] ?? [];
    $commerce = $rp['commerce'] ?? [];
    $sources  = $rp['sources'] ?? [];
    $safety   = $rp['safety'] ?? [];
    $disc     = $rp['disclosure'] ?? [];

    $title = $seo['seo_title'] ?? $payload->seo_title ?? (($hero['title'] ?? $payload->h1) . ' Review');
    $desc  = $seo['seo_description'] ?? $payload->seo_description ?? ($qv['summary'] ?? '');
    $ogImg = $seo['og_image_url'] ?? $payload->og_image_url ?? ($prod['hero_image_url'] ?? $payload->hero_image_url);
    $h1    = $hero['title'] ?? $payload->h1;
    $host  = $hero['character_name'] ?? $payload->character_name;
    $channel = $hero['channel'] ?? $payload->channel;
    $productName = $prod['product_name'] ?? $prod['product_title'] ?? null;
    $brand = $prod['brand'] ?? null;
    $category = $prod['category'] ?? $prod['product_category_taxonomy'] ?? null;
    $heroImg = $prod['official_product_image_url'] ?? $prod['hero_image_url'] ?? $ogImg;

    // PUBLIC DISPLAY AUTHORITY: payload JSON only. Flat relational columns remain
    // available for operational/query/publication use but are NOT a display fallback.
    $finalScore    = $facts['beastiescore'] ?? ($bs['final_score'] ?? null);
    $publicScore   = $facts['public_score'] ?? ($bs['public_score'] ?? null);
    $ratingCount   = $facts['public_rating_count_retailer'] ?? ($facts['public_rating_count_score_model'] ?? null);
    $evidenceCount = $facts['analysed_evidence_count'] ?? null;
    $sourceCount   = $facts['source_count'] ?? null;
    $publicSignal  = $facts['public_signal'] ?? null;
    $analysisDepth = $facts['analysis_depth'] ?? null;
    $depthCount    = $facts['analysis_depth_count'] ?? null;
    $depthBasis    = $bs['analysis_depth_basis'] ?? [];

    $curSym = ['USD'=>'$','GBP'=>'£','EUR'=>'€','AUD'=>'A$','CAD'=>'C$'];
    $money = function ($p, $c = 'USD') use ($curSym) {
        return ($p === null || $p === '') ? null : ($curSym[$c] ?? '$') . number_format((float) $p, 2);
    };
    $asList = fn ($v) => is_array($v) ? $v : (strlen((string) $v) ? array_map('trim', explode(',', (string) $v)) : []);

    $retailers = $commerce['retailers'] ?? [];
    $primary = collect($retailers)->firstWhere('primary', true) ?? ($retailers[0] ?? null);
    $ytId = $video['youtube_id'] ?? '';
    $videoUrl = $video['video_url'] ?? '';
    $watchUrl = $ytId ? "https://www.youtube.com/watch?v={$ytId}" : $videoUrl;
@endphp
<!doctype html>
<html lang="{{ $payload->language_variant ?? 'en' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <meta name="description" content="{{ $desc }}">
    <link rel="canonical" href="{{ $canonical }}">
    @if($indexable ?? false)
    <meta name="robots" content="index, follow, max-image-preview:large">
    @else
    <meta name="robots" content="noindex, nofollow">
    @endif

    {{-- Open Graph --}}
    <meta property="og:type" content="article">
    <meta property="og:site_name" content="BeastieRated">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $desc }}">
    <meta property="og:url" content="{{ $canonical }}">
    @if($ogImg)<meta property="og:image" content="{{ $ogImg }}">@endif

    {{-- Twitter --}}
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $title }}">
    <meta name="twitter:description" content="{{ $desc }}">
    @if($ogImg)<meta name="twitter:image" content="{{ $ogImg }}">@endif

    {{-- Structured data — the pipeline's pre-built schema_json_ld, with its
         internal host rewritten to this request's per-region host (controller). --}}
    @if(!empty($schemaJson))
    <script type="application/ld+json">
{!! $schemaJson !!}
    </script>
    @endif

    <style>
        :root { --ink:#14181f; --muted:#5b6472; --line:#e4e7ec; --accent:#c77d00; --pos:#1a7f44; --neg:#c0392b; }
        * { box-sizing:border-box; }
        body { margin:0; font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif; color:var(--ink); background:#fff; line-height:1.6; }
        .wrap { max-width:840px; margin:0 auto; padding:24px 20px 80px; }
        a { color:var(--accent); }
        nav.crumb { font-size:13px; color:var(--muted); margin-bottom:14px; }
        nav.crumb a { color:var(--muted); }
        h1 { font-size:30px; line-height:1.2; margin:.2em 0 .1em; }
        .subtitle { font-size:18px; color:var(--muted); margin:0 0 12px; }
        h2 { font-size:21px; margin:34px 0 10px; padding-top:14px; border-top:1px solid var(--line); }
        h3 { font-size:16px; margin:18px 0 6px; }
        .byline { font-size:14px; color:var(--muted); margin-bottom:10px; }
        .score { font-weight:800; color:var(--accent); }
        dl.facts { background:#faf7f0; border:1px solid var(--line); border-radius:12px; padding:16px 18px; margin:8px 0; }
        dl.facts div { display:flex; gap:10px; padding:5px 0; border-bottom:1px solid var(--line); font-size:14.5px; }
        dl.facts div:last-child { border-bottom:0; }
        dl.facts dt { flex:0 0 150px; color:var(--muted); margin:0; }
        dl.facts dd { margin:0; font-weight:600; }
        ul.tight { margin:6px 0; padding-left:20px; }
        ul.tight li { margin:3px 0; }
        .pos li::marker { content:"✓  "; color:var(--pos); }
        .neg li::marker { content:"✕  "; color:var(--neg); }
        table.retail { width:100%; border-collapse:collapse; font-size:14.5px; margin-top:8px; }
        table.retail td { padding:9px 6px; border-bottom:1px solid var(--line); }
        table.retail td:last-child { text-align:right; }
        .tag { font-size:11px; text-transform:uppercase; letter-spacing:.04em; color:var(--muted); border:1px solid var(--line); border-radius:5px; padding:1px 6px; }
        .cta { display:inline-block; background:var(--accent); color:#fff; font-weight:700; padding:10px 18px; border-radius:8px; text-decoration:none; margin:8px 0; }
        .note { font-size:13px; color:var(--muted); }
        .callout { background:#f7f9fc; border:1px solid var(--line); border-radius:10px; padding:12px 14px; margin:10px 0; }
        .chips span { display:inline-block; background:#faf7f0; border:1px solid var(--line); border-radius:999px; padding:3px 11px; font-size:13px; margin:0 6px 6px 0; }
        .videoposter { position:relative; display:block; max-width:100%; border-radius:12px; overflow:hidden; margin:10px 0; }
        .videoposter img { width:100%; display:block; }
        .videoposter .play { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; font-size:54px; color:#fff; text-shadow:0 2px 12px rgba(0,0,0,.6); }
        footer { border-top:1px solid var(--line); margin-top:40px; padding-top:16px; font-size:13px; color:var(--muted); }
    </style>
</head>
<body>
<div class="wrap">

    <nav class="crumb">
        <a href="/">Home</a>
        @if($channel) › <span>{{ $channel }}</span>@endif
        @if($category) › <span>{{ $category }}</span>@endif
        › <span>Review</span>
    </nav>

    <h1>{{ $h1 }}</h1>
    @if($hero['subtitle'] ?? null)<p class="subtitle">{{ $hero['subtitle'] }}</p>@endif

    <p class="byline">
        @if($host)Reviewed by <strong>{{ $host }}</strong>@if($channel) · {{ $channel }}@endif — @endif
        @if($payload->created_at)Published {{ \Illuminate\Support\Carbon::parse($payload->created_at)->format('M j, Y') }}@endif
        @if($finalScore !== null) · <span class="score">BeastieScore {{ $finalScore }}/5</span>@endif
    </p>

    {{-- ── Review Facts (LLM-extractable) ── --}}
    <dl class="facts" aria-label="Review facts">
        @if($productName)<div><dt>Product</dt><dd>{{ $productName }}</dd></div>@endif
        @if($brand)<div><dt>Brand</dt><dd>{{ $brand }}</dd></div>@endif
        @if($category)<div><dt>Category</dt><dd>{{ $category }}</dd></div>@endif
        @if($host)<div><dt>Reviewer</dt><dd>{{ $host }}@if($channel) · {{ $channel }}@endif</dd></div>@endif
        {{-- confidence_tier is INTERNAL ONLY and must never render (approved contract). --}}
        @if($finalScore !== null)<div><dt>BeastieScore</dt><dd>{{ $finalScore }} / 5</dd></div>@endif
        @if($publicScore !== null)<div><dt>Public score</dt><dd>{{ $publicScore }} / 5{{ $ratingCount ? ' · '.number_format($ratingCount).' ratings' : '' }}</dd></div>@endif
        @if($publicSignal)<div><dt>Public signal</dt><dd>{{ $publicSignal }}</dd></div>@endif
        @if($analysisDepth)<div><dt>Analysis depth</dt><dd>{{ $analysisDepth }}@if($depthCount !== null) · {{ $depthCount }} independent sources examined @endif</dd></div>@endif
        @if(!empty($fit['best_for']))<div><dt>Best for</dt><dd>{{ implode(', ', $asList($fit['best_for'])) }}</dd></div>@endif
        @if(!empty($fit['not_best_for']))<div><dt>Watch out for</dt><dd>{{ implode(', ', $asList($fit['not_best_for'])) }}</dd></div>@endif
        <div><dt>Disclosure</dt><dd>AI-generated review from aggregated public data; not a hands-on product test.</dd></div>
    </dl>

    {{-- ── Video (externally hosted — click-to-load poster, no heavy iframe) ── --}}
    @if($watchUrl && $heroImg)
        <a class="videoposter" href="{{ $watchUrl }}" target="_blank" rel="noopener">
            <img src="{{ $heroImg }}" alt="{{ $h1 }} — watch the video review" loading="lazy">
            <span class="play">▶</span>
        </a>
        <p class="note">Video hosted externally — opens on the source platform.</p>
    @endif

    {{-- ── Quick Verdict ── --}}
    @if(array_filter($qv))
        <h2>Quick Verdict</h2>
        @if(!empty($qv['summary']))<p><strong>{{ $qv['summary'] }}</strong></p>@endif
        @if(!empty($qv['good_for']))<p><strong>Good for:</strong> {{ $qv['good_for'] }}</p>@endif
        @if(!empty($qv['watch_out_for']))<p><strong>Watch out for:</strong> {{ $qv['watch_out_for'] }}</p>@endif
        @if(!empty($qv['beastie_take']))<p>{{ $qv['beastie_take'] }}</p>@endif
    @endif

    {{-- ── D2 current active Best For memberships ── --}}
    @include('reviews.partials.best-for')

    {{-- ── Where to buy: national payload retailers, then the local mount ── --}}
    @include('reviews.partials.retailers-national')
    @include('reviews.partials.local-retailers')

    {{-- ── Public evidence fields (approved public semantics) ── --}}
    @if($publicSignal || $analysisDepth || !empty($depthBasis))
        <h2>How Much Evidence Sits Behind This</h2>
        <ul class="tight">
            @if($publicSignal)<li>Public signal: <strong>{{ $publicSignal }}</strong></li>@endif
            @if($analysisDepth)<li>Analysis depth: <strong>{{ $analysisDepth }}</strong>@if($depthCount !== null) ({{ $depthCount }} independent sources examined)@endif</li>@endif
            @if(($depthBasis['professional_reviews'] ?? null) !== null)<li>Professional reviews: {{ $depthBasis['professional_reviews'] }}</li>@endif
            @if(($depthBasis['retailer_review_sources'] ?? null) !== null)<li>Retailer review sources: {{ $depthBasis['retailer_review_sources'] }}</li>@endif
            @if(($depthBasis['video_reviews'] ?? null) !== null)<li>Video reviews: {{ $depthBasis['video_reviews'] }}</li>@endif
        </ul>
        @if(!empty($bs['score_basis_summary']))<p class="note">{{ $bs['score_basis_summary'] }}</p>@endif
        <p class="note">{{ $bs['score_disclosure_line'] ?? 'BeastieScore is an aggregation-based verdict from public review data and third-party signals, not a hands-on product test.' }}</p>
    @endif

    {{-- ── D3 curated public adjustment disclosure ── --}}
    @include('reviews.partials.adjustments')

    {{-- ── Character's Take ── --}}
    @if(array_filter($take))
        <h2>{{ $host ? $host."'s Take" : "Character's Take" }}</h2>
        @if(!empty($take['quote']))<blockquote>“{{ $take['quote'] }}”</blockquote>@endif
        @if(!empty($take['summary']))<p>{{ $take['summary'] }}</p>@endif
        @if(!empty($take['highlights']))
            <ul class="tight">@foreach($take['highlights'] as $hl)<li>{{ $hl }}</li>@endforeach</ul>
        @endif
    @endif

    {{-- ── Pros / Cons ── --}}
    @if(!empty($pc['pros']))
        <h2>What Owners Loved</h2>
        <ul class="tight pos">@foreach($pc['pros'] as $p)<li>{{ $p }}</li>@endforeach</ul>
    @endif
    @if(!empty($pc['cons']))
        <h2>What Owners Complained About</h2>
        <ul class="tight neg">@foreach($pc['cons'] as $c)<li>{{ $c }}</li>@endforeach</ul>
    @endif

    {{-- ── Best For / Not For ── --}}
    @if(!empty($fit['best_for']) || !empty($fit['not_best_for']))
        <h2>Best For / Not Best For</h2>
        @if(!empty($fit['best_for']))<p><strong>Best for:</strong> {{ implode(' · ', $asList($fit['best_for'])) }}</p>@endif
        @if(!empty($fit['not_best_for']))<p><strong>Not best for:</strong> {{ implode(' · ', $asList($fit['not_best_for'])) }}</p>@endif
    @endif

    {{-- ── Full Character Review ── --}}
    @if(array_filter($fcr))
        <h2>Full Review Report</h2>
        @if(!empty($fcr['overview']))<p>{{ $fcr['overview'] }}</p>@endif
        @if(!empty($fcr['character_verdict']))<h3>{{ $host ?: 'Character' }}’s Verdict</h3><p>{{ $fcr['character_verdict'] }}</p>@endif
        @foreach(($fcr['sections'] ?? []) as $sec)
            @if(!empty($sec['heading']))<h3>{{ $sec['heading'] }}</h3>@endif
            @if(!empty($sec['body']))<p>{{ $sec['body'] }}</p>@endif
        @endforeach
    @endif

    {{-- ── Trait Breakdown ── --}}
    @if(!empty(array_filter((array) $traits)))
        <h2>Trait Breakdown</h2>
        @foreach($traits as $name => $body)
            @if($body)<h3>{{ ucwords(str_replace('_', ' ', $name)) }}</h3><p>{{ $body }}</p>@endif
        @endforeach
    @endif

    {{-- ── Owner Consensus ── --}}
    @if(!empty($owner['summary']))
        <h2>Owner Consensus</h2>
        <p>{{ $owner['summary'] }}</p>
    @endif

    {{-- ── Evidence & Sources ── --}}
    @php $typed = $sources['typed_source_links'] ?? []; $articles = $sources['review_articles'] ?? []; @endphp
    @if(!empty($typed) || !empty($articles) || $evidenceCount)
        <h2>Evidence &amp; Sources</h2>
        @if($evidenceCount)
            <p>{{ number_format($evidenceCount) }} analysed signals across {{ $sourceCount }} source families{{ $ratingCount ? ', from '.number_format($ratingCount).' public ratings' : '' }}.</p>
        @endif
        @if(!empty($typed))
            <h3>Review sources</h3>
            <div class="chips">
                @foreach($typed as $s)
                    @if(!empty($s['url']))<span><a href="{{ $s['url'] }}" rel="nofollow noopener" target="_blank">{{ $s['domain'] ?? 'source' }}</a>@if($s['type'] ?? null) · {{ $s['type'] }}@endif</span>@endif
                @endforeach
            </div>
        @endif
    @endif

    {{-- ── Transcript (only when supplied) ── --}}
    @if(($transcript['available'] ?? false) && !empty($transcript['text']))
        <h2>Video Transcript</h2>
        <p style="white-space:pre-line">{{ \Illuminate\Support\Str::limit($transcript['text'], 1200) }}</p>
    @endif

    {{-- ── Q&A ── --}}
    @if(!empty($qa))
        <h2>Questions &amp; Answers</h2>
        @foreach($qa as $item)
            <h3>{{ $item['q'] ?? $item['question'] ?? '' }}</h3>
            <p>{{ $item['a'] ?? $item['answer'] ?? '' }}</p>
        @endforeach
    @endif

    {{-- ── Safety & Disclosure ── --}}
    <h2>Safety &amp; Disclosure</h2>
    <div class="callout">
        <strong>🛡️ {{ $safety['public_safety_label'] ?? 'Safety Check' }}:</strong>
        {{ $safety['public_safety_note'] ?? $safety['safety_feed_summary'] ?? 'No matched official safety alerts found in checked sources.' }}
        <div class="note" style="margin-top:6px">
            @if(($safety['safety_feed_freshness_status'] ?? null))Freshness: {{ $safety['safety_feed_freshness_status'] }}. @endif
            This is not a safety certification.
        </div>
    </div>
    <p class="note">{{ $disc['affiliate_disclosure_text'] ?? 'BeastieRated is an AI-powered review service. Our characters and verdicts are generated by AI from aggregated public review data, retailer signals, and cited third-party sources. We do not conduct hands-on product testing or safety certification. As an Amazon Associate, we earn from qualifying purchases.' }}</p>

    <footer>
        <p>BeastieRated — Real Reviews. Real Animals. Real Opinions. AI-generated from aggregated public data; not hands-on testing.</p>
    </footer>

</div>
</body>
</html>
