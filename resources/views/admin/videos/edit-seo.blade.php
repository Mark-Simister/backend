@extends('layouts.admin.master')

@section('title', 'Edit Video – SEO Fields')

@push('styles')
<style>
  /*  */
</style>
@endpush

@section('content')
<div class="seo-hero mb-4">
  <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
    <div class="d-flex align-items-start gap-3">
      <span class="badge bg-primary-subtle text-primary border border-primary px-3 py-2 rounded-pill"><i class="bi bi-rocket-takeoff me-1"></i> SEO</span>
      <div>
        <h2 class="mb-1 h4 h3-lg">Optimize: <span class="hglow">{{ $video->title }}</span></h2>
        <div class="text-muted small">
          Channel: {{ $video->channel->name ?? '—' }} • Category: {{ $video->category->name ?? '—' }}
        </div>
      </div>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <a class="btn btn-outline-secondary" href="{{ route('admin.videos.edit', $video) }}"><i class="bi bi-sliders"></i> Full Edit</a>
      <a class="btn btn-success" href="{{ route('admin.videos.edit.product', $video) }}"><i class="bi bi-bag"></i> Product Fields</a>
    </div>
  </div>
</div>


@if(session('success'))
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle me-1"></i> {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
@endif

@if ($errors->any())
  <div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="bi bi-exclamation-triangle me-1"></i> Please fix the following:
    <ul class="mb-0 mt-1">
      @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
@endif

<form method="POST" action="{{ route('admin.videos.update.seo', $video) }}">
@csrf
@method('PUT')

