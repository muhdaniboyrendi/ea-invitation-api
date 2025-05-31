<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use App\Models\Theme;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ThemeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $themes = Theme::with('themeCategory')->get();

            return response()->json([
                'status' => true,
                'message' => 'Themes retrieved successfully',
                'data' => $themes->map(function ($theme) {
                    return [
                        'id' => $theme->id,
                        'name' => $theme->name,
                        'theme_category_id' => $theme->theme_category_id,
                        'link' => $theme->link,
                        'thumbnail' => $theme->thumbnail,
                        'thumbnail_url' => $theme->thumbnail_url,
                        'theme_category' => $theme->themeCategory,
                        'created_at' => $theme->created_at,
                        'updated_at' => $theme->updated_at,
                    ];
                })
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error fetching packages: ',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        if ($user->role != 'admin') {
            return response()->json([
                'status' => 'error',
                'message' => 'Forbidden access'
            ], 403);
        }
        
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'theme_category_id' => 'required|exists:theme_categories,id',
            'thumbnail' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'link' => 'nullable|string'
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

            $thumbnailPath = null;

            if ($request->hasFile('thumbnail')) {
                $file = $request->file('thumbnail');
                $fileName = time() . '_' . $file->getClientOriginalName();
                $thumbnailPath = $file->storeAs('themes/thumbnails', $fileName, 'public');
            }

            $theme = Theme::create([
                'name' => $request->name,
                'theme_category_id' => $request->theme_category_id,
                'link' => $request->link,
                'thumbnail' => $thumbnailPath
            ]);

            $theme->thumbnail_url = $thumbnailPath ? url('storage/' . $thumbnailPath) : null;

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Theme created successfully',
                'data' => $theme
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create theme',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(String $id)
{
    try {
        $theme = Theme::findOrFail($id);
        
        return response()->json([
            'status' => 'success',
            'data' => [
                'id' => $theme->id,
                'name' => $theme->name,
                'theme_category_id' => $theme->theme_category_id,
                'link' => $theme->link,
                'thumbnail' => $theme->thumbnail,
                'thumbnail_url' => $theme->thumbnail_url,
            ]
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Theme not found',
            'error' => config('app.debug') ? $e->getMessage() : null
        ], 404);
    }
}

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Theme $theme)
    {
        $user = Auth::user();

        if ($user->role != 'admin') {
            return response()->json([
                'status' => 'error',
                'message' => 'Forbidden access'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'theme_category_id' => 'required|exists:theme_categories,id',
            'thumbnail' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'link' => 'nullable|string'
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

            if ($request->hasFile('thumbnail')) {
                if ($theme->thumbnail) {
                    Storage::disk('public')->delete($theme->thumbnail);
                }
                
                $file = $request->file('thumbnail');
                $fileName = time() . '_' . $file->getClientOriginalName();
                $thumbnailPath = $file->storeAs('themes/thumbnails', $fileName, 'public');
                
                $theme->thumbnail = $thumbnailPath;
            }

            $theme->name = $request->name;
            $theme->theme_category_id = $request->theme_category_id;
            $theme->link = $request->link;
            $theme->save();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Theme updated successfully',
                'data' => $theme
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update theme',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(String $id)
    {
        $user = Auth::user();

        if ($user->role != 'admin') {
            return response()->json([
                'status' => 'error',
                'message' => 'Forbidden access'
            ], 403);
        }

        try {
            DB::beginTransaction();

            $theme = Theme::findOrFail($id);
            
            if ($theme->thumbnail) {
                Storage::disk('public')->delete($theme->thumbnail);
            }
            
            $theme->delete();

            DB::commit();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Theme deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete theme',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function getThemeByOrderId(Request $request)
    {
        try {
            $order = Order::where('order_id', $request->order_id)->first();
        
            if (!$order) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Order not found'
                ], 404);
            }

            if ($order->package_id === 1) {
                $themes = Theme::where('theme_category_id', 1)->with('themeCategory')->get();
            } else {
                $themes = Theme::with('themeCategory')->get();
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'order_id' => $order->id,
                    'themes' => $themes
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get themes',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}
