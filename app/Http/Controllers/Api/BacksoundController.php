<?php

namespace App\Http\Controllers\Api;

use App\Models\Backsound;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class BacksoundController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $backsounds = Backsound::all();
        return response()->json([
            'status' => 'success',
            'data' => $backsounds
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        if ($user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'artist' => 'required|string|max:255',
            'file_path' => 'nullable|string',
            'thumbnail' => 'nullable|string',
            'audio_file' => 'nullable|file|mimes:mp3,wav,ogg,m4a',
            'thumbnail_file' => 'nullable|file|mimes:jpg,jpeg,png,webp'
        ]);

        if ($request->hasFile('audio_file')) {
            $validated['file_path'] = $request->file('audio_file')->store('musics', 'public');
        }
        
        if ($request->hasFile('thumbnail_file')) {
            $validated['thumbnail'] = $request->file('thumbnail_file')->store('musics/thumbnails', 'public');
        }

        $music = Backsound::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Music created successfully',
            'data' => $music
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $music = Backsound::find($id);

        if (!$music) {
            return response()->json([
                'status' => 'error',
                'message' => 'Music not found'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $music
        ], 200);
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
    public function destroy(Backsound $backsound)
    {
        $backsound->delete();
        
        return response()->json([
            'status' => 'success',
            'message' => 'Music deleted successfully'
        ], 200);
    }
}
