<?php

namespace App\Http\Controllers\Api;

use App\Models\Comment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class CommentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'invitation_id' => 'required|exists:invitations,id',
            'per_page' => 'nullable|integer|min:1|max:100',
            'sort' => 'nullable|in:asc,desc',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();
        $perPage = $validated['per_page'] ?? 10;
        $sort = $validated['sort'] ?? 'desc';
        
        $comments = Comment::where('invitation_id', $validated['invitation_id'])
                          ->orderBy('created_at', $sort)
                          ->paginate($perPage);
        
        return response()->json([
            'status' => 'success',
            'data' => $comments,
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'invitation_id' => 'required|exists:invitations,id',
            'name' => 'required|string|max:100',
            'message' => 'required|string|max:1000',
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

            $comment = Comment::create([
                'invitation_id' => $validated['invitation_id'],
                'name' => $validated['name'],
                'message' => $validated['message'],
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Comment added successfully',
                'data' => $comment,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to add comment. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }



    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $comment = Comment::with('invitation')->find($id);
        
        if (!$comment) {
            return response()->json([
                'status' => 'error',
                'message' => 'Comment not found'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $comment,
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $user = Auth::user();

        $comment = Comment::find($id);
        
        if (!$comment) {
            return response()->json([
                'status' => 'error',
                'message' => 'Comment not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'message' => 'required|string|max:1000',
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

            $comment->update([
                'name' => $validated['name'],
                'message' => $validated['message'],
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Comment updated successfully',
                'data' => $comment,
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update comment. Please try again.',
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
        
        $comment = Comment::find($id);
        
        if (!$comment) {
            return response()->json([
                'status' => 'error',
                'message' => 'Comment not found'
            ], 404);
        }

        try {
            DB::beginTransaction();

            $comment->delete();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Comment deleted successfully',
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete comment. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get comments stats for invitation
     */
    public function stats(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'invitation_id' => 'required|exists:invitations,id',
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
            $totalComments = Comment::where('invitation_id', $validated['invitation_id'])->count();
            $recentComments = Comment::where('invitation_id', $validated['invitation_id'])
                                   ->orderBy('created_at', 'desc')
                                   ->limit(5)
                                   ->get();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'total_comments' => $totalComments,
                    'recent_comments' => $recentComments,
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get comment stats. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Moderate comment (for admin use)
     */
    public function moderate(Request $request, $id)
    {
        $user = Auth::user();

        $comment = Comment::find($id);
        
        if (!$comment) {
            return response()->json([
                'status' => 'error',
                'message' => 'Comment not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'action' => 'required|in:approve,reject,hide',
            'reason' => 'nullable|string|max:255',
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

            // You can add moderation fields to your schema if needed
            // For now, we'll just log the moderation action
            $comment->update([
                'moderated_at' => now(),
                'moderated_by' => $user->id,
                'moderation_status' => $validated['action'],
                'moderation_reason' => $validated['reason'] ?? null,
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Comment moderated successfully',
                'data' => $comment,
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to moderate comment. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}
