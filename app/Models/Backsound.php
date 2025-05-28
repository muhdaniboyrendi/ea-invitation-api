<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Backsound extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'artist',
        'file_path',
        'thumbnail',
    ];

    public function mainInfos()
    {
        return $this->hasMany(MainInfo::class);
    }
}
