<?php

namespace App\Http\Controllers\Api;

use Midtrans\Snap;
use Midtrans\Config;
use App\Models\Order;
use App\Models\Package;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
    public function __construct()
    {
        Config::$serverKey = env('MIDTRANS_SERVER_KEY');
        Config::$isProduction = env('MIDTRANS_IS_PRODUCTION', false);
        Config::$isSanitized = true;
        Config::$is3ds = true;
    }

    /**
     * Create order and get payment URL from Midtrans
     * 
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function createPayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'package_id' => 'required|exists:packages,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation Error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        $package = Package::findOrFail($request->package_id);

        $finalPrice = $package->price;
        
        if ($package->discount) {
            $finalPrice = $package->price - ($package->price * $package->discount / 100);
        }

        $orderId = 'ORDER-' . Str::uuid()->toString();

        $order = Order::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'order_id' => $orderId,
            'amount' => $finalPrice,
            'payment_status' => 'pending'
        ]);

        $params = [
            'transaction_details' => [
                'order_id' => $order->order_id,
                'gross_amount' => (int) $order->amount,
            ],
            'customer_details' => [
                'first_name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone ?? '',
            ],
            'item_details' => [
                [
                    'id' => $package->id,
                    'price' => (int) $order->amount,
                    'quantity' => 1,
                    'name' => $package->name,
                ]
            ],
        ];

        try {
            $snapToken = Snap::getSnapToken($params);
            $snapUrl = Snap::getSnapUrl($params);

            $order->update([
                'snap_token' => $snapToken,
                'midtrans_url' => $snapUrl
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Order created successfully',
                'data' => [
                    'order_id' => $order->order_id,
                    'amount' => $order->amount,
                    'snap_token' => $snapToken,
                    'redirect_url' => $snapUrl
                ]
            ], 201);
        } catch (\Exception $e) {
            $order->delete();
            
            return response()->json([
                'status' => false,
                'message' => 'Payment gateway error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get order status
     * 
     * @param string $orderId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getOrderStatus($orderId)
    {
        $order = Order::where('order_id', $orderId)->first();
        
        if (!$order) {
            return response()->json([
                'status' => false,
                'message' => 'Order not found'
            ], 404);
        }

        $user = Auth::user();
        if ($order->user_id !== $user->id) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        return response()->json([
            'status' => true,
            'data' => [
                'order_id' => $order->order_id,
                'amount' => $order->amount,
                'payment_status' => $order->payment_status,
                'payment_method' => $order->payment_method,
                'created_at' => $order->created_at,
                'package' => $order->package->name
            ]
        ]);
    }

    /**
     * Handle notification from Midtrans
     * 
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handleNotification(Request $request)
    {
        $notificationBody = json_decode($request->getContent(), true);
        
        // Verify signature
        $signatureKey = env('MIDTRANS_SERVER_KEY');
        $orderId = $notificationBody['order_id'];
        $statusCode = $notificationBody['status_code'];
        $grossAmount = $notificationBody['gross_amount'];
        $serverKey = $signatureKey;
        $signature = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);
        
        if ($signature !== $notificationBody['signature_key']) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid signature'
            ], 403);
        }

        $order = Order::where('order_id', $orderId)->first();
        if (!$order) {
            return response()->json([
                'status' => false,
                'message' => 'Order not found'
            ], 404);
        }

        // Update payment status based on transaction status
        $transactionStatus = $notificationBody['transaction_status'];
        $fraudStatus = $notificationBody['fraud_status'] ?? null;
        $paymentType = $notificationBody['payment_type'] ?? null;
        $transactionId = $notificationBody['transaction_id'] ?? null;

        if ($transactionStatus == 'capture') {
            if ($fraudStatus == 'challenge') {
                $paymentStatus = 'pending';
            } else if ($fraudStatus == 'accept') {
                $paymentStatus = 'paid';
            }
        } else if ($transactionStatus == 'settlement') {
            $paymentStatus = 'paid';
        } else if ($transactionStatus == 'deny') {
            $paymentStatus = 'canceled';
        } else if ($transactionStatus == 'cancel' || $transactionStatus == 'expire') {
            $paymentStatus = $transactionStatus == 'cancel' ? 'canceled' : 'expired';
        } else if ($transactionStatus == 'pending') {
            $paymentStatus = 'pending';
        }

        // Update order
        $order->update([
            'payment_status' => $paymentStatus,
            'payment_method' => $paymentType,
            'midtrans_transaction_id' => $transactionId
        ]);

        // Process after payment completed - you can add any business logic here
        if ($paymentStatus === 'paid') {
            // Example: Activate user subscription, send email, etc.
        }

        return response()->json([
            'status' => true,
            'message' => 'Notification processed'
        ]);
    }

    /**
     * Get list of user orders
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserOrders()
    {
        $user = Auth::user();
        $orders = Order::where('user_id', $user->id)
            ->with('package:id,name,price')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->id,
                    'order_id' => $order->order_id,
                    'package_name' => $order->package->name,
                    'amount' => $order->amount,
                    'payment_status' => $order->payment_status,
                    'payment_method' => $order->payment_method,
                    'created_at' => $order->created_at->format('Y-m-d H:i:s')
                ];
            });

        return response()->json([
            'status' => true,
            'data' => $orders
        ]);
    }

    /**
     * Cancel order (if still pending)
     * 
     * @param string $orderId
     * @return \Illuminate\Http\JsonResponse
     */
    public function cancelOrder($orderId)
    {
        $order = Order::where('order_id', $orderId)->first();
        
        if (!$order) {
            return response()->json([
                'status' => false,
                'message' => 'Order not found'
            ], 404);
        }

        $user = Auth::user();
        if ($order->user_id !== $user->id) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        if ($order->payment_status !== 'pending') {
            return response()->json([
                'status' => false,
                'message' => 'Only pending orders can be canceled'
            ], 400);
        }

        $order->update(['payment_status' => 'canceled']);

        return response()->json([
            'status' => true,
            'message' => 'Order canceled successfully'
        ]);
    }
}
