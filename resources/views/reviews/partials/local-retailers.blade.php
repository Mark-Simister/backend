{{--
    Local / sponsored retailer region - SSR MOUNT CONTRACT ONLY.

    This sprint supplies the deterministic mount point and its data contract so a
    later hydration island can attach. NO carousel, API or sponsored-placement
    behaviour is implemented here, and none may be claimed complete.

    The container and heading are present even when empty, per the approved design.
--}}
<h2>Local Stockists</h2>

<div
    data-island="local-retailers"
    data-island-state="ssr-placeholder"
    data-review-slug="{{ $payload->review_slug }}"
    data-region="{{ $origin }}"
>
    <p class="note">Local stockist availability loads for your area where we have it.</p>
</div>
