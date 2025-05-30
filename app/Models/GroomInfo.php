<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GroomInfo extends Model
{
    use HasFactory;

    protected $fillable = [
        'invitation_id',
        'groom_fullname',
        'groom_father',
        'groom_mother',
        'groom_instagram',
        'groom_photo',
    ];

    public function getGroomPhotoUrlAttribute(): ?string
    {
        return $this->groom_photo ? asset('storage/' . $this->groom_photo) : null;
    }

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(Invitation::class);
    }
}
