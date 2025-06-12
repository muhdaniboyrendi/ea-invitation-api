<?php

namespace App\Http\Controllers\Api;

use Carbon\Carbon;
use App\Models\Event;
use App\Models\Invitation;
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
     * Store multiple events at once (with update/create logic).
     */
    private function storeMultiple(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'invitation_id' => 'required|exists:invitations,id',
            'events' => 'required|array|min:1|max:10',
            'events.*.id' => 'nullable|integer|exists:events,id',
            'events.*.name' => 'required|string|max:255',
            'events.*.venue' => 'required|string|max:255',
            'events.*.date' => 'required|date',
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

            $processedEvents = [];
            $createdCount = 0;
            $updatedCount = 0;
            
            foreach ($validated['events'] as $eventData) {
                $eventAttributes = [
                    'invitation_id' => $validated['invitation_id'],
                    'name' => $eventData['name'],
                    'venue' => $eventData['venue'],
                    'date' => $eventData['date'],
                    'time_start' => $eventData['time_start'],
                    'time_end' => $eventData['time_end'] ?? null,
                    'address' => $eventData['address'] ?? null,
                    'maps_url' => $eventData['maps_url'] ?? null,
                ];

                // Handle maps_embed_url - auto-generate if not provided but maps_url exists
                if (!empty($eventData['maps_embed_url'])) {
                    $eventAttributes['maps_embed_url'] = $eventData['maps_embed_url'];
                } elseif (!empty($eventData['maps_url'])) {
                    $embedUrl = Event::convertToEmbedUrl($eventData['maps_url']);
                    if ($embedUrl) {
                        $eventAttributes['maps_embed_url'] = $embedUrl;
                    }
                }

                // Check if event has ID (update) or not (create)
                if (isset($eventData['id']) && !empty($eventData['id'])) {
                    // Update existing event
                    $event = Event::find($eventData['id']);
                    
                    if ($event) {
                        // Verify that the event belongs to the same invitation
                        if ($event->invitation_id != $validated['invitation_id']) {
                            throw new \Exception("Event ID {$eventData['id']} does not belong to invitation {$validated['invitation_id']}");
                        }
                        
                        $event->update($eventAttributes);
                        $processedEvents[] = $event;
                        $updatedCount++;
                    } else {
                        throw new \Exception("Event with ID {$eventData['id']} not found");
                    }
                } else {
                    // Create new event
                    $event = Event::create($eventAttributes);
                    $processedEvents[] = $event;
                    $createdCount++;
                }
            }

            DB::commit();

            // Load relationships for response
            $eventIds = collect($processedEvents)->pluck('id');
            $events = Event::with('invitation')->whereIn('id', $eventIds)->get();

            // Generate response message
            $messages = [];
            if ($createdCount > 0) {
                $messages[] = "{$createdCount} event(s) created";
            }
            if ($updatedCount > 0) {
                $messages[] = "{$updatedCount} event(s) updated";
            }
            $message = implode(' and ', $messages) . ' successfully';

            return response()->json([
                'status' => 'success',
                'message' => $message,
                'data' => $events,
                'summary' => [
                    'created' => $createdCount,
                    'updated' => $updatedCount,
                    'total' => count($processedEvents)
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to process events. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Store a single event (with update/create logic).
     */
    private function storeSingle(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'nullable|integer|exists:events,id',
            'invitation_id' => 'required|exists:invitations,id',
            'name' => 'required|string|max:255',
            'venue' => 'required|string|max:255',
            'date' => 'required|date',
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

            $eventAttributes = [
                'invitation_id' => $validated['invitation_id'],
                'name' => $validated['name'],
                'venue' => $validated['venue'],
                'date' => Carbon::parse($validated['date'])->toDateString(),
                'time_start' => $validated['time_start'],
                'time_end' => $validated['time_end'] ?? null,
                'address' => $validated['address'] ?? null,
                'maps_url' => $validated['maps_url'] ?? null,
            ];

            // Handle maps_embed_url - auto-generate if not provided but maps_url exists
            if (!empty($validated['maps_embed_url'])) {
                $eventAttributes['maps_embed_url'] = $validated['maps_embed_url'];
            } elseif (!empty($validated['maps_url'])) {
                $embedUrl = Event::convertToEmbedUrl($validated['maps_url']);
                if ($embedUrl) {
                    $eventAttributes['maps_embed_url'] = $embedUrl;
                }
            }

            $isUpdate = false;

            // Check if event has ID (update) or not (create)
            if (isset($validated['id']) && !empty($validated['id'])) {
                // Update existing event
                $event = Event::find($validated['id']);
                
                if ($event) {
                    // Verify that the event belongs to the same invitation
                    if ($event->invitation_id != $validated['invitation_id']) {
                        throw new \Exception("Event ID {$validated['id']} does not belong to invitation {$validated['invitation_id']}");
                    }
                    
                    $event->update($eventAttributes);
                    $isUpdate = true;
                } else {
                    throw new \Exception("Event with ID {$validated['id']} not found");
                }
            } else {
                // Create new event
                $event = Event::create($eventAttributes);
            }

            DB::commit();

            $event->load('invitation');

            return response()->json([
                'status' => 'success',
                'message' => $isUpdate ? 'Event updated successfully' : 'Event created successfully',
                'data' => $event,
                'action' => $isUpdate ? 'updated' : 'created'
            ], $isUpdate ? 200 : 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => $isUpdate ? 'Failed to update event. Please try again.' : 'Failed to create event. Please try again.',
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
    public function getEventsByInvitation($invitationId)
    {
        try {
            $invitation = Invitation::find($invitationId);
            
            if (!$invitation) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invitation not found',
                ], 404);
            }

            $events = Event::where('invitation_id', $invitationId)
                        ->orderBy('date', 'asc')
                        ->orderBy('time_start', 'asc')
                        ->get();

            return response()->json([
                'status' => 'success',
                'message' => 'Events retrieved successfully',
                'data' => $events,
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

    /**
     * Convert Google Maps URL to embed URL.
     */
    public function convertMapsUrl(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'maps_url' => 'required|url',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $embedUrl = Event::convertToEmbedUrl($request->maps_url);
            
            if ($embedUrl) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'URL converted successfully',
                    'data' => [
                        'original_url' => $request->maps_url,
                        'embed_url' => $embedUrl
                    ]
                ], 200);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unable to convert the provided Google Maps URL',
                ], 400);
            }

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to convert URL',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}
