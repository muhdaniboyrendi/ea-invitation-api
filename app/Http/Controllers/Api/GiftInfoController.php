<?php

namespace App\Http\Controllers\Api;

use App\Models\GiftInfo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class GiftInfoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        
        $giftInfos = GiftInfo::where('invitation_id', $request->invitation_id)
                            ->get();
        
        return response()->json([
            'status' => 'success',
            'data' => $giftInfos,
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'invitation_id' => 'required|exists:invitations,id',
            'gift_infos' => 'required|array|min:1',
            'gift_infos.*.bank_name' => 'required|string|max:100',
            'gift_infos.*.account_number' => 'required|string|max:50',
            'gift_infos.*.account_holder' => 'required|string|max:100',
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

            $giftInfos = [];
            foreach ($validated['gift_infos'] as $giftInfo) {
                $giftInfos[] = GiftInfo::create([
                    'invitation_id' => $validated['invitation_id'],
                    'bank_name' => $giftInfo['bank_name'],
                    'account_number' => $giftInfo['account_number'],
                    'account_holder' => $giftInfo['account_holder'],
                ]);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Gift info added successfully',
                'data' => $giftInfos,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to add gift info. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $user = Auth::user();
        
        $giftInfo = GiftInfo::find($id);
        
        if (!$giftInfo) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gift info not found'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $giftInfo,
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $user = Auth::user();

        $giftInfo = GiftInfo::find($id);
        
        if (!$giftInfo) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gift info not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'bank_name' => 'required|string|max:100',
            'account_number' => 'required|string|max:50',
            'account_holder' => 'required|string|max:100',
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

            $giftInfo->update([
                'bank_name' => $validated['bank_name'],
                'account_number' => $validated['account_number'],
                'account_holder' => $validated['account_holder'],
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Gift info updated successfully',
                'data' => $giftInfo,
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update gift info. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $user = Auth::user();
        
        $giftInfo = GiftInfo::find($id);
        
        if (!$giftInfo) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gift info not found'
            ], 404);
        }

        try {
            DB::beginTransaction();

            $giftInfo->delete();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Gift info deleted successfully',
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete gift info. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Bulk update gift infos for specific invitation
     */
    public function bulkUpdate(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'invitation_id' => 'required|exists:invitations,id',
            'gift_infos' => 'required|array|min:1',
            'gift_infos.*.id' => 'nullable|exists:gift_infos,id',
            'gift_infos.*.bank_name' => 'required|string|max:100',
            'gift_infos.*.account_number' => 'required|string|max:50',
            'gift_infos.*.account_holder' => 'required|string|max:100',
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

            // Delete existing gift infos for this invitation
            GiftInfo::where('invitation_id', $validated['invitation_id'])->delete();

            // Create new gift infos
            $giftInfos = [];
            foreach ($validated['gift_infos'] as $giftInfo) {
                $giftInfos[] = GiftInfo::create([
                    'invitation_id' => $validated['invitation_id'],
                    'bank_name' => $giftInfo['bank_name'],
                    'account_number' => $giftInfo['account_number'],
                    'account_holder' => $giftInfo['account_holder'],
                ]);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Gift infos updated successfully',
                'data' => $giftInfos,
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update gift infos. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}
