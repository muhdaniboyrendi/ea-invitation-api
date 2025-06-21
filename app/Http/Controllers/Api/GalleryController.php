<?php

namespace App\Http\Controllers\Api;

use App\Models\Gallery;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class GalleryController extends Controller
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
            'photos' => 'required|array|min:1',
            'photos.*' => 'required|file|mimes:jpg,jpeg,png,gif,webp|max:2048',
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

            $uploadedPhotos = [];
            $galleryItems = [];

            // Process each photo
            foreach ($validated['photos'] as $index => $photoFile) {
                if ($photoFile->isValid()) {
                    $photoExtension = $photoFile->getClientOriginalExtension();
                    $photoUuid = Str::uuid();
                    $photoFileName = $photoUuid . '.' . $photoExtension;
                    
                    $imagePath = $photoFile->storeAs('gallery/photos', $photoFileName, 'public');
                    $uploadedPhotos[] = $imagePath;

                    // Create gallery item
                    $galleryItem = Gallery::create([
                        'invitation_id' => $validated['invitation_id'],
                        'image_path' => $imagePath,
                    ]);

                    $galleryItems[] = $galleryItem;
                } else {
                    throw new \Exception("Invalid photo file uploaded at index {$index}");
                }
            }

            DB::commit();

            // Load relations and format response
            $formattedGalleryItems = collect($galleryItems)->map(function ($item) {
                return [
                    'id' => $item->id,
                    'invitation_id' => $item->invitation_id,
                    'image_url' => $item->image_url,
                    'created_at' => $item->created_at,
                    'updated_at' => $item->updated_at,
                ];
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Gallery photos uploaded successfully',
                'data' => $formattedGalleryItems,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            // Clean up uploaded files on error
            foreach ($uploadedPhotos as $imagePath) {
                if (Storage::disk('public')->exists($imagePath)) {
                    Storage::disk('public')->delete($imagePath);
                }
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to upload gallery photos. Please try again.',
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
            $galleryItems = Gallery::where('invitation_id', $invitationId)
                ->orderBy('created_at', 'desc')
                ->get();

            if ($galleryItems->isEmpty()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Gallery not found'
                ], 404);
            }

            $formattedGalleryItems = $galleryItems->map(function ($item) {
                return [
                    'id' => $item->id,
                    'invitation_id' => $item->invitation_id,
                    'image_url' => $item->image_url,
                    'created_at' => $item->created_at,
                    'updated_at' => $item->updated_at,
                ];
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Gallery retrieved successfully',
                'data' => $formattedGalleryItems
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve gallery',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
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
    public function destroy(Gallery $gallery)
    {
        $user = Auth::user();

        if (!$gallery) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gallery item not found'
            ], 404);
        }

        try {
            DB::beginTransaction();

            $imagePath = $gallery->image_path;

            $gallery->delete();

            if ($imagePath && Storage::disk('public')->exists($imagePath)) {
                Storage::disk('public')->delete($imagePath);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Gallery item deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete gallery item. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Remove all gallery items for a specific invitation.
     */
    public function destroyAll($invitationId)
    {
        try {
            DB::beginTransaction();

            $galleryItems = Gallery::where('invitation_id', $invitationId)->get();

            if ($galleryItems->isEmpty()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No gallery items found for this invitation'
                ], 404);
            }

            $imagePaths = $galleryItems->pluck('image_path')->toArray();

            // Delete all gallery items
            Gallery::where('invitation_id', $invitationId)->delete();

            // Delete all image files
            foreach ($imagePaths as $imagePath) {
                if ($imagePath && Storage::disk('public')->exists($imagePath)) {
                    Storage::disk('public')->delete($imagePath);
                }
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'All gallery items deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete gallery items. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}
