<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Vimeo Videos</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; margin: 24px; color: #222; }
    h1 { margin: 0 0 12px; }
    .toolbar { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; }
    .btn { display: inline-block; padding: 8px 12px; border: 1px solid #ddd; border-radius: 8px; text-decoration: none; color: #111; background: #fafafa; }
    .btn[disabled] { opacity: 0.5; pointer-events: none; }
    .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 16px; }
    .card { border: 1px solid #eee; border-radius: 12px; overflow: hidden; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,.05); }
    .thumb { width: 100%; height: 140px; object-fit: cover; background: #f4f4f4; display: block; }
    .content { padding: 12px; }
    .title { font-weight: 600; font-size: 15px; margin: 0 0 8px; line-height: 1.2; }
    .meta { font-size: 12px; color: #666; }
    .empty { border: 1px dashed #ccc; padding: 24px; border-radius: 12px; text-align: center; color: #666; }
    .error { color: #b00020; background: #ffe9ec; border: 1px solid #f5c2c7; padding: 12px; border-radius: 8px; margin-bottom: 16px; }
  </style>
</head>
<body>
  <h1>Vimeo Videos</h1>

  @if ($error)
    <div class="error">
      <strong>API Error:</strong> {{ $error }}<br>
      Check your <code>VIMEO_ACCESS_TOKEN</code> and scopes in <code>.env</code>.
    </div>
  @endif

  <div class="toolbar">
    <span>Page: <strong>{{ $page }}</strong></span>
    <a class="btn" href="{{ route('vimeo.videos', ['page' => $prevPage ?? 1]) }}" @if(!$prevPage) disabled @endif>&larr; Previous</a>
    <a class="btn" href="{{ route('vimeo.videos', ['page' => $nextPage ?? $page]) }}" @if(!$nextPage) disabled @endif>Next &rarr;</a>
  </div>

  @if (empty($videos))
    <div class="empty">
      No videos found on this page.
      @if (!$error)
        Try clicking “Previous” or “Next”, or upload a video to your Vimeo library.
      @endif
    </div>
  @else
    <div class="grid">
      @foreach ($videos as $v)
        @php
          // pick the first available thumbnail
          $thumb = null;
          if (!empty($v['pictures']['sizes'])) {
            $thumb = $v['pictures']['sizes'][0]['link'] ?? null;
            // Prefer a medium size if available
            foreach ($v['pictures']['sizes'] as $s) {
              if (!empty($s['link']) && ($s['width'] ?? 0) >= 320) { $thumb = $s['link']; break; }
            }
          }
          $created = \Illuminate\Support\Carbon::parse($v['created_time'] ?? null)->format('Y-m-d H:i');
          $dur = $v['duration'] ?? 0;
          $mins = floor($dur/60);
          $secs = str_pad($dur%60, 2, '0', STR_PAD_LEFT);
        @endphp
        <div class="card">
          @if ($thumb)
            <img src="{{ $thumb }}" alt="Thumbnail" class="thumb">
          @else
            <div class="thumb"></div>
          @endif
          <div class="content">
            <div class="title">
              <a href="{{ $v['link'] ?? '#' }}" target="_blank" rel="noopener noreferrer">
                {{ $v['name'] ?? '(Untitled Video)' }}
              </a>
            </div>
            <div class="meta">
              {{ $created }} · {{ $mins }}:{{ $secs }}
            </div>
          </div>
        </div>
      @endforeach
    </div>
  @endif

  <div class="toolbar" style="margin-top:16px;">
    <a class="btn" href="{{ route('vimeo.videos', ['page' => $prevPage ?? 1]) }}" @if(!$prevPage) disabled @endif>&larr; Previous</a>
    <a class="btn" href="{{ route('vimeo.videos', ['page' => $nextPage ?? $page]) }}" @if(!$nextPage) disabled @endif>Next &rarr;</a>
  </div>
</body>
</html>
