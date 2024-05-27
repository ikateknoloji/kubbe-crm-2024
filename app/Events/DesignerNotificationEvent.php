<?php

namespace App\Events;

use App\Models\DesingerNotification;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DesignerNotificationEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;

    /**
     * Create a new event instance.
     */
    public function __construct(array $message)
    {
        // Sadece ilk tasarımcı kullanıcısına bildirim gönder
        $designerNotification = DesingerNotification::create([
            'message' => json_encode($message),
            'is_read' => false,
        ]);

        // Event'in taşıyacağı mesajı ayarla
        $this->message = $designerNotification;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('designer-notifications'),
        ];
    }

    /**
     * 
     */
    public function broadcastAs()
    {
        return 'designer-notifications';
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