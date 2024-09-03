<?php

namespace Microcrud\Abstracts\Jobs;

use Illuminate\Bus\Queueable;
use Microcrud\Abstracts\Service;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;

class StoreJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $data;
    protected $service;
    /**
     * Create a new job instance.
     *
     * @param array $data
     * @return void
     */
    public function __construct($data, $service)
    {
        $this->service = $service;
        $this->data = $data;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->service->create($this->data);
    }

    // public function failed(\Exception $e)
    // {
    //     \Illuminate\Support\Facades\Log::debug('MyNotification failed');
    // }
}
