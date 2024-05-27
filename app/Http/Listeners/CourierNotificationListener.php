<?php

namespace App\Listeners;

use App\Events\CourierNotificationEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class CourierNotificationListener
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
    public function handle(CourierNotificationEvent $event): void
    {
        Log::debug('Customer Notification Event Received', ['data' => $event->message]);
    }
}