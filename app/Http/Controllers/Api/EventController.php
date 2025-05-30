<?php

namespace App\Http\Controllers\Api;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class EventController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $user = Auth::user();
            $events = Event::with('invitation')
                ->whereHas('invitation', function($query) use ($user) {
                    $query->where('user_id', $user->id);
                })
                ->orderBy('date')
                ->orderBy('time_start')
                ->get();

            return response()->json([
                'status' => 'success',
                'message' => 'Events retrieved successfully',
                'data' => $events
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve events',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     * Supports both single event and multiple events creation.
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        // Check if request contains multiple events
        $isMultiple = $request->has('events') && is_array($request->events);

        if ($isMultiple) {
            return $this->storeMultiple($request);
        } else {
            return $this->storeSingle($request);
        }
    }

    /**
     * Store multiple events at once.
     */
    private function storeMultiple(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'invitation_id' => 'required|exists:invitations,id',
            'events' => 'required|array|min:1|max:10',
            'events.*.name' => 'required|string|max:255',
            'events.*.venue' => 'required|string|max:255',
            'events.*.date' => 'required|date|after_or_equal:today',
            'events.*.time_start' => 'required|date_format:H:i',
            'events.*.time_end' => 'nullable|date_format:H:i|after:events.*.time_start',
            'events.*.address' => 'nullable|string',
            'events.*.maps_url' => 'nullable|url',
            'events.*.maps_embed_url' => 'nullable|url',
        ], [
            'events.*.time_end.after' => 'The end time must be after the start time.',
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

            $createdEvents = [];
            
            foreach ($validated['events'] as $eventData) {
                $event = Event::create([
                    'invitation_id' => $validated['invitation_id'],
                    'name' => $eventData['name'],
                    'venue' => $eventData['venue'],
                    'date' => $eventData['date'],
                    'time_start' => $eventData['time_start'],
                    'time_end' => $eventData['time_end'] ?? null,
                    'address' => $eventData['address'] ?? null,
                    'maps_url' => $eventData['maps_url'] ?? null,
                    'maps_embed_url' => $eventData['maps_embed_url'] ?? null,
                ]);

                $createdEvents[] = $event;
            }

            DB::commit();

            // Load relationships for response
            $events = Event::with('invitation')->whereIn('id', collect($createdEvents)->pluck('id'))->get();

            return response()->json([
                'status' => 'success',
                'message' => count($createdEvents) . ' events added successfully',
                'data' => $events,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to add events. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Store a single event.
     */
    private function storeSingle(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'invitation_id' => 'required|exists:invitations,id',
            'name' => 'required|string|max:255',
            'venue' => 'required|string|max:255',
            'date' => 'required|date|after_or_equal:today',
            'time_start' => 'required|date_format:H:i',
            'time_end' => 'nullable|date_format:H:i|after:time_start',
            'address' => 'nullable|string',
            'maps_url' => 'nullable|url',
            'maps_embed_url' => 'nullable|url',
        ], [
            'time_end.after' => 'The end time must be after the start time.',
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

            $event = Event::create([
                'invitation_id' => $validated['invitation_id'],
                'name' => $validated['name'],
                'venue' => $validated['venue'],
                'date' => $validated['date'],
                'time_start' => $validated['time_start'],
                'time_end' => $validated['time_end'] ?? null,
                'address' => $validated['address'] ?? null,
                'maps_url' => $validated['maps_url'] ?? null,
                'maps_embed_url' => $validated['maps_embed_url'] ?? null,
            ]);

            DB::commit();

            $event->load('invitation');

            return response()->json([
                'status' => 'success',
                'message' => 'Event added successfully',
                'data' => $event,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to add event. Please try again.',
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
            $event = Event::with('invitation')
                ->whereHas('invitation', function($query) use ($user) {
                    $query->where('user_id', $user->id);
                })
                ->findOrFail($id);

            return response()->json([
                'status' => 'success',
                'message' => 'Event retrieved successfully',
                'data' => $event
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Event not found',
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
            $event = Event::whereHas('invitation', function($query) use ($user) {
                $query->where('user_id', $user->id);
            })->findOrFail($id);

            $validator = Validator::make($request->all(), [
                'invitation_id' => 'sometimes|required|exists:invitations,id',
                'name' => 'sometimes|required|string|max:255',
                'venue' => 'sometimes|required|string|max:255',
                'date' => 'sometimes|required|date|after_or_equal:today',
                'time_start' => 'sometimes|required|date_format:H:i',
                'time_end' => 'nullable|date_format:H:i|after:time_start',
                'address' => 'nullable|string',
                'maps_url' => 'nullable|url',
                'maps_embed_url' => 'nullable|url',
            ], [
                'time_end.after' => 'The end time must be after the start time.',
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

            $event->update($validated);

            DB::commit();

            $event->load('invitation');

            return response()->json([
                'status' => 'success',
                'message' => 'Event updated successfully',
                'data' => $event,
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update event. Please try again.',
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
            $event = Event::whereHas('invitation', function($query) use ($user) {
                $query->where('user_id', $user->id);
            })->findOrFail($id);

            DB::beginTransaction();

            $event->delete();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Event deleted successfully',
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete event. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get events by invitation ID.
     */
    public function getByInvitation($invitationId)
    {
        try {
            $user = Auth::user();
            
            // Verify invitation belongs to user
            $invitation = \App\Models\Invitation::where('id', $invitationId)
                ->where('user_id', $user->id)
                ->firstOrFail();

            $events = Event::where('invitation_id', $invitationId)
                ->orderBy('date')
                ->orderBy('time_start')
                ->get();

            return response()->json([
                'status' => 'success',
                'message' => 'Events retrieved successfully',
                'data' => $events
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve events',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Bulk delete events by invitation ID.
     */
    public function bulkDelete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'event_ids' => 'required|array|min:1',
            'event_ids.*' => 'required|integer|exists:events,id',
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

            $deletedCount = Event::whereIn('id', $request->event_ids)
                ->whereHas('invitation', function($query) use ($user) {
                    $query->where('user_id', $user->id);
                })
                ->delete();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => $deletedCount . ' events deleted successfully',
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete events. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}
