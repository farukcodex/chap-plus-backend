<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SeatUnlockedEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $busId;
    public $travelDate;
    public $seatNumbers;
    public $action = 'unlocked';

    public function __construct($busId, $travelDate, array $seatNumbers)
    {
        $this->busId = $busId;
        $this->travelDate = $travelDate;
        $this->seatNumbers = $seatNumbers;
    }

    public function broadcastAs(): string
    {
        return 'seat.unlocked';
    }

    public function broadcastOn(): array
    {
        return [
            new Channel("bus.{$this->busId}.{$this->travelDate}"),
        ];
    }
}

