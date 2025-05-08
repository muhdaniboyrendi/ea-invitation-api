<?php

namespace App\Http\Controllers\Api;

use App\Models\Theme;
use Illuminate\Http\Request;
use App\Models\ThemeCategory;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ThemeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $themes = Theme::with('category')->get();
        
        return response()->json([
            'status' => true,
            'data' => $themes
        ], 200);
    }

    public function getCategories()
    {
        $categories = ThemeCategory::all();
        
        return response()->json([
            'status' => true,
            'data' => $categories
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'thumbnail' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'link' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Upload thumbnail jika ada
        $thumbnailPath = null;
        if ($request->hasFile('thumbnail')) {
            $file = $request->file('thumbnail');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $thumbnailPath = $file->storeAs('themes/thumbnails', $fileName, 'public');
        }

        // Buat tema baru
        $theme = Theme::create([
            'name' => $request->name,
            'category_id' => $request->category_id,
            'link' => $request->link,
            'thumbnail' => $thumbnailPath
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Theme has been successfully added',
            'data' => $theme
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(String $id)
    {
        $theme = Theme::with('category')->find($id);
        
        if (!$theme) {
            return response()->json([
                'status' => false,
                'message' => 'Theme not found'
            ], 404);
        }
        
        return response()->json([
            'status' => true,
            'data' => $theme
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $theme = Theme::find($id);
        
        if (!$theme) {
            return response()->json([
                'status' => false,
                'message' => 'Theme not found'
            ], 404);
        }
        
        // Validasi input
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'thumbnail' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'link' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Upload thumbnail baru jika ada
        if ($request->hasFile('thumbnail')) {
            // Hapus thumbnail lama jika ada
            if ($theme->thumbnail) {
                Storage::disk('public')->delete($theme->thumbnail);
            }
            
            $file = $request->file('thumbnail');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $thumbnailPath = $file->storeAs('themes/thumbnails', $fileName, 'public');
            
            $theme->thumbnail = $thumbnailPath;
        }

        // Update tema
        $theme->name = $request->name;
        $theme->category_id = $request->category_id;
        $theme->link = $request->link;
        $theme->save();

        return response()->json([
            'status' => true,
            'message' => 'Theme has been successfully updated',
            'data' => $theme
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $theme = Theme::find($id);
        
        if (!$theme) {
            return response()->json([
                'status' => false,
                'message' => 'Theme not found'
            ], 404);
        }
        
        // Hapus thumbnail jika ada
        if ($theme->thumbnail) {
            Storage::disk('public')->delete($theme->thumbnail);
        }
        
        $theme->delete();
        
        return response()->json([
            'status' => true,
            'message' => 'Theme has been successfully deleted'
        ]);
    }
}
