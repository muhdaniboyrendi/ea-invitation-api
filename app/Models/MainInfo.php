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
        'main_photo',
        'wedding_date',
        'wedding_time',
        'time_zone',
    ];

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(Invitation::class);
    }
}
