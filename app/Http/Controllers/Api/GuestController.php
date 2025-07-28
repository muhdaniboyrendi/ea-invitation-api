<?php

namespace App\Http\Controllers\Api;

use App\Models\Guest;
use App\Models\Invitation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class GuestController extends Controller
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
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'invitation_id' => 'required|exists:invitations,id',
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'is_group' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();

        try {
            DB::beginTransaction();

            $guest = Guest::create([
                'invitation_id' => $validated['invitation_id'],
                'name' => $validated['name'],
                'phone' => $validated['phone'],
                'is_group' => $validated['is_group'] ?? false,
                'attendance_status' => $validated['is_group'] === true ? 'attending' : 'pending',
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Guest created successfully',
                'data' => $guest
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create guest',
                'error' => config('app.debug') ? $e->getMessage() : null
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
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'phone' => 'sometimes|nullable|string|max:20',
            'is_group' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();

        try {
            DB::beginTransaction();

            $guest = Guest::findOrFail($id);
            $guest->update([
                'name' => $validated['name'] ?? $guest->name,
                'phone' => $validated['phone'] ?? $guest->phone,
                'is_group' => $validated['is_group'] ?? $guest->is_group,
                'attendance_status' => $validated['is_group'] === true ? 'attending' : 'pending',
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Guest updated successfully',
                'data' => $guest
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update guest',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            DB::beginTransaction();

            $guest = Guest::findOrFail($id);
            $guest->delete();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Guest deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete guest',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function getGuestsByInvitationId($invitationId)
    {
        $validator = Validator::make(['invitation_id' => $invitationId], [
            'invitation_id' => 'required|exists:invitations,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();

        try {
            DB::beginTransaction();

            $guests = Guest::where('invitation_id', $validated['invitation_id'])->with(['invitation'])->get();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Guests retrieved successfully',
                'data' => $guests
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve guests',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function checkGuest($slug, Request $request)
    {
        try {
            $guest = $request->query('guest');
    
            $invitation = Invitation::where('slug', $slug)->first();
    
            if (!$invitation) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invitation not found'
                ], 404);
            }
    
            $guests = Guest::where('invitation_id', $invitation->id)->get();
    
            $guestChecked = $guests->where('slug', $guest)->first();
    
            return response()->json([
                'status' => 'success',
                'message' => 'Guest check successful',
                'data' => $guestChecked
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to check guest',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function updateAttendance(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'attendance_status' => 'required|in:attending,not_attending,pending',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();

        try {
            DB::beginTransaction();

            $guest = Guest::findOrFail($id);
            $guest->update([
                'attendance_status' => $validated['attendance_status'] ?? $guest->attendance_status,
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Guest updated successfully',
                'data' => $guest
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update guest',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}
