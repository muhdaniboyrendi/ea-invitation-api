<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Invitation extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function package(): HasOne
    {
        return $this->hasOne(Package::class);
    }

    public function theme(): HasOne
    {
        return $this->hasOne(Theme::class);
    }

    public function guests(): HasMany
    {
        return $this->hasMany(Guest::class);
    }
}
