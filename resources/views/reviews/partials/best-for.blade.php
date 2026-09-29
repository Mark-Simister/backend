{{--
    D2 — CURRENT ACTIVE Best For memberships for this review.

    Resolved server-side by App\Support\BestFor\CurrentMembershipResolver from
    the review's published_review_id and the request's host region. Only current,
    published, live, active editions reach here, and for any one category/year the
    resolver has already chosen exact-region OR GLOBAL — never both.

    NO NUMERIC RANK. The approved design shows the superlative and the
    category/year a review is currently recommended for; it never presents a
    position such as "#2". `position` is not exposed by the resolver, so there is
    nothing here to render even accidentally.

    Historical memberships are NOT shown: an edition that was replaced was
    replaced, and its record stays on its own immutable edition page.

    Zero memberships is a legitimate state and renders nothing at all.
--}}
@php $memberships = collect($bestFor ?? []); @endphp

@if($memberships->isNotEmpty())
    <h2>Currently Recommended In</h2>

    <ul class="tight" data-section="best-for-current">
        @foreach($memberships as $membership)
            <li>
                <strong>{{ $membership['superlative'] }}</strong>
                — {{ $membership['category_name'] }} {{ $membership['year'] }}
            </li>
        @endforeach
    </ul>

    <p class="note">Current selections only. A review is listed here while it is part of the current edition for that category and year.</p>
@endif
