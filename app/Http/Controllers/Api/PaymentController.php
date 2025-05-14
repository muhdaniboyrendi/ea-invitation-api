<?php

namespace App\Http\Controllers\Api;

use Midtrans\Snap;
use Midtrans\Config;
use App\Models\Order;
use App\Models\Package;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class PaymentController extends Controller
{
    public function __construct()
    {
        Config::$serverKey = env('MIDTRANS_SERVER_KEY');
        Config::$isProduction = env('MIDTRANS_IS_PRODUCTION', false);
        Config::$isSanitized = true;
        Config::$is3ds = true;
    }

    public function createPayment(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required',
            'package_id' => 'required',
            // Add other required fields
        ]);

        $package = Package::find($request->package_id);

        $packagePrices = $package->price;

        $amount = $packagePrices;
        
        // Create an order in your database
        $order = Order::create([
            'user_id' => $validated['user_id'],
            'package_id' => $validated['package_id'],
            'amount' => $amount,
            'status' => 'pending',
            'order_id' => 'INV-' . time(),
        ]);

        // Set up the transaction details for Midtrans
        $transaction_details = [
            'order_id' => $order->order_id,
            'gross_amount' => $amount
        ];

        // Customer details
        $customer_details = [
            'first_name' => $request->first_name ?? 'Customer',
            'email' => $request->email ?? 'customer@example.com',
            'phone' => $request->phone ?? '08123456789',
        ];

        // Item details
        $item_details = [
            [
                'id' => $validated['package_id'],
                'price' => $amount,
                'quantity' => 1,
                'name' => 'Paket Undangan ' . $validated['package_id'],
            ]
        ];

        // Transaction data
        $transaction = [
            'transaction_details' => $transaction_details,
            'customer_details' => $customer_details,
            'item_details' => $item_details,
        ];

        try {
            // Get Snap Payment Page URL
            $snapToken = Snap::getSnapToken($transaction);
            
            // Update order with snap token
            $order->update(['snap_token' => $snapToken]);
            
            // Return the snap token
            return response()->json([
                'status' => 'success',
                'snap_token' => $snapToken,
                'order_id' => $order->order_id
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function handleNotification(Request $request)
    {
        $notif = new \Midtrans\Notification();
        
        $transaction = $notif->transaction_status;
        $type = $notif->payment_type;
        $order_id = $notif->order_id;
        $fraud = $notif->fraud_status;

        $order = Order::where('order_id', $order_id)->firstOrFail();

        if ($transaction == 'capture') {
            if ($type == 'credit_card') {
                if ($fraud == 'challenge') {
                    $order->status = 'challenge';
                } else {
                    $order->status = 'success';
                }
            }
        } else if ($transaction == 'settlement') {
            $order->status = 'success';
        } else if ($transaction == 'pending') {
            $order->status = 'pending';
        } else if ($transaction == 'deny') {
            $order->status = 'denied';
        } else if ($transaction == 'expire') {
            $order->status = 'expired';
        } else if ($transaction == 'cancel') {
            $order->status = 'canceled';
        }

        $order->save();
        
        return response()->json(['status' => 'success']);
    }

    public function getStatus($orderId)
    {
        $order = Order::where('order_id', $orderId)->firstOrFail();
        
        return response()->json([
            'status' => $order->status,
            'package_id' => $order->package_id,
            'amount' => $order->amount
        ]);
    }
}
