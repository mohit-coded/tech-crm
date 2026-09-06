<?php

namespace App\Models;

use App\Events\OpportunityStageChanged;
use App\Models\Concerns\BelongsToLocation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Opportunity extends Model
{
    use HasFactory, BelongsToLocation;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'location_id',
        'contact_id',
        'pipeline_id',
        'pipeline_stage_id',
        'name',
        'monetary_value',
        'status',
        'source',
        'owner_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'monetary_value' => 'decimal:2',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(PipelineStage::class, 'pipeline_stage_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * The only sanctioned way to change stage — raw attribute updates
     * elsewhere would silently skip the OpportunityStageChanged event.
     */
    public function moveToStage(PipelineStage $stage): void
    {
        $oldStageId = $this->pipeline_stage_id;

        $this->update(['pipeline_stage_id' => $stage->id]);

        OpportunityStageChanged::dispatch($this, $oldStageId, $stage->id);
    }
}
