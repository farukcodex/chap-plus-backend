<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('order.{id}', function ($user, $id) {
    // Both the Customer and the Assigned Rider can listen to this order's channel
    $order = \App\Models\Order::find($id);
    
    if (!$order) {
        return false;
    }

    return (int) $user->id === (int) $order->user_id || (int) $user->id === (int) $order->rider_id;
});
