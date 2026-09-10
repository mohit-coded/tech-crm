<?php

namespace App\Models;

use App\Models\Concerns\BelongsToLocation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appointment extends Model
{
    use HasFactory, BelongsToLocation;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'location_id',
        'calendar_id',
        'contact_id',
        'starts_at',
        'ends_at',
        'status',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * starts_at/ends_at are always stored in UTC (app.timezone is UTC —
     * see config/app.php), so the plain 'datetime' cast round-trips them
     * correctly without any explicit timezone conversion.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function calendar(): BelongsTo
    {
        return $this->belongsTo(Calendar::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
