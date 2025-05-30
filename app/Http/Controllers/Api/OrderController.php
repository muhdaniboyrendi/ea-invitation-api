<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{
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
}
