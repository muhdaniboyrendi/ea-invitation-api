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
        // try {
        //     $user = Auth::user();
        //     $mainInfos = MainInfo::with(['invitation', 'backsound'])
        //         ->whereHas('invitation', function($query) use ($user) {
        //             $query->where('user_id', $user->id);
        //         })
        //         ->get();

        //     return response()->json([
        //         'status' => 'success',
        //         'message' => 'Main info retrieved successfully',
        //         'data' => $mainInfos
        //     ], 200);

        // } catch (\Exception $e) {
        //     return response()->json([
        //         'status' => 'error',
        //         'message' => 'Failed to retrieve main info',
        //         'error' => config('app.debug') ? $e->getMessage() : null
        //     ], 500);
        // }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'invitation_id' => 'required|exists:invitations,id',
            'backsound_id' => 'nullable|exists:backsounds,id',
            'main_photo' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:2048',
            'wedding_date' => 'required|date|after_or_equal:today',
            'wedding_time' => 'required|date_format:H:i',
            'time_zone' => 'required|in:WIB,WITA,WIT',
            'custom_backsound' => 'nullable|file|mimes:mp3,wav,ogg|max:10240', // 10MB max
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

            // Handle main photo upload
            if ($request->hasFile('main_photo')) {
                $photoFile = $request->file('main_photo');
                $photoExtension = $photoFile->getClientOriginalExtension();
                $photoUuid = Str::uuid();
                $photoFileName = $photoUuid . '.' . $photoExtension;
                
                $validated['main_photo'] = $photoFile->storeAs('main/photos', $photoFileName, 'public');
            }

            // Handle custom backsound upload
            if ($request->hasFile('custom_backsound')) {
                $backsoundFile = $request->file('custom_backsound');
                $backsoundExtension = $backsoundFile->getClientOriginalExtension();
                $backsoundUuid = Str::uuid();
                $backsoundFileName = $backsoundUuid . '.' . $backsoundExtension;
                
                $validated['custom_backsound'] = $backsoundFile->storeAs('main/backsounds', $backsoundFileName, 'public');
            }

            $mainInfo = MainInfo::create([
                'invitation_id' => $validated['invitation_id'],
                'backsound_id' => $validated['backsound_id'] ?? null,
                'main_photo' => $validated['main_photo'] ?? null,
                'wedding_date' => $validated['wedding_date'],
                'wedding_time' => $validated['wedding_time'],
                'time_zone' => $validated['time_zone'],
                'custom_backsound' => $validated['custom_backsound'] ?? null,
            ]);

            DB::commit();

            $mainInfo->load(['invitation', 'backsound']);

            return response()->json([
                'status' => 'success',
                'message' => 'Main info added successfully',
                'data' => $mainInfo,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            // Clean up uploaded files if error occurs
            if (isset($validated['main_photo']) && Storage::disk('public')->exists($validated['main_photo'])) {
                Storage::disk('public')->delete($validated['main_photo']);
            }
            if (isset($validated['custom_backsound']) && Storage::disk('public')->exists($validated['custom_backsound'])) {
                Storage::disk('public')->delete($validated['custom_backsound']);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to add main info. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        try {
            $user = Auth::user();
            $mainInfo = MainInfo::with(['invitation', 'backsound'])
                ->whereHas('invitation', function($query) use ($user) {
                    $query->where('user_id', $user->id);
                })
                ->findOrFail($id);

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
        $user = Auth::user();

        try {
            $mainInfo = MainInfo::whereHas('invitation', function($query) use ($user) {
                $query->where('user_id', $user->id);
            })->findOrFail($id);

            $validator = Validator::make($request->all(), [
                'invitation_id' => 'sometimes|required|exists:invitations,id',
                'backsound_id' => 'nullable|exists:backsounds,id',
                'main_photo' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:2048',
                'wedding_date' => 'sometimes|required|date|after_or_equal:today',
                'wedding_time' => 'sometimes|required|date_format:H:i',
                'time_zone' => 'sometimes|required|in:WIB,WITA,WIT',
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

            DB::beginTransaction();

            $oldMainPhoto = $mainInfo->main_photo;
            $oldCustomBacksound = $mainInfo->custom_backsound;

            // Handle main photo upload
            if ($request->hasFile('main_photo')) {
                $photoFile = $request->file('main_photo');
                $photoExtension = $photoFile->getClientOriginalExtension();
                $photoUuid = Str::uuid();
                $photoFileName = $photoUuid . '.' . $photoExtension;
                
                $validated['main_photo'] = $photoFile->storeAs('main/photos', $photoFileName, 'public');
            }

            // Handle custom backsound upload
            if ($request->hasFile('custom_backsound')) {
                $backsoundFile = $request->file('custom_backsound');
                $backsoundExtension = $backsoundFile->getClientOriginalExtension();
                $backsoundUuid = Str::uuid();
                $backsoundFileName = $backsoundUuid . '.' . $backsoundExtension;
                
                $validated['custom_backsound'] = $backsoundFile->storeAs('main/backsounds', $backsoundFileName, 'public');
            }

            $mainInfo->update($validated);

            // Delete old files after successful update
            if ($request->hasFile('main_photo') && $oldMainPhoto && Storage::disk('public')->exists($oldMainPhoto)) {
                Storage::disk('public')->delete($oldMainPhoto);
            }
            if ($request->hasFile('custom_backsound') && $oldCustomBacksound && Storage::disk('public')->exists($oldCustomBacksound)) {
                Storage::disk('public')->delete($oldCustomBacksound);
            }

            DB::commit();

            $mainInfo->load(['invitation', 'backsound']);

            return response()->json([
                'status' => 'success',
                'message' => 'Main info updated successfully',
                'data' => $mainInfo,
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            
            // Clean up uploaded files if error occurs
            if (isset($validated['main_photo']) && Storage::disk('public')->exists($validated['main_photo'])) {
                Storage::disk('public')->delete($validated['main_photo']);
            }
            if (isset($validated['custom_backsound']) && Storage::disk('public')->exists($validated['custom_backsound'])) {
                Storage::disk('public')->delete($validated['custom_backsound']);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update main info. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
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

            // Store file paths before deletion
            $mainPhoto = $mainInfo->main_photo;
            $customBacksound = $mainInfo->custom_backsound;

            $mainInfo->delete();

            // Delete associated files
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
}
