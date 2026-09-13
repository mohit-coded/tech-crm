<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignStep extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'campaign_id',
        'position',
        'channel',
        'body',
        'delay_minutes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'delay_minutes' => 'integer',
        ];
    }

    /**
     * campaign_steps has no location_id and this model does not use
     * BelongsToLocation — same situation as PipelineStage/AvailabilityRule
     * (see CLAUDE.md's PipelineStage tenant-check rule): it's only scoped
     * indirectly, via campaign_id -> Campaign -> location_id. Any code
     * that takes a CampaignStep from outside its own campaign's steps()
     * relation must verify tenancy explicitly. CampaignController never
     * does this — it only ever resolves steps through the already
     * tenant-verified $campaign's own relation.
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
