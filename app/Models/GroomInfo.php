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
        'groom_callname',
        'groom_father',
        'groom_mother',
        'groom_instagram',
        'groom_photo',
    ];

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(Invitation::class);
    }
}
