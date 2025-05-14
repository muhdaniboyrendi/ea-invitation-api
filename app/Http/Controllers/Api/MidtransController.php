<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class MidtransController extends Controller
{
    public function notificationHandler(Request $request)
{
    $json = $request->getContent();
    $notification = json_decode($json);

    $orderId = $notification->order_id;
    $order = Order::where('order_number', $orderId)->first();

    if ($order) {
        $signatureKey = hash('sha512', $notification->order_id . $notification->status_code . $notification->gross_amount . config('midtrans.server_key'));
        if ($signatureKey === $notification->signature_key) {
            $order->payment_method = $notification->payment_type;
            if ($notification->transaction_status == 'capture' || $notification->transaction_status == 'settlement') {
                $order->payment_status = 'paid';
            } elseif ($notification->transaction_status == 'pending') {
                $order->payment_status = 'pending';
            } elseif ($notification->transaction_status == 'deny' || $notification->transaction_status == 'expire' || $notification->transaction_status == 'cancel') {
                $order->payment_status = 'canceled';
            }
            $order->midtrans_transaction_id = $notification->transaction_id;
            $order->save();
        }
    }

    return response()->json(['status' => 'success']);
}
}
