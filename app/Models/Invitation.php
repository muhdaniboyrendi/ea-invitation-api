<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Invitation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'order_id',
        'theme_id',
        'status',
        'expiry_date',
        'groom',
        'bride',
        'slug'
    ];

    protected $casts = [
        'expiry_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function theme(): BelongsTo
    {
        return $this->belongsTo(Theme::class);
    }

    public function mainInfo(): HasOne
    {
        return $this->hasOne(MainInfo::class);
    }

    public function groomInfo(): HasOne
    {
        return $this->hasOne(GroomInfo::class);
    }

    public function brideInfo(): HasOne
    {
        return $this->hasOne(BrideInfo::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function loveStories(): HasMany
    {
        return $this->hasMany(LoveStory::class);
    }

    public function galleryImages(): HasMany
    {
        return $this->hasMany(Gallery::class);
    }

    public function giftInfo(): HasMany
    {
        return $this->hasMany(GiftInfo::class);
    }

    public function guests(): HasMany
    {
        return $this->hasMany(Guest::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }
}
