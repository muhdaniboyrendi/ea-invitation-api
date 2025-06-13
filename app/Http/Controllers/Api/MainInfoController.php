<?php

namespace App\Http\Controllers\Api;

use App\Models\MainInfo;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class MainInfoController extends Controller
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
            'invitation_id' => 'required|exists:invitations,id',
            'backsound_id' => 'nullable|exists:backsounds,id',
            'main_photo' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:2048',
            'wedding_date' => 'required|date|after_or_equal:today',
            'wedding_time' => 'required|date_format:H:i',
            'time_zone' => 'required|in:WIB,WITA,WIT',
            'custom_backsound' => 'nullable|file|mimes:mp3,wav,ogg|max:10240',
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

            $existingMainInfo = MainInfo::where('invitation_id', $validated['invitation_id'])->first();
            $isUpdate = $existingMainInfo !== null;

            $oldMainPhoto = $isUpdate ? $existingMainInfo->main_photo : null;
            $oldCustomBacksound = $isUpdate ? $existingMainInfo->custom_backsound : null;

            if ($request->hasFile('main_photo')) {
                $photoFile = $request->file('main_photo');
                $photoExtension = $photoFile->getClientOriginalExtension();
                $photoUuid = Str::uuid();
                $photoFileName = $photoUuid . '.' . $photoExtension;
                
                $validated['main_photo'] = $photoFile->storeAs('main/photos', $photoFileName, 'public');
            } elseif ($isUpdate) {
                $validated['main_photo'] = $oldMainPhoto;
            }

            if ($request->hasFile('custom_backsound')) {
                $backsoundFile = $request->file('custom_backsound');
                $backsoundExtension = $backsoundFile->getClientOriginalExtension();
                $backsoundUuid = Str::uuid();
                $backsoundFileName = $backsoundUuid . '.' . $backsoundExtension;
                
                $validated['custom_backsound'] = $backsoundFile->storeAs('main/backsounds', $backsoundFileName, 'public');
            } elseif ($isUpdate) {
                $validated['custom_backsound'] = $oldCustomBacksound;
            }

            if ($isUpdate) {
                $existingMainInfo->update([
                    'backsound_id' => $validated['backsound_id'] ?? null,
                    'main_photo' => $validated['main_photo'] ?? null,
                    'wedding_date' => $validated['wedding_date'],
                    'wedding_time' => $validated['wedding_time'],
                    'time_zone' => $validated['time_zone'],
                    'custom_backsound' => $validated['custom_backsound'] ?? null,
                ]);

                $mainInfo = $existingMainInfo;

                if ($request->hasFile('main_photo') && $oldMainPhoto && Storage::disk('public')->exists($oldMainPhoto)) {
                    Storage::disk('public')->delete($oldMainPhoto);
                }
                if ($request->hasFile('custom_backsound') && $oldCustomBacksound && Storage::disk('public')->exists($oldCustomBacksound)) {
                    Storage::disk('public')->delete($oldCustomBacksound);
                }

            } else {
                $mainInfo = MainInfo::create([
                    'invitation_id' => $validated['invitation_id'],
                    'backsound_id' => $validated['backsound_id'] ?? null,
                    'main_photo' => $validated['main_photo'] ?? null,
                    'wedding_date' => $validated['wedding_date'],
                    'wedding_time' => $validated['wedding_time'],
                    'time_zone' => $validated['time_zone'],
                    'custom_backsound' => $validated['custom_backsound'] ?? null,
                ]);
            }

            DB::commit();

            $mainInfo->load(['invitation', 'backsound']);

            return response()->json([
                'status' => 'success',
                'message' => $isUpdate ? 'Main info updated successfully' : 'Main info created successfully',
                'data' => $mainInfo,
            ], $isUpdate ? 200 : 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            if (isset($validated['main_photo']) && Storage::disk('public')->exists($validated['main_photo'])) {
                Storage::disk('public')->delete($validated['main_photo']);
            }
            if (isset($validated['custom_backsound']) && Storage::disk('public')->exists($validated['custom_backsound'])) {
                Storage::disk('public')->delete($validated['custom_backsound']);
            }

            return response()->json([
                'status' => 'error',
                'message' => $isUpdate ? 'Failed to update main info. Please try again.' : 'Failed to create main info. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($invitationId)
    {
        try {
            $mainInfo = MainInfo::with(['backsound'])
                ->where('invitation_id', $invitationId)
                ->first();

            return response()->json([
                'status' => 'success',
                'message' => 'Main info retrieved successfully',
                'data' => $mainInfo
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Main info not found',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 404);
        }
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
            $mainInfo = MainInfo::whereHas('invitation', function($query) use ($user) {
                $query->where('user_id', $user->id);
            })->findOrFail($id);

            DB::beginTransaction();

            $mainPhoto = $mainInfo->main_photo;
            $customBacksound = $mainInfo->custom_backsound;

            $mainInfo->delete();

            if ($mainPhoto && Storage::disk('public')->exists($mainPhoto)) {
                Storage::disk('public')->delete($mainPhoto);
            }
            if ($customBacksound && Storage::disk('public')->exists($customBacksound)) {
                Storage::disk('public')->delete($customBacksound);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Main info deleted successfully',
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete main info. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Add or update photo for main info
     */
    public function addOrUpdatePhoto(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'invitation_id' => 'required|exists:invitations,id',
            'main_photo' => 'required|file|mimes:jpg,jpeg,png,webp|max:2048',
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

            $existingMainInfo = MainInfo::where('invitation_id', $validated['invitation_id'])->first();
            
            if (!$existingMainInfo) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Main info not found for this invitation'
                ], 404);
            }

            $oldMainPhoto = $existingMainInfo->main_photo;

            $photoFile = $request->file('main_photo');
            $photoExtension = $photoFile->getClientOriginalExtension();
            $photoUuid = Str::uuid();
            $photoFileName = $photoUuid . '.' . $photoExtension;
            
            $newPhotoPath = $photoFile->storeAs('main/photos', $photoFileName, 'public');

            $existingMainInfo->update([
                'main_photo' => $newPhotoPath
            ]);

            if ($oldMainPhoto && Storage::disk('public')->exists($oldMainPhoto)) {
                Storage::disk('public')->delete($oldMainPhoto);
            }

            DB::commit();

            $existingMainInfo->load(['invitation', 'backsound']);

            return response()->json([
                'status' => 'success',
                'message' => 'Main photo updated successfully',
                'data' => $existingMainInfo,
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            
            if (isset($newPhotoPath) && Storage::disk('public')->exists($newPhotoPath)) {
                Storage::disk('public')->delete($newPhotoPath);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update main photo. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get photo for main info
     */
    public function getPhoto($invitationId)
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

        try {
            $mainInfo = MainInfo::where('invitation_id', $invitationId)->first();
            
            if (!$mainInfo) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Main info not found for this invitation'
                ], 404);
            }

            if (!$mainInfo->main_photo) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No photo found for this main info'
                ], 404);
            }

            if (!Storage::disk('public')->exists($mainInfo->main_photo)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Photo file not found in storage'
                ], 404);
            }


            return response()->json([
                'status' => 'success',
                'message' => 'Main photo retrieved successfully',
                'data' => [
                    'photo_url' => $mainInfo->main_photo_url,
                    'photo_path' => $mainInfo->main_photo,
                    'invitation_id' => $invitationId
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve main photo. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}
