<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\ShopifyService;
use Illuminate\Console\Command;

class testCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:testing';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List or register Shopify webhooks and check active access scopes';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        dd('testCommand executed successfully!');
    }
}
