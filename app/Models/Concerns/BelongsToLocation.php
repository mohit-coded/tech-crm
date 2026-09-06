<?php

namespace App\Models\Concerns;

use App\Models\Location;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

trait BelongsToLocation
{
    public static function bootBelongsToLocation(): void
    {
        static::addGlobalScope('location', function (Builder $builder) {
            if ($locationId = Auth::user()?->current_location_id) {
                $builder->where($builder->getModel()->getTable().'.location_id', $locationId);
            }
        });

        static::creating(function ($model) {
            if (! $model->location_id && $locationId = Auth::user()?->current_location_id) {
                $model->location_id = $locationId;
            }
        });
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
