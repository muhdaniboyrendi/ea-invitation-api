<?php

namespace App\Http\Controllers\Api;

use App\Models\BrideInfo;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class BrideController extends Controller
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
            'bride_fullname' => 'required|string|max:255',
            'bride_father' => 'required|string|max:255',
            'bride_mother' => 'required|string|max:255',
            'bride_instagram' => 'nullable|string|max:255',
            'bride_photo' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:2048',
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
    
            if ($request->hasFile('bride_photo')) {
                $photoFile = $request->file('bride_photo');
                $photoExtension = $photoFile->getClientOriginalExtension();
                $photoUuid = Str::uuid();
                $photoFileName = $photoUuid . '.' . $photoExtension;
                
                $validated['bride_photo'] = $photoFile->storeAs('bride/photos', $photoFileName, 'public');
            }

            $brideInfo = BrideInfo::create([
                'bride_fullname' => $validated['bride_fullname'],
                'bride_father' => $validated['bride_father'],
                'bride_mother' => $validated['bride_mother'],
                'bride_instagram' => $validated['bride_instagram'] ?? null,
                'bride_photo' => $validated['bride_photo'] ?? null,
            ]);

            DB::commit();
    
            return response()->json([
                'status' => 'success',
                'message' => 'bride info added successfully',
                'data' => $brideInfo,
            ], 201);
    
        } catch (\Exception $e) {
            DB::rollBack();
            
            if (isset($validated['bride_photo']) && Storage::disk('public')->exists($validated['bride_photo'])) {
                Storage::disk('public')->delete($validated['bride_photo']);
            }
    
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to add bride info. Please try again.',
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
            $brideInfo = BrideInfo::find($id);

            if (!$brideInfo) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'bride info not found'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'bride info retrieved successfully',
                'data' => [
                    'id' => $brideInfo->id,
                    'bride_fullname' => $brideInfo->bride_fullname,
                    'bride_father' => $brideInfo->bride_father,
                    'bride_mother' => $brideInfo->bride_mother,
                    'bride_instagram' => $brideInfo->bride_instagram,
                    'bride_photo_url' => $brideInfo->bride_photo_url,
                    'created_at' => $brideInfo->created_at,
                    'updated_at' => $brideInfo->updated_at,
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

        $brideInfo = BrideInfo::find($id);

        if (!$brideInfo) {
            return response()->json([
                'status' => 'error',
                'message' => 'bride info not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'bride_fullname' => 'required|string|max:255',
            'bride_father' => 'required|string|max:255',
            'bride_mother' => 'required|string|max:255',
            'bride_instagram' => 'nullable|string|max:255',
            'bride_photo' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();
        $oldBridePhotoPath = $brideInfo->bride_photo;

        try {
            DB::beginTransaction();

            if ($request->hasFile('bride_photo')) {
                $PhotoFile = $request->file('bride_photo');
                $thumbnailExtension = $PhotoFile->getClientOriginalExtension();
                $thumbnailUuid = Str::uuid();
                $PhotoFileName = $thumbnailUuid . '.' . $thumbnailExtension;
                
                $validated['bride_photo'] = $PhotoFile->storeAs('bride/photos', $PhotoFileName, 'public');
            }

            $brideInfo->update($validated);

            if ($request->hasFile('bride_photo') && $oldBridePhotoPath && Storage::disk('public')->exists($oldBridePhotoPath)) {
                Storage::disk('public')->delete($oldBridePhotoPath);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Backsound updated successfully',
                'data' => [
                    'id' => $brideInfo->id,
                    'bride_fullname' => $brideInfo->bride_fullname,
                    'bride_father' => $brideInfo->bride_father,
                    'bride_mother' => $brideInfo->bride_mother,
                    'bride_instagram' => $brideInfo->bride_instagram,
                    'bride_photo_url' => $brideInfo->bride_photo_url,
                    'created_at' => $brideInfo->created_at,
                    'updated_at' => $brideInfo->updated_at,
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollback();
            
            if (isset($validated['bride_photo']) && Storage::disk('public')->exists($validated['bride_photo'])) {
                Storage::disk('public')->delete($validated['bride_photo']);
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
    public function destroy(BrideInfo $brideInfo)
    {
        $user = Auth::user();

        if (!$brideInfo) {
            return response()->json([
                'status' => 'error',
                'message' => 'Backsound not found'
            ], 404);
        }

        try {
            DB::beginTransaction();

            $bridePhotoPath = $brideInfo->bride_photo;

            $brideInfo->delete();

            if ($bridePhotoPath && Storage::disk('public')->exists($bridePhotoPath)) {
                Storage::disk('public')->delete($bridePhotoPath);
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
