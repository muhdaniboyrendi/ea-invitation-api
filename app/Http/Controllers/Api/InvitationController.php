<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use App\Models\Invitation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            'order_id' => 'required|exists:orders,id',
            'theme_id' => 'required|exists:themes,id',
            'groom' => 'required|string|max:50',
            'bride' => 'required|string|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();

        $order = Order::with('package')->find($request->order_id);
        if (!$order) {
            return response()->json([
                'status' => 'error',
                'message' => 'Order not found'
            ], 404);
        }

        if ($order->user_id !== $user->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Forbidden: You do not have permission to create an invitation for this order'
            ], 403);
        }

        if (!$order->package) {
            return response()->json([
                'status' => 'error',
                'message' => 'Package not found for this order'
            ], 400);
        }

        $existingInvitation = Invitation::where('order_id', $request->order_id)->first();

        if ($existingInvitation) {
            return response()->json([
                'status' => 'error',
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
        try {
            DB::beginTransaction();

            $invitation = Invitation::create([
                'user_id' => $user->id,
                'order_id' => $request->order_id,
                'theme_id' => $request->theme_id,
                'status' => 'draft',
                'expiry_date' => $expiryDate,
                'groom' => $request->groom,
                'bride' => $request->bride,
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Invitation created successfully',
                'data' => $invitation->load(['user', 'order', 'theme'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create invitation',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            DB::beginTransaction();

            $invitation = Invitation::with(['order', 'theme'])->find($id);

            if (!$invitation) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invitation not found'
                ], 404);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Invitation retrieved successfully',
                'data' => $invitation
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve invitation',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validator = Validator::make(array_merge($request->all(), ['id' => $id]), [
            'id' => 'required|exists:invitations,id',
            'theme_id' => 'sometimes|exists:themes,id',
            'groom' => 'sometimes|string|max:50',
            'bride' => 'sometimes|string|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();
        $user = Auth::user();

        try {
            DB::beginTransaction();

            $invitation = Invitation::with(['order.package'])->find($validated['id']);

            if (!$invitation) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invitation not found'
                ], 404);
            }

            if ($invitation->user_id !== $user->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Forbidden: You do not have permission to update this invitation'
                ], 403);
            }

            // Check if invitation is expired
            if ($invitation->expiry_date && now()->gt($invitation->expiry_date)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot update expired invitation'
                ], 400);
            }

            // Prepare data for update (only include fields that are present in request)
            $updateData = [];
            
            if (isset($validated['theme_id'])) {
                $updateData['theme_id'] = $validated['theme_id'];
            }
            
            if (isset($validated['groom'])) {
                $updateData['groom'] = $validated['groom'];
            }
            
            if (isset($validated['bride'])) {
                $updateData['bride'] = $validated['bride'];
            }

            // Add updated timestamp
            $updateData['updated_at'] = now();

            $invitation->update($updateData);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Invitation updated successfully',
                'data' => $invitation->fresh()->load(['user', 'order', 'theme'])
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update invitation',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
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
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|exists:orders,order_id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        $order = Order::where('order_id', $request->order_id)->first();

        if (!$order) {
            return response()->json([
                'status' => 'error',
                'message' => 'Order not found'
            ], 404);
        }

        if ($user->id !== $order->user_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Forbidden: You do not have permission to check this order'
            ], 403);
        }

        try {
            $invitation = Invitation::where('order_id', $order->id)->with('order')->first();

            if (!$invitation) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invitation not found for this order',
                ], 404);
            }
            
            return response()->json([
                'status' => 'success',
                'message' => 'Invitation found',
                'data' => [
                    'id' => $invitation->id,
                    'order_id' => $invitation->order_id,
                    'groom' => $invitation->groom,
                    'bride' => $invitation->bride,
                    'package_id' => $invitation->order->package_id,
                    'created_at' => $invitation->created_at,
                    'updated_at' => $invitation->updated_at
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error checking invitation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display all invitations for the authenticated user.
     */
    public function showInvitationByUser()
    {
        try {
            $user = Auth::user();
            
            $invitations = Invitation::with(['guests'])
                ->where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'status' => 'success',
                'message' => 'User invitations retrieved successfully',
                'data' => $invitations,
                'count' => $invitations->count()
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve user invitations',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function completeInvitation($id)
    {
        try {
            $invitation = Invitation::findOrFail($id);
            $invitation->status = 'published';
            $invitation->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Invitation published successfully',
                'data' => $invitation
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update invitation status',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}