<div class="row g-4">
  
  {{-- LEFT: SEO form --}}
  <div class="col-12 col-lg-8">
    <div class="card card-soft">
      <div class="card-body p-4">
        <div class="">
                <label for="regions">Select Regions</label>
                <select name="regions" id="regions" class="form-control">
                    @foreach ($regions as $region)
                        <option value="{{ $region->id }}">{{ $region->name }}</option>
                    @endforeach
                </select>
            </div>
        <div class="card-section-title">Meta</div>

        {{-- Title + Counter --}}
        <div class="mb-3">
          <div class="d-flex justify-content-between align-items-center mb-1">
            <label for="seo_title" class="form-label mb-0 fw-semibold">SEO Title</label>
            <div class="counter-wrap">
              <small class="text-muted">Recommended 50–60</small>
              <span class="badge bg-light text-dark border counter"><span id="titleCount">0</span></span>
            </div>
          </div>
          <div class="form-floating">
            <input type="text" name="seo_title" id="seo_title" class="form-control form-control-lg"
                   placeholder="Compelling, keyword-rich title…"
                   value="{{ old('seo_title', $video->seo_title) }}">
            
          </div>
          <div class="progress len mt-2">
            <div id="titleBar" class="progress-bar" role="progressbar" style="width:0%"></div>
          </div>
        </div>

        {{-- Description + Counter --}}
        <div class="mb-3">
          <div class="d-flex justify-content-between align-items-center mb-1">
            <label for="seo_description" class="form-label mb-0 fw-semibold">SEO Description</label>
            <div class="counter-wrap">
              <small class="text-muted">Recommended 120–160</small>
              <span class="badge bg-light text-dark border counter"><span id="descCount">0</span></span>
            </div>
          </div>
          <div class="form-floating">
            <textarea name="seo_description" id="seo_description" class="form-control" style="height:120px"
                      placeholder="Persuasive summary that drives clicks…">{{ old('seo_description', $video->seo_description) }}</textarea>
            
          </div>
          <div class="progress len mt-2">
            <div id="descBar" class="progress-bar" role="progressbar" style="width:0%"></div>
          </div>
        </div>

        <div class="divider"></div>

        <div class="card-section-title">Engagement</div>
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label fw-semibold">Hashtags (comma-separated)</label>
           <input type="text" name="hashtags" id="hashtags" class="form-control" placeholder="e.g. tech, review, gadgets" value="{{ old('hashtags', is_array($video->hashtags) ? implode(', ', $video->hashtags) : ($video->hashtags ?? '')) }}">
            <div id="hashtagChips" class="mt-2 d-flex flex-wrap gap-2"></div>
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label fw-semibold">CTA Text</label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-megaphone"></i></span>
              <input type="text" name="cta_text" id="cta_text" class="form-control"
       placeholder="e.g., Watch now, Learn more"
       value="{{ old('cta_text', $video->cta_text) }}">
            </div>
          </div>
        </div>

        <div class="divider"></div>

        <div class="card-section-title">Open Graph & Twitter</div>
        <div class="row g-3">
          <div class="col-12 col-md-6">
            <label class="form-label fw-semibold">OG Image URL</label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-image"></i></span>
              <input type="text" name="og_image_url" id="og_image_url" class="form-control"
       placeholder="https://…/image.jpg"
       value="{{ old('og_image_url', $video->og_image_url) }}">
            </div>
            <div class="form-text">Recommended 1200×630 (1.91:1).</div>
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label fw-semibold">Twitter Title</label>
            <input type="text" name="twitter_title" id="twitter_title" class="form-control"
       value="{{ old('twitter_title', $video->twitter_title) }}" placeholder="Title for Twitter Card">
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label fw-semibold mt-3 mt-md-0">Twitter Description</label>
            <input type="text" name="twitter_description" id="twitter_description" class="form-control"
       value="{{ old('twitter_description', $video->twitter_description) }}" placeholder="Description for Twitter Card">
          </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-4">
          <a href="{{ route('admin.videos.index') }}" class="btn btn-light">
            <i class="bi bi-arrow-left"></i> Cancel
          </a>
          <button type="submit" class="btn btn-primary px-4">
            <i class="bi bi-save"></i> Save SEO
          </button>
        </div>
      </div>
    </div>
  </div>

  {{-- RIGHT: Live previews --}}
  <div class="col-12 col-lg-4">
    <div class="sticky-side">
      {{-- SERP --}}
      <div class="card card-soft mb-3">
        <div class="card-header bg-white border-0 pb-0">
          <div class="card-section-title mb-0">Google SERP Preview</div>
        </div>
        <div class="card-body">
          <div class="serp-card">
            <div class="serp-title" id="serpTitle">{{ old('seo_title', $video->seo_title) ?: 'Your Title Appears Here' }}</div>
            <div class="serp-url">{{ request()->getHost() }}/videos/{{ $video->id }}</div>
            <div class="serp-desc mt-1" id="serpDesc">{{ old('seo_description', $video->seo_description) ?: 'Your description appears here, optimized for click-through rate and relevance.' }}</div>
          </div>
        </div>
      </div>

      {{-- Social Card --}}
      <div class="card card-soft">
        <div class="card-header bg-white border-0 pb-0">
          <div class="card-section-title mb-0">Social Card Preview</div>
        </div>
        <div class="card-body">
          @php $og = old('og_image_url', $video->og_image_url); @endphp
          <div class="ratio ratio-16x9 border">
            <img id="ogPreview" src="{{ $og ?: 'https://placehold.co/1200x630?text=Open+Graph+Image' }}" alt="OG Preview">
          </div>
          <div class="mt-2">
            <div class="fw-semibold" id="twTitle">{{ old('twitter_title', $video->twitter_title) ?: 'Twitter Card Title' }}</div>
            <div class="text-muted small" id="twDesc">{{ old('twitter_description', $video->twitter_description) ?: 'Twitter card description preview goes here.' }}</div>
          </div>
          <div class="divider"></div>
          <div>
            <div class="card-section-title mb-1">Hashtags</div>
            <div id="hashtagChipsPreview" class="d-flex flex-wrap gap-2"></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
</form>
@endsection

