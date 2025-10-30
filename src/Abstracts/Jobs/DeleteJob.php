<?php

namespace Microcrud\Abstracts\Jobs;

use Illuminate\Bus\Queueable;
use Microcrud\Abstracts\Service;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;

class DeleteJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $id;
    protected $service;
    protected $forceDelete;
    /**
     * Create a new job instance.
     *
     * @param mixed $id
     * @param Service $service
     * @param bool $forceDelete
     * @return void
     */
    public function __construct($id, $service, $forceDelete = false)
    {
        $this->service = $service;
        $this->id = $id;
        $this->forceDelete = $forceDelete;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->service->delete($this->id, $this->forceDelete);
    }

    public function failed(\Exception $e)
    {
        \Illuminate\Support\Facades\Log::error('MicroCRUD DeleteJob failed: ' . $e->getMessage(), [
            'exception' => $e,
            'id' => $this->id,
            'force_delete' => $this->forceDelete,
            'trace' => $e->getTraceAsString(),
        ]);
    }
}
