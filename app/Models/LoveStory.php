<?php

namespace App\Models;

use Carbon\Carbon;
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
    ];

    protected $casts = [
        'date' => 'date',
    ];

    protected $appends = [
        'formatted_date',
        'short_description',
        'word_count',
        'years_ago',
    ];

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(Invitation::class);
    }

    /**
     * Get formatted date attribute.
     */
    public function getFormattedDateAttribute(): ?string
    {
        return $this->date ? $this->date->format('d F Y') : null;
    }

    /**
     * Get short description (first 100 characters).
     */
    public function getShortDescriptionAttribute(): ?string
    {
        if (!$this->description) {
            return null;
        }

        return strlen($this->description) > 100 
            ? substr($this->description, 0, 100) . '...'
            : $this->description;
    }

    /**
     * Get word count of description.
     */
    public function getWordCountAttribute(): int
    {
        return $this->description ? str_word_count(strip_tags($this->description)) : 0;
    }

    /**
     * Get years ago from the date.
     */
    public function getYearsAgoAttribute(): ?string
    {
        if (!$this->date) {
            return null;
        }

        $yearsAgo = Carbon::now()->diffInYears($this->date);
        
        if ($yearsAgo == 0) {
            $monthsAgo = Carbon::now()->diffInMonths($this->date);
            if ($monthsAgo == 0) {
                $daysAgo = Carbon::now()->diffInDays($this->date);
                return $daysAgo == 0 ? 'Today' : $daysAgo . ' days ago';
            }
            return $monthsAgo . ' months ago';
        }
        
        return $yearsAgo . ' years ago';
    }

    /**
     * Get the month and year from date.
     */
    public function getMonthYearAttribute(): ?string
    {
        return $this->date ? $this->date->format('F Y') : null;
    }

    /**
     * Get the season from date.
     */
    public function getSeasonAttribute(): ?string
    {
        if (!$this->date) {
            return null;
        }

        $month = $this->date->month;
        
        if (in_array($month, [12, 1, 2])) {
            return 'Winter';
        } elseif (in_array($month, [3, 4, 5])) {
            return 'Spring';
        } elseif (in_array($month, [6, 7, 8])) {
            return 'Summer';
        } else {
            return 'Fall';
        }
    }

    /**
     * Scope to get stories with dates.
     */
    public function scopeWithDates($query)
    {
        return $query->whereNotNull('date');
    }

    /**
     * Scope to get stories without dates.
     */
    public function scopeWithoutDates($query) 
    {
        return $query->whereNull('date');
    }

    /**
     * Scope to get stories by year.
     */
    public function scopeByYear($query, $year)
    {
        return $query->whereYear('date', $year);
    }

    /**
     * Scope to get stories by date range.
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    /**
     * Scope to order by chronological order.
     */
    public function scopeChronological($query)
    {
        return $query->orderBy('date')->orderBy('created_at');
    }

    /**
     * Scope to order by reverse chronological order.
     */
    public function scopeReverseChronological($query)
    {
        return $query->orderBy('date', 'desc')->orderBy('created_at', 'desc');
    }

    /**
     * Scope to search by title or description.
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function($q) use ($search) {
            $q->where('title', 'like', '%' . $search . '%')
              ->orWhere('description', 'like', '%' . $search . '%');
        });
    }

    /**
     * Check if story has a description.
     */
    public function hasDescription(): bool
    {
        return !empty($this->description);
    }

    /**
     * Check if story has a date.
     */
    public function hasDate(): bool
    {
        return !is_null($this->date);
    }

    /**
     * Get reading time estimate (based on 200 words per minute).
     */
    public function getReadingTimeAttribute(): string
    {
        if (!$this->description) {
            return '0 min';
        }

        $minutes = ceil($this->word_count / 200);
        return $minutes . ' min read';
    }

    /**
     * Get all love stories grouped by year.
     */
    public static function getGroupedByYear($invitationId)
    {
        return self::where('invitation_id', $invitationId)
            ->withDates()
            ->chronological()
            ->get()
            ->groupBy(function($story) {
                return $story->date->format('Y');
            });
    }

    /**
     * Get love story statistics for an invitation.
     */
    public static function getStatistics($invitationId)
    {
        $stories = self::where('invitation_id', $invitationId)->get();
        
        return [
            'total_stories' => $stories->count(),
            'stories_with_dates' => $stories->whereNotNull('date')->count(),
            'stories_without_dates' => $stories->whereNull('date')->count(),
            'total_words' => $stories->sum('word_count'),
            'earliest_date' => $stories->whereNotNull('date')->min('date'),
            'latest_date' => $stories->whereNotNull('date')->max('date'),
            'average_words_per_story' => $stories->count() > 0 ? round($stories->sum('word_count') / $stories->count()) : 0,
        ];
    }

    /**
     * Create a timeline of love stories.
     */
    public static function createTimeline($invitationId)
    {
        return self::where('invitation_id', $invitationId)
            ->withDates()
            ->chronological() 
            ->get()
            ->map(function($story) {
                return [
                    'id' => $story->id,
                    'title' => $story->title,
                    'date' => $story->date->format('Y-m-d'),
                    'formatted_date' => $story->formatted_date,
                    'years_ago' => $story->years_ago,
                    'short_description' => $story->short_description,
                    'season' => $story->season,
                ];
            });
    }
}
