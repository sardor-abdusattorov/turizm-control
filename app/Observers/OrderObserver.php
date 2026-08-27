<?php

namespace App\Observers;

use App\Models\Order;
use Illuminate\Support\Facades\Storage;

class OrderObserver
{
    public function deleting(Order $order): void
    {
        if ($order->file_path && Storage::disk('local')->exists($order->file_path)) {
            Storage::disk('local')->delete($order->file_path);
        }
    }
}
