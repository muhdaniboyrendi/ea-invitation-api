<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Gallery extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'invitation_id',
        'image_path',
    ];

    protected $appends = [
        'image_url',
        'image_size',
    ];

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(Invitation::class);
    }

     /**
     * Get the full URL of the image
     */
    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? asset('storage/' . $this->image_path) : null;
    }

    /**
     * Get the image file size in KB
     */
    public function getImageSizeAttribute(): ?int
    {
        if (!$this->image_path || !Storage::disk('public')->exists($this->image_path)) {
            return null;
        }

        $sizeInBytes = Storage::disk('public')->size($this->image_path);
        return round($sizeInBytes / 1024); // Convert to KB
    }

    /**
     * Scope to filter by invitation
     */
    // public function scopeByInvitation($query, $invitationId)
    // {
    //     return $query->where('invitation_id', $invitationId);
    // }

    /**
     * Scope to order by creation date
     */
    // public function scopeLatest($query)
    // {
    //     return $query->orderBy('created_at', 'desc');
    // }

    /**
     * Boot method to handle model events
     */
    // protected static function boot()
    // {
    //     parent::boot();

    //     // Delete image file when model is deleted
    //     static::deleting(function ($gallery) {
    //         if ($gallery->image_path && Storage::disk('public')->exists($gallery->image_path)) {
    //             Storage::disk('public')->delete($gallery->image_path);
    //         }
    //     });
    // }
}
