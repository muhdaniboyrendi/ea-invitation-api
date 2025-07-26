<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Backsound extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'artist',
        'audio',
        'thumbnail',
    ];

    protected $appends = ['audioUrl', 'thumbnailUrl'];

    public function getAudioUrlAttribute(): ?string
    {
        return $this->audio ? asset('storage/' . $this->audio) : null;
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        return $this->thumbnail ? asset('storage/' . $this->thumbnail) : null;
    }

    public function mainInfos()
    {
        return $this->hasMany(MainInfo::class);
    }
}
