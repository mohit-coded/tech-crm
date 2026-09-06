<?php

namespace App\Events;

use App\Models\Opportunity;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OpportunityStageChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Opportunity $opportunity,
        public int $oldStageId,
        public int $newStageId,
    ) {}
}