@push('scripts')
<script>
(function () {
  const sel = id => document.getElementById(id);
  const clamp = (s, max) => (s || '').toString().slice(0, max);

  const title = sel('seo_title'), desc = sel('seo_description');
  const titleCount = sel('titleCount'), descCount = sel('descCount');
  const titleBar = sel('titleBar'), descBar = sel('descBar');
  const serpTitle = sel('serpTitle'), serpDesc = sel('serpDesc');

  const twTitle = sel('twitter_title'), twDesc = sel('twitter_description');
  const twTitleOut = sel('twTitle'), twDescOut = sel('twDesc');

  const ogInput = sel('og_image_url'), ogPreview = sel('ogPreview');
  const hashtags = sel('hashtags'), chips = sel('hashtagChips'), chipsPrev = sel('hashtagChipsPreview');

  function meter(value, sweetMin, sweetMax, hardMax){
    const v = (value||0); const pct = Math.min(100, Math.round(v / hardMax * 100));
    let cls = 'safe';
    if (v < sweetMin || v > sweetMax) cls = (v <= hardMax ? 'warn' : 'danger');
    return { pct, cls };
  }

  function paintBar(bar, m){
    if(!bar) return;
    bar.style.width = m.pct + '%';
    bar.classList.remove('safe','warn','danger');
    bar.classList.add(m.cls);
  }

  function updateCounts(){
    const tLen = (title?.value || '').length;
    const dLen = (desc?.value || '').length;
    if(titleCount) titleCount.textContent = tLen;
    if(descCount) descCount.textContent = dLen;

    // Title sweet spot 50–60, hard 70
    paintBar(titleBar, meter(tLen, 50, 60, 70));
    // Description sweet 120–160, hard 180
    paintBar(descBar, meter(dLen, 120, 160, 180));
  }

  function updateSerp(){
    if(serpTitle && title){ serpTitle.textContent = clamp(title.value || 'Your Title Appears Here', 70); }
    if(serpDesc && desc){ serpDesc.textContent = clamp(desc.value || 'Your description appears here, optimized for click-through rate and relevance.', 180); }
  }

  function updateTwitter(){
    if(twTitleOut && twTitle){ twTitleOut.textContent = clamp(twTitle.value || 'Twitter Card Title', 70); }
    if(twDescOut && twDesc){ twDescOut.textContent = clamp(twDesc.value || 'Twitter card description preview goes here.', 200); }
  }

  function renderChips(el, list){
    if(!el) return;
    el.innerHTML = '';
    list.forEach(tag=>{
      const s = document.createElement('span');
      s.className = 'chip';
      s.innerHTML = `<i class="bi bi-hash"></i>${tag}`;
      el.appendChild(s);
    });
  }

  function updateHashtags(){
    const raw = (hashtags?.value || '')
      .split(',')
      .map(v=>v.trim())
      .filter(Boolean);
    renderChips(chips, raw);
    renderChips(chipsPrev, raw);
  }

  function updateOG(){
    const v = ogInput?.value?.trim();
    ogPreview && (ogPreview.src = v || 'https://placehold.co/1200x630?text=Open+Graph+Image');
  }

  // Initial render
  updateCounts(); updateSerp(); updateTwitter(); updateHashtags(); updateOG();

  // Listeners
  title && title.addEventListener('input', ()=>{ updateCounts(); updateSerp(); });
  desc && desc.addEventListener('input', ()=>{ updateCounts(); updateSerp(); });
  twTitle && twTitle.addEventListener('input', updateTwitter);
  twDesc && twDesc.addEventListener('input', updateTwitter);
  ogInput && ogInput.addEventListener('input', updateOG);
  hashtags && hashtags.addEventListener('input', updateHashtags);

  // --- NEW: Region change Ajax ---
  const regionSelect = document.getElementById('regions');
  if(regionSelect){
    regionSelect.addEventListener('change', function(){
      let regionId = this.value;
      let videoId = "{{ $video->id }}";

      fetch(`/admin/videos/${videoId}/seo/${regionId}`)
        .then(res => res.json())
        .then(data => {
          sel('seo_title').value = data.seo_title || '';
          sel('seo_description').value = data.seo_description || '';
          sel('hashtags').value = data.hashtags || '';
          sel('cta_text').value = data.cta_text || '';
          sel('og_image_url').value = data.og_image_url || '';
          sel('twitter_title').value = data.twitter_title || '';
          sel('twitter_description').value = data.twitter_description || '';

          // Re-render previews
          updateCounts(); 
          updateSerp(); 
          updateTwitter(); 
          updateHashtags(); 
          updateOG();
        });
    });
  }
})();
</script>
@endpush
