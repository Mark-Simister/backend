<?php

namespace App\Models;

use App\Support\Media\ChannelCardImages;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Channel extends Model
{
    use HasFactory;


    protected $fillable = [
        'name',
        'image',
        'video',
        'primary_color',
        'secondary_color',
        'accent_color',
        'background_color',
        'text_color',
        'hover_color',
        'highlight_color',
        'cta',
        'channel_category',
    ];

    // bio, category_label and pipeline_code are Admin Content & Media columns (design
    // record section 9.2). Hidden until the public channel API composes them explicitly
    // (Sprint C), so adding the columns changes no public output.
    protected $hidden = ['image', 'bio', 'category_label', 'pipeline_code'];
    protected $appends = ['image_url'];

    /** Set only by public channel-discovery listings; see useCardImage(). */
    private bool $usesCardImage = false;

    public function getImageUrlAttribute()
    {
        if ($this->usesCardImage) {
            return $this->cardImageUrl();
        }

        return $this->image ? asset($this->image) : null;
    }

    /**
     * Serialise this instance's `image_url` as card artwork (cardImageUrl()). Only public
     * channel-discovery listings opt in; every other serialisation - channel detail, admin,
     * console and queued work - keeps the legacy `image` value.
     */
    public function useCardImage(): static
    {
        $this->usesCardImage = true;

        return $this;
    }

    /**
     * Public card artwork: the channel's active managed `card_image` (resolved in one
     * batched lookup per request), otherwise the legacy `image` path, otherwise null.
     */
    public function cardImageUrl(): ?string
    {
        $managed = $this->exists ? ChannelCardImages::forCurrentRequest()->urlFor($this->getKey()) : null;

        return $managed ?? ($this->image ? asset($this->image) : null);
    }

    public function categories()
    {
        return $this->hasMany(Category::class);
    }

    // public function categories()
    // {
    //     return $this->belongsToMany(Category::class, 'category_channel');
    // }
    public function videos()
    {
        return $this->belongsToMany(Video::class, 'video_channel');
    }

    public function regions()
    {
        return $this->belongsToMany(Region::class, 'channel_region');
    }

    public function followers()
    {
        return $this->hasMany(ChannelFollow::class, 'channel_id');
    }
}
