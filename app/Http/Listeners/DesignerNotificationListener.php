<?php

namespace App\Listeners;

use App\Events\DesignerNotificationEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class DesignerNotificationListener
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
    public function handle(DesignerNotificationEvent $event): void
    {
        Log::debug('Desinger Notification Event Received', ['data' => $event->message]);
    }
}