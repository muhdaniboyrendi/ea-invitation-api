<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CoupleInfo extends Model
{
    use HasFactory;

    protected $table = 'couple_info';

    protected $fillable = [
        'invitation_id',
        'groom_name',
        'groom_father',
        'groom_mother',
        'groom_instagram',
        'bride_name',
        'bride_father',
        'bride_mother',
        'bride_instagram',
    ];

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(Invitation::class);
    }
}
