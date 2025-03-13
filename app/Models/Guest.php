<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Guest extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function invitation()
    {
        return $this->belongsTo(Invitation::class);
    }
}
