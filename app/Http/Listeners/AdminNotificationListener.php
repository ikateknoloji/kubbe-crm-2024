<?php

namespace App\Listeners;

use App\Events\AdminNotificationEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class AdminNotificationListener
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(AdminNotificationEvent $event)
    {
        Log::debug('Admin Notification Event Received', ['data' => $event->message]);
    }
}