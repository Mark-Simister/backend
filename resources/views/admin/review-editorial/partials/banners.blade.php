{{-- Persistent workspace banners (design record section 11, "Validation and warnings"). --}}
@if (! $authority->isValid())
    <div class="alert alert-danger">The video-authority configuration is invalid. Nothing can be QA-passed or released, and every review fails closed until it is corrected.</div>
@endif
@if (! $isPipeline)
    <div class="alert alert-dark">Legacy admin-source row: not publicly eligible. Only viewing and dispositions are available.</div>
@endif
@if ($d3)
    <div class="alert alert-danger">D3: this review is under the divergence governance hold. It cannot be released.</div>
@endif
@if ($d1)
    <div class="alert alert-danger">D1: this review has a contradictory public safety state. It cannot be released.</div>
@endif
@if ($record->isHeld())
    <div class="alert alert-warning">Editorial hold since {{ $record->hold_at }}: {{ $record->hold_reason }}</div>
@endif
@if ($stale)
    <div class="alert alert-warning">
        Changed since the {{ $record->editorial_state === 'released' ? 'release' : 'QA pass' }}: the bound fingerprint no longer matches the current content or media
        (last import {{ $payload->updated_at ?? 'unknown' }}). A fresh QA pass and Release are required.
    </div>
@endif
@if ($disposition?->isWithdrawn())
    <div class="alert alert-danger">Withdrawn {{ $disposition->withdrawn_at }}{{ $disposition->isPendingDisposition() ? ' - treated as material until the class is confirmed' : '' }}.</div>
@endif
@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    </div>
@endif
