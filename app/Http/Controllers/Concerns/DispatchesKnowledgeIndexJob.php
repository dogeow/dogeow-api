<?php

namespace App\Http\Controllers\Concerns;

use App\Jobs\TriggerKnowledgeIndexBuildJob;

trait DispatchesKnowledgeIndexJob
{
    /**
     * Dispatch the knowledge index build job.
     */
    protected function dispatchKnowledgeIndexBuildJob(): void
    {
        TriggerKnowledgeIndexBuildJob::dispatch();
    }
}
