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
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'invitation_id' => 'required|exists:invitations,id',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();
        $perPage = $validated['per_page'] ?? 12;
        
        $galleries = Gallery::where('invitation_id', $validated['invitation_id'])
                           ->orderBy('created_at', 'desc')
                           ->paginate($perPage);
        
        return response()->json([
            'status' => 'success',
            'data' => $galleries,
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
            'images' => 'required|array|min:1|max:20',
            'images.*' => 'required|file|mimes:jpg,jpeg,png,webp|max:5120', // 5MB max per image
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

            $galleries = [];
            $uploadedFiles = [];

            foreach ($validated['images'] as $index => $imageFile) {
                $imageExtension = $imageFile->getClientOriginalExtension();
                $imageUuid = Str::uuid();
                $imageFileName = $imageUuid . '.' . $imageExtension;
                
                $imagePath = $imageFile->storeAs('gallery/images', $imageFileName, 'public');
                $uploadedFiles[] = $imagePath;

                $galleries[] = Gallery::create([
                    'invitation_id' => $validated['invitation_id'],
                    'image_path' => $imagePath,
                ]);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Gallery images uploaded successfully',
                'data' => $galleries,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            // Clean up uploaded files if transaction fails
            foreach ($uploadedFiles as $filePath) {
                if (Storage::disk('public')->exists($filePath)) {
                    Storage::disk('public')->delete($filePath);
                }
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to upload gallery images. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $gallery = Gallery::with('invitation')->find($id);
        
        if (!$gallery) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gallery image not found'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $gallery,
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $user = Auth::user();

        $gallery = Gallery::find($id);
        
        if (!$gallery) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gallery image not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'image' => 'required|file|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $oldImagePath = $gallery->image_path;

            // Upload new image
            $imageFile = $request->file('image');
            $imageExtension = $imageFile->getClientOriginalExtension();
            $imageUuid = Str::uuid();
            $imageFileName = $imageUuid . '.' . $imageExtension;
            
            $newImagePath = $imageFile->storeAs('gallery/images', $imageFileName, 'public');

            // Update gallery record
            $gallery->update([
                'image_path' => $newImagePath,
            ]);

            // Delete old image
            if ($oldImagePath && Storage::disk('public')->exists($oldImagePath)) {
                Storage::disk('public')->delete($oldImagePath);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Gallery image updated successfully',
                'data' => $gallery,
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            // Clean up new uploaded file if transaction fails
            if (isset($newImagePath) && Storage::disk('public')->exists($newImagePath)) {
                Storage::disk('public')->delete($newImagePath);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update gallery image. Please try again.',
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
        
        $gallery = Gallery::find($id);
        
        if (!$gallery) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gallery image not found'
            ], 404);
        }

        try {
            DB::beginTransaction();

            $imagePath = $gallery->image_path;

            $gallery->delete();

            // Delete image file
            if ($imagePath && Storage::disk('public')->exists($imagePath)) {
                Storage::disk('public')->delete($imagePath);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Gallery image deleted successfully',
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete gallery image. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Bulk delete gallery images
     */
    public function bulkDestroy(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'gallery_ids' => 'required|array|min:1',
            'gallery_ids.*' => 'required|exists:galleries,id',
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

            $galleries = Gallery::whereIn('id', $validated['gallery_ids'])->get();
            $imagePaths = $galleries->pluck('image_path')->toArray();

            // Delete records
            Gallery::whereIn('id', $validated['gallery_ids'])->delete();

            // Delete image files
            foreach ($imagePaths as $imagePath) {
                if ($imagePath && Storage::disk('public')->exists($imagePath)) {
                    Storage::disk('public')->delete($imagePath);
                }
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Gallery images deleted successfully',
                'deleted_count' => count($validated['gallery_ids']),
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete gallery images. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Reorder gallery images
     */
    public function reorder(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'invitation_id' => 'required|exists:invitations,id',
            'gallery_order' => 'required|array|min:1',
            'gallery_order.*.id' => 'required|exists:galleries,id',
            'gallery_order.*.order' => 'required|integer|min:1',
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

            foreach ($validated['gallery_order'] as $orderData) {
                Gallery::where('id', $orderData['id'])
                       ->where('invitation_id', $validated['invitation_id'])
                       ->update(['order' => $orderData['order']]);
            }

            DB::commit();

            $reorderedGalleries = Gallery::where('invitation_id', $validated['invitation_id'])
                                        ->orderBy('order', 'asc')
                                        ->get();

            return response()->json([
                'status' => 'success',
                'message' => 'Gallery images reordered successfully',
                'data' => $reorderedGalleries,
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to reorder gallery images. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}
