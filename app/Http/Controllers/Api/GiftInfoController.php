<?php

namespace App\Http\Controllers\Api;

use App\Models\GiftInfo;
use App\Models\Invitation;
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
     * Supports both single gift info and multiple gift infos creation.
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        $isMultiple = $request->has('gift_infos') && is_array($request->gift_infos);

        if ($isMultiple) {
            return $this->storeMultiple($request);
        } else {
            return $this->storeSingle($request);
        }
    }

    /**
     * Store multiple gift infos at once (with update/create logic).
     */
    private function storeMultiple(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'invitation_id' => 'required|exists:invitations,id',
            'gift_infos' => 'required|array|min:1|max:10',
            'gift_infos.*.id' => 'nullable|integer|exists:gift_infos,id',
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

            $processedGiftInfos = [];
            $createdCount = 0;
            $updatedCount = 0;
            
            foreach ($validated['gift_infos'] as $giftInfoData) {
                $giftInfoAttributes = [
                    'invitation_id' => $validated['invitation_id'],
                    'bank_name' => $giftInfoData['bank_name'],
                    'account_number' => $giftInfoData['account_number'],
                    'account_holder' => $giftInfoData['account_holder'],
                ];

                if (isset($giftInfoData['id']) && !empty($giftInfoData['id'])) {
                    $giftInfo = GiftInfo::find($giftInfoData['id']);
                    
                    if ($giftInfo) {
                        if ($giftInfo->invitation_id != $validated['invitation_id']) {
                            throw new \Exception("Gift Info ID {$giftInfoData['id']} does not belong to invitation {$validated['invitation_id']}");
                        }
                        
                        $giftInfo->update($giftInfoAttributes);
                        $processedGiftInfos[] = $giftInfo;
                        $updatedCount++;
                    } else {
                        throw new \Exception("Gift Info with ID {$giftInfoData['id']} not found");
                    }
                } else {
                    $giftInfo = GiftInfo::create($giftInfoAttributes);
                    $processedGiftInfos[] = $giftInfo;
                    $createdCount++;
                }
            }

            DB::commit();

            $giftInfoIds = collect($processedGiftInfos)->pluck('id');
            $giftInfos = GiftInfo::with('invitation')->whereIn('id', $giftInfoIds)->get();

            $messages = [];
            if ($createdCount > 0) {
                $messages[] = "{$createdCount} gift info(s) created";
            }
            if ($updatedCount > 0) {
                $messages[] = "{$updatedCount} gift info(s) updated";
            }
            $message = implode(' and ', $messages) . ' successfully';

            return response()->json([
                'status' => 'success',
                'message' => $message,
                'data' => $giftInfos,
                'summary' => [
                    'created' => $createdCount,
                    'updated' => $updatedCount,
                    'total' => count($processedGiftInfos)
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to process gift infos. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Store a single gift info (with update/create logic).
     */
    private function storeSingle(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'nullable|integer|exists:gift_infos,id',
            'invitation_id' => 'required|exists:invitations,id',
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

            $giftInfoAttributes = [
                'invitation_id' => $validated['invitation_id'],
                'bank_name' => $validated['bank_name'],
                'account_number' => $validated['account_number'],
                'account_holder' => $validated['account_holder'],
            ];

            $isUpdate = false;

            if (isset($validated['id']) && !empty($validated['id'])) {
                $giftInfo = GiftInfo::find($validated['id']);
                
                if ($giftInfo) {
                    if ($giftInfo->invitation_id != $validated['invitation_id']) {
                        throw new \Exception("Gift Info ID {$validated['id']} does not belong to invitation {$validated['invitation_id']}");
                    }
                    
                    $giftInfo->update($giftInfoAttributes);
                    $isUpdate = true;
                } else {
                    throw new \Exception("Gift Info with ID {$validated['id']} not found");
                }
            } else {
                $giftInfo = GiftInfo::create($giftInfoAttributes);
            }

            DB::commit();

            $giftInfo->load('invitation');

            return response()->json([
                'status' => 'success',
                'message' => $isUpdate ? 'Gift info updated successfully' : 'Gift info created successfully',
                'data' => $giftInfo,
                'action' => $isUpdate ? 'updated' : 'created'
            ], $isUpdate ? 200 : 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => $isUpdate ? 'Failed to update gift info. Please try again.' : 'Failed to create gift info. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $user = Auth::user();
            $event = GiftInfo::whereHas('invitation', function($query) use ($user) {
                $query->where('user_id', $user->id);
            })->findOrFail($id);

            DB::beginTransaction();

            $event->delete();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Gift deleted successfully',
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete gift. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get events by invitation ID.
     */
    public function getGiftsByInvitation($invitationId)
    {
        try {
            $invitation = Invitation::find($invitationId);
            
            if (!$invitation) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invitation not found',
                ], 404);
            }
            
            $gifts = GiftInfo::where('invitation_id', $invitationId)->get();

            return response()->json([
                'status' => 'success',
                'message' => 'Gifts retrieved successfully',
                'data' => $gifts,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve gifts',
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
