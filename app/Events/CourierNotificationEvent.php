<?php

namespace App\Events;

use App\Models\CourierNotification;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CourierNotificationEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;
    /**
     * Create a new event instance.
     */
    public function __construct(array $message)
    {
        // Veritabanına courier bildirimini kaydet.
        $courierNotification = CourierNotification::create([
            'message' => json_encode($message),
            'is_read' => false,
        ]);
    
        // CourierNotification::create() metodunun döndürdüğü nesneyi kullan
        $this->message = $courierNotification;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('courier-notifications'),
        ];
    }

    /**
     * Summary of broadcastAs
     * @return string
     */
    public function broadcastAs()
    {
        return 'courier-notifications';
    }

    /**
     * The event's broadcast data.
     *
     * @return array
     */
    public function broadcastWith()
    {
        return [
            'message' => $this->message,
        ];
    }
}