<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use App\Models\Package;
use App\Models\Invitation;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
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
        $package = Package::find($request->package_id);
        
        if (!$package) {
            return response()->json([
                'status' => false,
                'message' => 'Package not found'
            ], 404);
        }

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

        $grossAmount = (int) $order->amount;

        $params = [
            'transaction_details' => [
                'order_id' => $order->order_id,
                'gross_amount' => $grossAmount,
            ],
            'customer_details' => [
                'first_name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone ?? '',
            ],
            'item_details' => [
                [
                    'id' => $package->id,
                    'price' => $grossAmount,
                    'quantity' => 1,
                    'name' => $package->name,
                ]
            ],
        ];

        try {
            $snapData = $this->getSnapTokenWithHttpRequest($params);
            
            $order->update([
                'snap_token' => $snapData['token'],
                'midtrans_url' => $snapData['redirect_url']
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Order created successfully',
                'data' => [
                    'order_id' => $order->order_id,
                    'amount' => $order->amount,
                    'snap_token' => $snapData['token'],
                    'redirect_url' => $snapData['redirect_url']
                ]
            ], 201);
        } catch (\Exception $e) {
            $order->update(['payment_status' => 'canceled']);
            
            return response()->json([
                'status' => false,
                'message' => 'Payment gateway error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updatePayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|exists:orders,order_id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation Error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        $order = Order::where('order_id', $request->order_id)->first();

        if (!$order) {
            return response()->json([
                'status' => false,
                'message' => 'Order not found'
            ], 404);
        }
    
        if ($order->user_id !== $user->id) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        return response()->json([
            'status' => true,
            'message' => 'Order successfully updated',
            'data' => [
                'order_id' => $order->order_id,
                'amount' => $order->amount,
                'snap_token' => $order->snap_token,
                'redirect_url' => $order->midtrans_url
            ]
        ], 200);
    }

    private function getSnapTokenWithHttpRequest(array $params)
    {
        $isProduction = env('MIDTRANS_IS_PRODUCTION', false);
        $serverKey = env('MIDTRANS_SERVER_KEY');
        
        if (empty($serverKey)) {
            throw new \Exception('Midtrans Server Key is not set');
        }
        
        $baseUrl = $isProduction
            ? 'https://app.midtrans.com/snap/v1/transactions'
            : 'https://app.sandbox.midtrans.com/snap/v1/transactions';
        
        $client = new \GuzzleHttp\Client();
        
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Authorization' => 'Basic ' . base64_encode($serverKey . ':')
        ];
        
        $response = $client->post($baseUrl, [
            'headers' => $headers,
            'json' => $params
        ]);
        
        $statusCode = $response->getStatusCode();
        
        if ($statusCode !== 201) {
            throw new \Exception('Failed to get Snap Token from Midtrans. Status code: ' . $statusCode);
        }
        
        $responseData = json_decode($response->getBody()->getContents(), true);
        
        if (!isset($responseData['token']) || !isset($responseData['redirect_url'])) {
            throw new \Exception('Invalid response from Midtrans');
        }
        
        return [
            'token' => $responseData['token'],
            'redirect_url' => $responseData['redirect_url']
        ];
    }

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

    public function handleNotification(Request $request)
    {
        $notificationBody = json_decode($request->getContent(), true);
        
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

        $order->update([
            'payment_status' => $paymentStatus,
            'payment_method' => $paymentType,
            'midtrans_transaction_id' => $transactionId
        ]);

        if ($paymentStatus === 'paid') {
            // make something
        }

        return response()->json([
            'status' => true,
            'message' => 'Notification processed'
        ], 200);
    }

    public function getOrders()
    {
        if (Auth::user()->role != 'admin') {
            return response()->json([
                'status' => false,
                'message' => 'Forbidden access'
            ], 403);
        }

        $orders = Order::with('user', 'package', 'invitation')->get();

        return response()->json([
            'status' => true,
            'message' => 'Ok',
            'data' => $orders
        ]);
    }

    public function getOrderDetail(string $id)
    {
        if (Auth::user()->role != 'admin') {
            return response()->json([
                'status' => false,
                'message' => 'Forbidden access'
            ], 403);
        }

        $order = Order::with('user', 'package', 'invitation')->find($id);

        return response()->json([
            'status' => true,
            'message' => 'Ok',
            'data' => $order
        ]);
    }

    public function getUserOrders()
    {
        $user = Auth::user();
        $orders = Order::where('user_id', $user->id)
            ->with('package')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->id,
                    'order_id' => $order->order_id,
                    'package_id' => $order->package->id,
                    'package_name' => $order->package->name,
                    'amount' => $order->amount,
                    'payment_status' => $order->payment_status,
                    'payment_method' => $order->payment_method,
                    'updated_at' => $order->updated_at->format('Y-m-d H:i:s')
                ];
            });

        return response()->json([
            'status' => true,
            'data' => $orders
        ]);
    }

    public function getOrder(string $orderId)
    {
        $order = Order::where('order_id', $orderId)->with('package')->first();

        if (!$order) {
            return response()->json([
                'status' => false,
                'message' => 'Order not found'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => [
                'id' => $order->id,
                'order_id' => $order->order_id,
                'package_id' => $order->package->id,
                'package_name' => $order->package->name,
                'amount' => $order->amount,
                'payment_status' => $order->payment_status,
                'payment_method' => $order->payment_method,
                'updated_at' => $order->updated_at
            ]
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

    /**
     * Handle recurring notification from Midtrans
     * 
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handleRecurringNotification(Request $request)
    {
        $notificationBody = json_decode($request->getContent(), true);
        
        // Verifikasi signature seperti di handleNotification
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

        // Logika untuk menangani recurring payment
        // Mirip dengan handleNotification tetapi untuk subscription/recurring

        return response()->json([
            'status' => true,
            'message' => 'Recurring notification processed'
        ]);
    }

    /**
     * Handle account notification from Midtrans
     * 
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handleAccountNotification(Request $request)
    {
        $notificationBody = json_decode($request->getContent(), true);
        
        // Verifikasi signature
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

        // Logika untuk menangani notifikasi akun pembayaran

        return response()->json([
            'status' => true,
            'message' => 'Account notification processed'
        ]);
    }
}
