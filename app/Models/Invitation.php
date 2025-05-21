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

    public function coupleInfo(): HasOne
    {
        return $this->hasOne(CoupleInfo::class);
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

    public function rsvps(): HasMany
    {
        return $this->hasMany(Rsvp::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function visitorLogs(): HasMany
    {
        return $this->hasMany(VisitorLog::class);
    }

    public function getAkadEvent()
    {
        return $this->events()->where('type', 'akad')->first();
    }

    public function getReceptionEvent()
    {
        return $this->events()->where('type', 'reception')->first();
    }
}
