<?php

namespace App\Http\Controllers\Api;

use App\Models\LoveStory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class LoveStoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $user = Auth::user();
            $loveStories = LoveStory::with('invitation')
                ->whereHas('invitation', function($query) use ($user) {
                    $query->where('user_id', $user->id);
                })
                ->orderBy('date')
                ->orderBy('created_at')
                ->get();

            return response()->json([
                'status' => 'success',
                'message' => 'Love stories retrieved successfully',
                'data' => $loveStories
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve love stories',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     * Supports both single love story and multiple love stories creation.
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        // Check if request contains multiple love stories
        $isMultiple = $request->has('love_stories') && is_array($request->love_stories);

        if ($isMultiple) {
            return $this->storeMultiple($request);
        } else {
            return $this->storeSingle($request);
        }
    }

    /**
     * Store multiple love stories at once.
     */
    private function storeMultiple(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'invitation_id' => 'required|exists:invitations,id',
            'love_stories' => 'required|array|min:1|max:20',
            'love_stories.*.title' => 'required|string|max:255',
            'love_stories.*.date' => 'nullable|date',
            'love_stories.*.description' => 'nullable|string|max:5000',
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

            $createdLoveStories = [];
            
            foreach ($validated['love_stories'] as $index => $storyData) {
                $loveStory = LoveStory::create([
                    'invitation_id' => $validated['invitation_id'],
                    'title' => $storyData['title'],
                    'date' => $storyData['date'] ?? null,
                    'description' => $storyData['description'] ?? null,
                ]);

                $createdLoveStories[] = $loveStory;
            }

            DB::commit();

            // Load relationships for response
            $loveStories = LoveStory::with('invitation')->whereIn('id', collect($createdLoveStories)->pluck('id'))->orderBy('date')->orderBy('created_at')->get();

            return response()->json([
                'status' => 'success',
                'message' => count($createdLoveStories) . ' love stories added successfully',
                'data' => $loveStories,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to add love stories. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Store a single love story.
     */
    private function storeSingle(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'invitation_id' => 'required|exists:invitations,id',
            'title' => 'required|string|max:255',
            'date' => 'nullable|date',
            'description' => 'nullable|string|max:5000',
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

            $loveStory = LoveStory::create([
                'invitation_id' => $validated['invitation_id'],
                'title' => $validated['title'],
                'date' => $validated['date'] ?? null,
                'description' => $validated['description'] ?? null,
            ]);

            DB::commit();

            $loveStory->load('invitation');

            return response()->json([
                'status' => 'success',
                'message' => 'Love story added successfully',
                'data' => $loveStory,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to add love story. Please try again.',
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
            $loveStory = LoveStory::with('invitation')
                ->whereHas('invitation', function($query) use ($user) {
                    $query->where('user_id', $user->id);
                })
                ->findOrFail($id);

            return response()->json([
                'status' => 'success',
                'message' => 'Love story retrieved successfully',
                'data' => $loveStory
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Love story not found',
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
            $loveStory = LoveStory::whereHas('invitation', function($query) use ($user) {
                $query->where('user_id', $user->id);
            })->findOrFail($id);

            $validator = Validator::make($request->all(), [
                'invitation_id' => 'sometimes|required|exists:invitations,id',
                'title' => 'sometimes|required|string|max:255',
                'date' => 'nullable|date',
                'description' => 'nullable|string|max:5000',
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

            $loveStory->update($validated);

            DB::commit();

            $loveStory->load('invitation');

            return response()->json([
                'status' => 'success',
                'message' => 'Love story updated successfully',
                'data' => $loveStory,
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update love story. Please try again.',
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
            $loveStory = LoveStory::whereHas('invitation', function($query) use ($user) {
                $query->where('user_id', $user->id);
            })->findOrFail($id);

            DB::beginTransaction();

            $loveStory->delete();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Love story deleted successfully',
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete love story. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get love stories by invitation ID.
     */
    public function getByInvitation($invitationId)
    {
        try {
            $user = Auth::user();
            
            // Verify invitation belongs to user
            $invitation = \App\Models\Invitation::where('id', $invitationId)
                ->where('user_id', $user->id)
                ->firstOrFail();

            $loveStories = LoveStory::where('invitation_id', $invitationId)
                ->orderBy('date')
                ->orderBy('created_at')
                ->get();

            return response()->json([
                'status' => 'success',
                'message' => 'Love stories retrieved successfully',
                'data' => $loveStories
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve love stories',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Bulk delete love stories.
     */
    public function bulkDelete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'love_story_ids' => 'required|array|min:1',
            'love_story_ids.*' => 'required|integer|exists:love_stories,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = Auth::user();
            
            DB::beginTransaction();

            $deletedCount = LoveStory::whereIn('id', $request->love_story_ids)
                ->whereHas('invitation', function($query) use ($user) {
                    $query->where('user_id', $user->id);
                })
                ->delete();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => $deletedCount . ' love stories deleted successfully',
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete love stories. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Bulk update order/sequence of love stories.
     */
    public function updateOrder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'love_stories' => 'required|array|min:1',
            'love_stories.*.id' => 'required|integer|exists:love_stories,id',
            'love_stories.*.order' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = Auth::user();
            
            DB::beginTransaction();

            foreach ($request->love_stories as $storyData) {
                LoveStory::where('id', $storyData['id'])
                    ->whereHas('invitation', function($query) use ($user) {
                        $query->where('user_id', $user->id);
                    })
                    ->update(['order' => $storyData['order']]);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Love stories order updated successfully',
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update love stories order. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get love stories timeline (chronologically ordered).
     */
    public function timeline($invitationId)
    {
        try {
            $user = Auth::user();
            
            // Verify invitation belongs to user
            $invitation = \App\Models\Invitation::where('id', $invitationId)
                ->where('user_id', $user->id)
                ->firstOrFail();

            $loveStories = LoveStory::where('invitation_id', $invitationId)
                ->whereNotNull('date')
                ->orderBy('date')
                ->get();

            // Group by year for better presentation
            $timeline = $loveStories->groupBy(function($story) {
                return $story->date ? $story->date->format('Y') : 'Unknown';
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Love stories timeline retrieved successfully',
                'data' => $timeline
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve love stories timeline',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}
