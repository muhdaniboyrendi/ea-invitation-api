<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GiftInfo extends Model
{
    use HasFactory;

    protected $table = 'gift_info';

    protected $fillable = [
        'invitation_id',
        'bank_name',
        'account_number',
        'account_holder',
    ];

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(Invitation::class);
    }
}
