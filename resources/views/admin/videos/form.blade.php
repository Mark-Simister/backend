<form action="{{ isset($video) ? route('admin.videos.update', $video) : route('admin.videos.store') }}" method="POST">
    @csrf
    @if(isset($video)) @method('PUT') @endif

    <div class="form-group">
        <label>Title <span class="text-danger">*</span></label>
        <input type="text" name="title" class="form-control" value="{{ old('title', $video->title ?? '') }}" required>
        @error('title') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="form-group">
        <label>Description</label>
        <textarea name="description" class="form-control" rows="3">{{ old('description', $video->description ?? '') }}</textarea>
    </div>

    <div class="form-group">
        <label>Type <span class="text-danger">*</span></label>
        <select name="type" class="form-control" required>
            <option value="youtube" {{ old('type', $video->type ?? '') == 'youtube' ? 'selected' : '' }}>YouTube</option>
            <option value="vimeo" {{ old('type', $video->type ?? '') == 'vimeo' ? 'selected' : '' }}>Vimeo</option>
        </select>
    </div>

    <div class="form-group">
        <label>Video URL</label>
        <input type="text" name="video_url" class="form-control" value="{{ old('video_url', $video->video_url ?? '') }}">
    </div>

    <div class="form-group">
        <label>Thumbnail URL</label>
        <input type="text" name="thumbnail_url" class="form-control" value="{{ old('thumbnail_url', $video->thumbnail_url ?? '') }}">
    </div>

    <div class="form-group">
        <label>Character</label>
        <select name="character_id" class="form-control">
            <option value="">Select Character</option>
            @foreach($characters as $character)
                <option value="{{ $character->id }}" {{ old('character_id', $video->character_id ?? '') == $character->id ? 'selected' : '' }}>{{ $character->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="form-group">
        <label>Channel</label>
        <select name="channel_id" class="form-control">
            <option value="">Select Channel</option>
            @foreach($channels as $channel)
                <option value="{{ $channel->id }}" {{ old('channel_id', $video->channel_id ?? '') == $channel->id ? 'selected' : '' }}>{{ $channel->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="form-group">
        <label>Category</label>
        <select name="category_id" class="form-control">
            <option value="">Select Category</option>
            @foreach($categories as $category)
                <option value="{{ $category->id }}" {{ old('category_id', $video->category_id ?? '') == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="form-group">
        <label>Access Level</label>
        <select name="access_level" class="form-control">
            <option value="public" {{ old('access_level', $video->access_level ?? '') == 'public' ? 'selected' : '' }}>Public</option>
            <option value="premium" {{ old('access_level', $video->access_level ?? '') == 'premium' ? 'selected' : '' }}>Premium</option>
            <option value="early_access" {{ old('access_level', $video->access_level ?? '') == 'early_access' ? 'selected' : '' }}>Early Access</option>
        </select>
    </div>

    <div class="form-group">
        <label>Affiliate Link</label>
        <input type="text" name="affiliate_link" class="form-control" value="{{ old('affiliate_link', $video->affiliate_link ?? '') }}">
    </div>

    <div class="mt-4">
        <button type="submit" class="btn btn-success">{{ isset($video) ? 'Update' : 'Create' }}</button>
        <a href="{{ route('admin.videos.index') }}" class="btn btn-secondary">Back</a>
    </div>
</form>