<?php

namespace App\Http\Controllers\Api;

use App\Models\Backsound;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class BacksoundController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $backsounds = Backsound::all();

            return response()->json([
                'status' => 'success',
                'message' => 'Backsounds retrieved successfully',
                'data' => $backsounds->map(function ($backsound) {
                    return [
                        'id' => $backsound->id,
                        'name' => $backsound->name,
                        'artist' => $backsound->artist,
                        'audio_url' => $backsound->audio_url,
                        'thumbnail_url' => $backsound->thumbnail_url,
                        'created_at' => $backsound->created_at,
                        'updated_at' => $backsound->updated_at,
                    ];
                })
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve backsounds',
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
                'message' => 'Forbidden: You do not have permission to create music.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'artist' => 'nullable|string|max:255',
            'audio' => 'required|file|mimes:mp3,wav,ogg,m4a|max:51200',
            'thumbnail' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:2048',
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
            
            if ($request->hasFile('audio')) {
                $audioFile = $request->file('audio');
                $audioExtension = $audioFile->getClientOriginalExtension();
                $audioUuid = Str::uuid();
                $audioFileName = $audioUuid . '.' . $audioExtension;
                
                $validated['audio'] = $audioFile->storeAs('musics', $audioFileName, 'public');
            }
    
            if ($request->hasFile('thumbnail')) {
                $thumbnailFile = $request->file('thumbnail');
                $thumbnailExtension = $thumbnailFile->getClientOriginalExtension();
                $thumbnailUuid = Str::uuid();
                $thumbnailFileName = $thumbnailUuid . '.' . $thumbnailExtension;
                
                $validated['thumbnail'] = $thumbnailFile->storeAs('musics/thumbnails', $thumbnailFileName, 'public');
            }

            $music = Backsound::create([
                'name' => $validated['name'],
                'artist' => $validated['artist'] ?? null,
                'audio' => $validated['audio'] ?? null,
                'thumbnail' => $validated['thumbnail'] ?? null,
            ]);

            DB::commit();
    
            return response()->json([
                'status' => 'success',
                'message' => 'Music created successfully',
                'data' => $music,
            ], 201);
    
        } catch (\Exception $e) {
            DB::rollBack();

            if (isset($validated['audio']) && Storage::disk('public')->exists($validated['audio'])) {
                Storage::disk('public')->delete($validated['audio']);
            }
            
            if (isset($validated['thumbnail']) && Storage::disk('public')->exists($validated['thumbnail'])) {
                Storage::disk('public')->delete($validated['thumbnail']);
            }
    
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create music. Please try again.',
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
            $backsound = Backsound::find($id);

            if (!$backsound) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Backsound not found'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Backsound retrieved successfully',
                'data' => [
                    'id' => $backsound->id,
                    'name' => $backsound->name,
                    'artist' => $backsound->artist,
                    'audio_url' => $backsound->audio_url,
                    'thumbnail_url' => $backsound->thumbnail_url,
                    'created_at' => $backsound->created_at,
                    'updated_at' => $backsound->updated_at,
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

        if ($user->role != 'admin') {
            return response()->json([
                'status' => 'error',
                'message' => 'Forbidden: You do not have permission to update music.'
            ], 403);
        }

        $backsound = Backsound::find($id);

        if (!$backsound) {
            return response()->json([
                'status' => 'error',
                'message' => 'Backsound not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'artist' => 'nullable|string|max:255',
            'audio' => 'sometimes|file|mimes:mp3,wav,ogg,m4a|max:51200',
            'thumbnail' => 'sometimes|file|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();
        $oldAudioPath = $backsound->audio;
        $oldThumbnailPath = $backsound->thumbnail;

        try {
            DB::beginTransaction();

            if ($request->hasFile('audio')) {
                $audioFile = $request->file('audio');
                $audioExtension = $audioFile->getClientOriginalExtension();
                $audioUuid = Str::uuid();
                $audioFileName = $audioUuid . '.' . $audioExtension;
                
                $validated['audio'] = $audioFile->storeAs('musics', $audioFileName, 'public');
            }

            if ($request->hasFile('thumbnail')) {
                $thumbnailFile = $request->file('thumbnail');
                $thumbnailExtension = $thumbnailFile->getClientOriginalExtension();
                $thumbnailUuid = Str::uuid();
                $thumbnailFileName = $thumbnailUuid . '.' . $thumbnailExtension;
                
                $validated['thumbnail'] = $thumbnailFile->storeAs('musics/thumbnails', $thumbnailFileName, 'public');
            }

            $backsound->update($validated);

            if ($request->hasFile('audio') && $oldAudioPath && Storage::disk('public')->exists($oldAudioPath)) {
                Storage::disk('public')->delete($oldAudioPath);
            }

            if ($request->hasFile('thumbnail') && $oldThumbnailPath && Storage::disk('public')->exists($oldThumbnailPath)) {
                Storage::disk('public')->delete($oldThumbnailPath);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Backsound updated successfully',
                'data' => [
                    'id' => $backsound->id,
                    'name' => $backsound->name,
                    'artist' => $backsound->artist,
                    'audio_url' => $backsound->audio_url,
                    'thumbnail_url' => $backsound->thumbnail_url,
                    'created_at' => $backsound->created_at,
                    'updated_at' => $backsound->updated_at,
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollback();
            
            if (isset($validated['audio']) && Storage::disk('public')->exists($validated['audio'])) {
                Storage::disk('public')->delete($validated['audio']);
            }
            
            if (isset($validated['thumbnail']) && Storage::disk('public')->exists($validated['thumbnail'])) {
                Storage::disk('public')->delete($validated['thumbnail']);
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
    public function destroy(Backsound $backsound)
    {
        $user = Auth::user();

        if ($user->role != 'admin') {
            return response()->json([
                'status' => 'error',
                'message' => 'Forbidden: You do not have permission to delete music.'
            ], 403);
        }

        if (!$backsound) {
            return response()->json([
                'status' => 'error',
                'message' => 'Backsound not found'
            ], 404);
        }

        try {
            DB::beginTransaction();

            $audioPath = $backsound->audio;
            $thumbnailPath = $backsound->thumbnail;

            $backsound->delete();

            if ($audioPath && Storage::disk('public')->exists($audioPath)) {
                Storage::disk('public')->delete($audioPath);
            }

            if ($thumbnailPath && Storage::disk('public')->exists($thumbnailPath)) {
                Storage::disk('public')->delete($thumbnailPath);
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
