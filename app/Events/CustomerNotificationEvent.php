<?php

namespace App\Events;

use App\Models\UserNotification;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CustomerNotificationEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $customer_id;
    public $message;

    /**
     * Create a new event instance.
     */
    public function __construct($customer_id, array $message)
    {
        $this->customer_id = $customer_id;
        $this->message = $message;

        // Veritabanına müşteri bildirimini kaydet.
        $customerNotification = UserNotification::create([
            'user_id' => $this->customer_id,
            'message' => json_encode($this->message),
        ]);

        
        $this->message = $customerNotification;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->customer_id),
        ];
    }
    
    /**
     * Summary of broadcastAs
     * @return string
     */
    public function broadcastAs()
    {
        return 'customer.notification';
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