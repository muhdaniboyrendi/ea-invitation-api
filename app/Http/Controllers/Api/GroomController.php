<?php

namespace App\Http\Controllers\Api;

use App\Models\GroomInfo;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class GroomController extends Controller
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
            'groom_fullname' => 'required|string|max:255',
            'groom_father' => 'required|string|max:255',
            'groom_mother' => 'required|string|max:255',
            'groom_instagram' => 'nullable|string|max:255',
            'groom_photo' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:2048',
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

            $existingGroomInfo = GroomInfo::where('invitation_id', $validated['invitation_id'])->first();
            $isUpdate = $existingGroomInfo !== null;

            $oldGroomPhoto = $isUpdate ? $existingGroomInfo->groom_photo : null;

            if ($request->hasFile('groom_photo')) {
                $photoFile = $request->file('groom_photo');
                $photoExtension = $photoFile->getClientOriginalExtension();
                $photoUuid = Str::uuid();
                $photoFileName = $photoUuid . '.' . $photoExtension;
                
                $validated['groom_photo'] = $photoFile->storeAs('groom/photos', $photoFileName, 'public');
            } elseif ($isUpdate) {
                $validated['groom_photo'] = $oldGroomPhoto;
            }

            if ($isUpdate) {
                $existingGroomInfo->update([
                    'groom_fullname' => $validated['groom_fullname'],
                    'groom_father' => $validated['groom_father'],
                    'groom_mother' => $validated['groom_mother'],
                    'groom_instagram' => $validated['groom_instagram'] ?? null,
                    'groom_photo' => $validated['groom_photo'] ?? null,
                ]);

                $groomInfo = $existingGroomInfo;

                if ($request->hasFile('groom_photo') && $oldGroomPhoto && Storage::disk('public')->exists($oldGroomPhoto)) {
                    Storage::disk('public')->delete($oldGroomPhoto);
                }

            } else {
                $groomInfo = GroomInfo::create([
                    'invitation_id' => $validated['invitation_id'],
                    'groom_fullname' => $validated['groom_fullname'],
                    'groom_father' => $validated['groom_father'],
                    'groom_mother' => $validated['groom_mother'],
                    'groom_instagram' => $validated['groom_instagram'] ?? null,
                    'groom_photo' => $validated['groom_photo'] ?? null,
                ]);
            }

            DB::commit();

            $groomInfo->load(['invitation']);

            return response()->json([
                'status' => 'success',
                'message' => $isUpdate ? 'Groom info updated successfully' : 'Groom info created successfully',
                'data' => $groomInfo,
            ], $isUpdate ? 200 : 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            if (isset($validated['groom_photo']) && Storage::disk('public')->exists($validated['groom_photo'])) {
                Storage::disk('public')->delete($validated['groom_photo']);
            }

            return response()->json([
                'status' => 'error',
                'message' => $isUpdate ? 'Failed to update groom info. Please try again.' : 'Failed to create groom info. Please try again.',
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
            $groomInfo = GroomInfo::where('invitation_id', $invitationId)->first();

            if (!$groomInfo) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Groom info not found'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Groom info retrieved successfully',
                'data' => [
                    'id' => $groomInfo->id,
                    'groom_fullname' => $groomInfo->groom_fullname,
                    'groom_father' => $groomInfo->groom_father,
                    'groom_mother' => $groomInfo->groom_mother,
                    'groom_instagram' => $groomInfo->groom_instagram,
                    'groom_photo_url' => $groomInfo->groom_photo_url,
                    'created_at' => $groomInfo->created_at,
                    'updated_at' => $groomInfo->updated_at,
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve backsound',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $user = Auth::user();

        $groomInfo = GroomInfo::find($id);

        if (!$groomInfo) {
            return response()->json([
                'status' => 'error',
                'message' => 'Groom info not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'groom_fullname' => 'required|string|max:255',
            'groom_father' => 'required|string|max:255',
            'groom_mother' => 'required|string|max:255',
            'groom_instagram' => 'nullable|string|max:255',
            'groom_photo' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();
        $oldGroomPhotoPath = $groomInfo->groom_photo;

        try {
            DB::beginTransaction();

            if ($request->hasFile('groom_photo')) {
                $PhotoFile = $request->file('groom_photo');
                $thumbnailExtension = $PhotoFile->getClientOriginalExtension();
                $thumbnailUuid = Str::uuid();
                $PhotoFileName = $thumbnailUuid . '.' . $thumbnailExtension;
                
                $validated['groom_photo'] = $PhotoFile->storeAs('groom/photos', $PhotoFileName, 'public');
            }

            $groomInfo->update($validated);

            if ($request->hasFile('groom_photo') && $oldGroomPhotoPath && Storage::disk('public')->exists($oldGroomPhotoPath)) {
                Storage::disk('public')->delete($oldGroomPhotoPath);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Backsound updated successfully',
                'data' => [
                    'id' => $groomInfo->id,
                    'groom_fullname' => $groomInfo->groom_fullname,
                    'groom_father' => $groomInfo->groom_father,
                    'groom_mother' => $groomInfo->groom_mother,
                    'groom_instagram' => $groomInfo->groom_instagram,
                    'groom_photo_url' => $groomInfo->groom_photo_url,
                    'created_at' => $groomInfo->created_at,
                    'updated_at' => $groomInfo->updated_at,
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollback();
            
            if (isset($validated['groom_photo']) && Storage::disk('public')->exists($validated['groom_photo'])) {
                Storage::disk('public')->delete($validated['groom_photo']);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update backsound. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(GroomInfo $groomInfo)
    {
        $user = Auth::user();

        if (!$groomInfo) {
            return response()->json([
                'status' => 'error',
                'message' => 'Backsound not found'
            ], 404);
        }

        try {
            DB::beginTransaction();

            $GroomPhotoPath = $groomInfo->groom_photo;

            $groomInfo->delete();

            if ($GroomPhotoPath && Storage::disk('public')->exists($GroomPhotoPath)) {
                Storage::disk('public')->delete($GroomPhotoPath);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Backsound deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete backsound. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}
