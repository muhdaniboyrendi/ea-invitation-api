<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BrideInfo extends Model
{
    use HasFactory;

    protected $fillable = [
        'invitation_id',
        'bride_fullname',
        'bride_father',
        'bride_mother',
        'bride_instagram',
        'bride_photo',
    ];

    protected $appends = ['bridePhotoUrl'];

    public function getBridePhotoUrlAttribute(): ?string
    {
        return $this->bride_photo ? asset('storage/' . $this->bride_photo) : null;
    }

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(Invitation::class);
    }
}
