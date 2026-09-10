<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AvailabilityRule extends Model
{
    use HasFactory;

    public const DAYS = [
        0 => 'Sunday',
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'calendar_id',
        'day_of_week',
        'start_time',
        'end_time',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
        ];
    }

    /**
     * availability_rules has no location_id and this model does not use
     * BelongsToLocation — same situation as PipelineStage (see CLAUDE.md's
     * PipelineStage tenant-check rule): it's only scoped indirectly, via
     * calendar_id -> Calendar -> location_id. Any code that takes an
     * AvailabilityRule from outside its own calendar's availabilityRules()
     * relation must verify tenancy explicitly. CalendarController never
     * does this — it only ever resolves rules through the already
     * tenant-verified $calendar's own relation.
     */
    public function calendar(): BelongsTo
    {
        return $this->belongsTo(Calendar::class);
    }
}
