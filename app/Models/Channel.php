<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Channel extends Model
{
    use HasFactory;


    protected $fillable = ['name', 'image','primary_color',
        'secondary_color',
        'accent_color',
        'background_color',];

    protected $hidden = ['image'];
    protected $appends = ['image_url'];

    public function getImageUrlAttribute()
    {
        return $this->image ? asset($this->image) : null;
    }

    public function categories()
    {
        return $this->hasMany(Category::class);
    }
    public function regions()
    {
        return $this->belongsToMany(Region::class, 'channel_region');
    }
}