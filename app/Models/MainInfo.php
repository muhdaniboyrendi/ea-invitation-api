<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MainInfo extends Model
{
    use HasFactory;

    protected $fillable = [
        'invitation_id',
        'backsound_id',
        'main_photo',
        'wedding_date',
        'wedding_time',
        'time_zone',
        'custom_backsound',
    ];

    protected $casts = [
        'wedding_date' => 'date',
        'wedding_time' => 'datetime:H:i',
    ];

    protected $appends = [
        'main_photo_url',
        'custom_backsound_url',
    ];

    public function getMainPhotoUrlAttribute(): ?string
    {
        return $this->main_photo ? asset('storage/' . $this->main_photo) : null;
    }

    public function getCustomBacksoundUrlAttribute(): ?string
    {
        return $this->custom_backsound ? asset('storage/' . $this->custom_backsound) : null;
    }

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(Invitation::class);
    }

    public function backsound(): BelongsTo
    {
        return $this->belongsTo(Backsound::class);
    }
}
