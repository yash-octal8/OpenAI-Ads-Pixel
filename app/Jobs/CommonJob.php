<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;

class CommonJob implements ShouldQueue
{
    use Queueable;

    public $data;

    /**
     * Create a new job instance.
     */
    public function __construct($data)
    {
        //$this->onQueue('common');
        $this->data = $data;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        //
    }
}
