<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use App\Models\Invitation;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class InvitationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {   
        try {
            $validator = Validator::make($request->all(), [
                'order_id' => 'required|exists:orders,id',
                'theme_id' => 'required|exists:themes,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = Auth::user();

            $order = Order::with('package')->find($request->order_id);
            if (!$order) {
                return response()->json([
                    'status' => false,
                    'message' => 'Order not found'
                ], 404);
            }

            if ($order->user_id !== $user->id) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized access to order'
                ], 403);
            }

            if (!$order->package) {
                return response()->json([
                    'status' => false,
                    'message' => 'Package not found for this order'
                ], 400);
            }

            $existingInvitation = Invitation::where('order_id', $request->order_id)->first();

            if ($existingInvitation) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invitation already exists for this order'
                ], 409);
            }

            $expiryDate = match($order->package->id) {
                1 => now()->addDays(30),
                2 => now()->addDays(90),
                3 => now()->addDays(180),
                4 => now()->addDays(360),
                default => throw new \Exception('Invalid package ID: ' . $order->package->id)
            };

            $invitation = Invitation::create([
                'user_id' => $user->id,
                'order_id' => $request->order_id,
                'theme_id' => $request->theme_id,
                'status' => 'draft',
                'expiry_date' => $expiryDate,
            ]);


            return response()->json([
                'status' => true,
                'message' => 'Invitation created successfully',
                'data' => $invitation->load(['user', 'order', 'theme'])
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while creating invitation',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function checkByOrderId(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'order_id' => 'required|exists:orders,order_id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation failed',
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

            if ($user->id !== $order->user_id) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized access to order'
                ], 403);
            }

            $invitation = Invitation::find($order->id);
            
            if ($invitation) {
                return response()->json([
                    'success' => true,
                    'exists' => true,
                    'message' => 'Invitation found',
                    'data' => [
                        'id' => $invitation->id,
                        'order_id' => $invitation->order_id,
                        'created_at' => $invitation->created_at,
                        'updated_at' => $invitation->updated_at
                    ]
                ], 200);
            } else {
                return response()->json([
                    'success' => true,
                    'exists' => false,
                    'message' => 'Invitation not found',
                    'data' => null
                ], 200);
            }
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'exists' => false,
                'message' => 'Error checking invitation',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
