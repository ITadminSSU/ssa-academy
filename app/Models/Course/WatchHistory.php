<?php

namespace App\Models\Course;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WatchHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'course_id',
        'current_section_id',
        'current_watching_id',
        'current_watching_type',
        'next_watching_id',
        'next_watching_type',
        'completed_watching',
        'lesson_watch_progress',
        'completion_date',
    ];

    protected $casts = [
        'completed_watching' => 'array',
        'lesson_watch_progress' => 'array',
    ];

    /**
     * Safely read completed curriculum items.
     * Older codepaths stored json_encode()'d strings into an array-cast column,
     * which can leave a string or double-encoded payload after hydration.
     */
    public function getCompletedWatchingItems(): array
    {
        $raw = $this->completed_watching;

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_string($decoded) ? json_decode($decoded, true) : $decoded;
        }

        if (! is_array($raw)) {
            return [];
        }

        // Legacy double-encode sometimes yields a one-element list of JSON text.
        if (isset($raw[0]) && is_string($raw[0])) {
            $decoded = json_decode($raw[0], true);
            $raw = is_array($decoded) ? $decoded : [];
        }

        return array_values(array_filter(
            $raw,
            fn ($item) => is_array($item) && isset($item['id'], $item['type'])
        ));
    }

    public function setCompletedWatchingItems(array $items): static
    {
        $cleaned = [];
        $seen = [];

        foreach ($items as $item) {
            if (! is_array($item) || ! isset($item['id'], $item['type'])) {
                continue;
            }

            $normalized = [
                'id' => (string) $item['id'],
                'type' => $item['type'],
            ];
            $key = $normalized['id'] . '|' . $normalized['type'];

            if (! isset($seen[$key])) {
                $seen[$key] = true;
                $cleaned[] = $normalized;
            }
        }

        // Assign a real array so the Eloquent cast encodes once.
        $this->completed_watching = $cleaned;

        return $this;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }
}
