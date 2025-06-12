<?php

namespace App\Http\Controllers\Api;

use App\Models\LoveStory;
use App\Models\Invitation;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class LoveStoryController extends Controller
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
     * Store multiple love stories at once (with update/create logic).
     */
    private function storeMultiple(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'invitation_id' => 'required|exists:invitations,id',
            'love_stories' => 'required|array|min:1|max:20',
            'love_stories.*.id' => 'nullable|integer|exists:love_stories,id',
            'love_stories.*.title' => 'required|string|max:255',
            'love_stories.*.date' => 'nullable|date',
            'love_stories.*.description' => 'nullable|string|max:5000',
            'love_stories.*.thumbnail' => 'nullable|file|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();
        $uploadedThumbnails = [];

        try {
            DB::beginTransaction();

            $processedLoveStories = [];
            $createdCount = 0;
            $updatedCount = 0;
            
            foreach ($validated['love_stories'] as $index => $storyData) {
                $storyAttributes = [
                    'invitation_id' => $validated['invitation_id'],
                    'title' => $storyData['title'],
                    'date' => $storyData['date'] ?? null,
                    'description' => $storyData['description'] ?? null,
                ];

                $oldThumbnail = null;
                $isUpdate = false;

                // Check if this is an update
                if (isset($storyData['id']) && !empty($storyData['id'])) {
                    $loveStory = LoveStory::find($storyData['id']);
                    
                    if (!$loveStory) {
                        throw new \Exception("Love Story with ID {$storyData['id']} not found");
                    }

                    if ($loveStory->invitation_id != $validated['invitation_id']) {
                        throw new \Exception("Love Story ID {$storyData['id']} does not belong to invitation {$validated['invitation_id']}");
                    }

                    $oldThumbnail = $loveStory->thumbnail;
                    $isUpdate = true;
                }

                // Handle thumbnail upload
                if ($request->hasFile("love_stories.{$index}.thumbnail")) {
                    $thumbnailFile = $request->file("love_stories.{$index}.thumbnail");
                    
                    if ($thumbnailFile->isValid()) {
                        $thumbnailExtension = $thumbnailFile->getClientOriginalExtension();
                        $thumbnailUuid = Str::uuid();
                        $thumbnailFileName = $thumbnailUuid . '.' . $thumbnailExtension;
                        
                        $storyAttributes['thumbnail'] = $thumbnailFile->storeAs('love_stories/thumbnails', $thumbnailFileName, 'public');
                        $uploadedThumbnails[] = $storyAttributes['thumbnail'];
                    } else {
                        throw new \Exception('Invalid thumbnail file uploaded');
                    }
                } elseif ($isUpdate) {
                    $storyAttributes['thumbnail'] = $oldThumbnail;
                } else {
                    $storyAttributes['thumbnail'] = null;
                }

                if ($isUpdate) {
                    $loveStory->update($storyAttributes);
                    $processedLoveStories[] = $loveStory;
                    $updatedCount++;

                    // Delete old thumbnail if new one was uploaded
                    if ($request->hasFile("love_stories.{$index}.thumbnail") && $oldThumbnail && Storage::disk('public')->exists($oldThumbnail)) {
                        Storage::disk('public')->delete($oldThumbnail);
                    }
                } else {
                    $loveStory = LoveStory::create($storyAttributes);
                    $processedLoveStories[] = $loveStory;
                    $createdCount++;
                }
            }

            DB::commit();

            $loveStoryIds = collect($processedLoveStories)->pluck('id');
            $loveStories = LoveStory::with('invitation')->whereIn('id', $loveStoryIds)->orderBy('date')->orderBy('created_at')->get();

            $messages = [];
            if ($createdCount > 0) {
                $messages[] = "{$createdCount} love story(s) created";
            }
            if ($updatedCount > 0) {
                $messages[] = "{$updatedCount} love story(s) updated";
            }
            $message = implode(' and ', $messages) . ' successfully';

            return response()->json([
                'status' => 'success',
                'message' => $message,
                'data' => $loveStories,
                'summary' => [
                    'created' => $createdCount,
                    'updated' => $updatedCount,
                    'total' => count($processedLoveStories)
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            // Clean up uploaded thumbnails on error
            foreach ($uploadedThumbnails as $thumbnail) {
                if (Storage::disk('public')->exists($thumbnail)) {
                    Storage::disk('public')->delete($thumbnail);
                }
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to process love stories. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Store a single love story (with update/create logic).
     */
    private function storeSingle(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'nullable|integer|exists:love_stories,id',
            'invitation_id' => 'required|exists:invitations,id',
            'title' => 'required|string|max:255',
            'date' => 'nullable|date',
            'description' => 'nullable|string|max:5000',
            'thumbnail' => 'nullable|file|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
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

            $storyAttributes = [
                'invitation_id' => $validated['invitation_id'],
                'title' => $validated['title'],
                'date' => $validated['date'] ?? null,
                'description' => $validated['description'] ?? null,
            ];

            $isUpdate = false;
            $oldThumbnail = null;

            // Check if this is an update
            if (isset($validated['id']) && !empty($validated['id'])) {
                $loveStory = LoveStory::find($validated['id']);
                
                if (!$loveStory) {
                    throw new \Exception("Love Story with ID {$validated['id']} not found");
                }

                if ($loveStory->invitation_id != $validated['invitation_id']) {
                    throw new \Exception("Love Story ID {$validated['id']} does not belong to invitation {$validated['invitation_id']}");
                }

                $oldThumbnail = $loveStory->thumbnail;
                $isUpdate = true;
            }

            // Handle thumbnail upload
            if ($request->hasFile('thumbnail')) {
                $thumbnailFile = $request->file('thumbnail');
                
                if ($thumbnailFile->isValid()) {
                    $thumbnailExtension = $thumbnailFile->getClientOriginalExtension();
                    $thumbnailUuid = Str::uuid();
                    $thumbnailFileName = $thumbnailUuid . '.' . $thumbnailExtension;
                    
                    $storyAttributes['thumbnail'] = $thumbnailFile->storeAs('love_stories/thumbnails', $thumbnailFileName, 'public');
                } else {
                    throw new \Exception('Invalid thumbnail file uploaded');
                }
            } elseif ($isUpdate) {
                $storyAttributes['thumbnail'] = $oldThumbnail;
            } else {
                $storyAttributes['thumbnail'] = null;
            }

            if ($isUpdate) {
                $loveStory->update($storyAttributes);

                // Delete old thumbnail if new one was uploaded
                if ($request->hasFile('thumbnail') && $oldThumbnail && Storage::disk('public')->exists($oldThumbnail)) {
                    Storage::disk('public')->delete($oldThumbnail);
                }
            } else {
                $loveStory = LoveStory::create($storyAttributes);
            }

            DB::commit();

            $loveStory->load('invitation');

            return response()->json([
                'status' => 'success',
                'message' => $isUpdate ? 'Love story updated successfully' : 'Love story created successfully',
                'data' => $loveStory,
                'action' => $isUpdate ? 'updated' : 'created'
            ], $isUpdate ? 200 : 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            // Clean up uploaded thumbnail on error
            if (isset($storyAttributes['thumbnail']) && Storage::disk('public')->exists($storyAttributes['thumbnail'])) {
                Storage::disk('public')->delete($storyAttributes['thumbnail']);
            }

            return response()->json([
                'status' => 'error',
                'message' => $isUpdate ? 'Failed to update love story. Please try again.' : 'Failed to create love story. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        //
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

            $thumbnailPath = $loveStory->thumbnail;

            $loveStory->delete();

            if ($thumbnailPath && Storage::disk('public')->exists($thumbnailPath)) {
                Storage::disk('public')->delete($thumbnailPath);
            }

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
    public function getStoriesByInvitation($invitationId)
    {
        try {
            $invitation = Invitation::find($invitationId);
            
            if (!$invitation) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invitation not found',
                ], 404);
            }

            $loveStories = LoveStory::where('invitation_id', $invitationId)
                        ->orderBy('date')
                        ->get();

            return response()->json([
                'status' => 'success',
                'message' => 'Love stories retrieved successfully',
                'data' => $loveStories->map(function($story) {
                    return [
                        'id' => $story->id,
                        'title' => $story->title,
                        'date' => $story->date,
                        'description' => $story->description,
                        'thumbnail_url' => $story->thumbnail_url,
                        'created_at' => $story->created_at,
                        'updated_at' => $story->updated_at,
                    ];
                }),
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

            // Get love stories to delete (for thumbnail cleanup)
            $loveStoriesToDelete = LoveStory::whereIn('id', $request->love_story_ids)
                ->whereHas('invitation', function($query) use ($user) {
                    $query->where('user_id', $user->id);
                })
                ->get();

            // Delete thumbnail files
            foreach ($loveStoriesToDelete as $loveStory) {
                if ($loveStory->thumbnail) {
                    Storage::disk('public')->delete($loveStory->thumbnail);
                }
            }

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
