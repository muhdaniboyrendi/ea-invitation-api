<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use App\Models\Invitation;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
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
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|exists:orders,order_id',
            'theme_id' => 'required|exists:themes,theme_id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // $order = Order::where('order_id', $request->order_id)->with('package')->first();
        $order = Order::where('order_id', $request->order_id)->first();
        $user = Auth::user();

        if ($order->package->id == 1) {
            $expiryDate = now()->addDays(30);
        } else if ($order->package->id == 2) {
            $expiryDate = now()->addDays(90);
        } else if ($order->package->id == 3) {
            $expiryDate = now()->addDays(180);
        } else if ($order->package->id == 4) {
            $expiryDate = now()->addDays(360);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'Invalid order ID'
            ], 400);
        }

        $invitation = Invitation::create([
            'user_id' => $user->id,
            'order_id' => $request->order_id,
            'expiry_date' => $expiryDate ?? null,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'ok',
            'data' => $invitation
        ], 201);
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
}
