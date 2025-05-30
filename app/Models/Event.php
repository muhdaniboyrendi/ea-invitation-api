<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'invitation_id',
        'name',
        'venue',
        'date',
        'time_start',
        'time_end',
        'address',
        'maps_link',
    ];

    protected $casts = [
        'date' => 'date',
        'time_start' => 'time',
        'time_end' => 'time',
    ];

    protected $appends = [
        'formatted_date',
        'formatted_time_range',
        'formatted_date_time',
        'is_past_event',
        'days_until_event',
    ];

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(Invitation::class);
    }

    /**
     * Get formatted date attribute.
     */
    public function getFormattedDateAttribute(): string
    {
        return $this->date->format('l, d F Y');
    }

    /**
     * Get formatted time range attribute.
     */
    public function getFormattedTimeRangeAttribute(): string
    {
        $startTime = $this->time_start->format('H:i');
        
        if ($this->time_end) {
            $endTime = $this->time_end->format('H:i');
            return "{$startTime} - {$endTime}";
        }
        
        return $startTime;
    }

    /**
     * Get formatted date and time attribute.
     */
    public function getFormattedDateTimeAttribute(): string
    {
        return $this->formatted_date . ' at ' . $this->formatted_time_range;
    }

    /**
     * Check if event is in the past.
     */
    public function getIsPastEventAttribute(): bool
    {
        $eventDateTime = Carbon::createFromFormat('Y-m-d H:i:s', 
            $this->date->format('Y-m-d') . ' ' . $this->time_start->format('H:i:s')
        );
        
        return $eventDateTime->isPast();
    }

    /**
     * Get days until event.
     */
    public function getDaysUntilEventAttribute(): int
    {
        $eventDate = $this->date->startOfDay();
        $today = Carbon::now()->startOfDay();
        
        return $today->diffInDays($eventDate, false);
    }

    /**
     * Scope to get upcoming events.
     */
    public function scopeUpcoming($query)
    {
        return $query->where('date', '>=', Carbon::now()->toDateString())
                    ->orderBy('date')
                    ->orderBy('time_start');
    }

    /**
     * Scope to get past events.
     */
    public function scopePast($query)
    {
        return $query->where('date', '<', Carbon::now()->toDateString())
                    ->orderBy('date', 'desc')
                    ->orderBy('time_start', 'desc');
    }

    /**
     * Scope to get events by date range.
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    /**
     * Get the event duration in minutes.
     */
    public function getDurationInMinutes(): ?int
    {
        if (!$this->time_end) {
            return null;
        }

        $start = Carbon::createFromFormat('H:i:s', $this->time_start->format('H:i:s'));
        $end = Carbon::createFromFormat('H:i:s', $this->time_end->format('H:i:s'));
        
        return $start->diffInMinutes($end);
    }

    /**
     * Get formatted duration.
     */
    public function getFormattedDuration(): ?string
    {
        $minutes = $this->getDurationInMinutes();
        
        if (!$minutes) {
            return null;
        }

        $hours = intval($minutes / 60);
        $remainingMinutes = $minutes % 60;

        if ($hours > 0 && $remainingMinutes > 0) {
            return "{$hours} hours {$remainingMinutes} minutes";
        } elseif ($hours > 0) {
            return "{$hours} " . ($hours === 1 ? 'hour' : 'hours');
        } else {
            return "{$remainingMinutes} " . ($remainingMinutes === 1 ? 'minute' : 'minutes');
        }
    }

    /**
     * Check if event has location details.
     */
    public function hasLocationDetails(): bool
    {
        return !empty($this->address) || !empty($this->maps_url) || !empty($this->maps_embed_url);
    }

    /**
     * Get Google Calendar URL for the event.
     */
    public function getGoogleCalendarUrl(): string
    {
        $startDateTime = $this->date->format('Ymd') . 'T' . $this->time_start->format('His');
        $endDateTime = $this->time_end 
            ? $this->date->format('Ymd') . 'T' . $this->time_end->format('His')
            : $this->date->format('Ymd') . 'T' . $this->time_start->addHour()->format('His');

        $params = [
            'action' => 'TEMPLATE',
            'text' => urlencode($this->name),
            'dates' => $startDateTime . '/' . $endDateTime,
            'location' => urlencode($this->venue . ($this->address ? ', ' . $this->address : '')),
            'details' => urlencode('Event: ' . $this->name),
        ];

        return 'https://calendar.google.com/calendar/render?' . http_build_query($params);
    }
}
