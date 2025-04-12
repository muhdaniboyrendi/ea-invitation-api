<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LoveStory extends Model
{
    use HasFactory;

    protected $fillable = [
        'invitation_id',
        'title',
        'date',
        'description',
        'order_number',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(Invitation::class);
    }
}
