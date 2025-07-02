<?php

namespace App\Http\Controllers\Api;

use App\Models\Package;
use App\Models\Invitation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class PackageController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $packages = Package::all();

            return response()->json([
                'status' => 'success',
                'message' => 'Packages retrieved successfully',
                'data' => $packages->map(function ($package) {
                    return [
                        'id' => $package->id,
                        'name' => $package->name,
                        'price' => $package->price,
                        'discount' => $package->discount,
                        'final_price' => $package->final_price,
                        'features' => $package->features,
                        'created_at' => $package->created_at,
                        'updated_at' => $package->updated_at,
                    ];
                })
            ]);
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
            'price' => 'required|integer|min:0',
            'discount' => 'nullable|integer|min:0|max:100',
            'features' => 'nullable|array',
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

            $package = Package::create($validated);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Package created successfully',
                'data' => $package
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create package',
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
            $package = Package::findOrFail($id);

            if (!$package) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Package not found'
                ], 404);
            }
    
            return response()->json([
                'status' => 'success',
                'message' => 'Package retrieved successfully',
                'data' => $package
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve package',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Package $package)
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
            'price' => 'required|min:0',
            'discount' => 'nullable|integer|min:0|max:100',
            'features' => 'nullable|array',
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

            $package->update($request->all());

            DB::commit();
    
            return response()->json([
                'status' => 'success',
                'message' => 'Package updated successfully',
                'data' => $package
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update package',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }

    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Package $package)
    {
        $user = Auth::user();

        if ($user->role != 'admin') {
            return response()->json([
                'status' => 'error',
                'message' => 'Forbidden access'
            ], 403);
        }

        try {
            $package->delete();
    
            return response()->json([
                'status' => 'success',
                'message' => 'Package deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update package',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function getPackageByInvitationId(String $invitationId)
    {
        try {
            DB::beginTransaction();

            $invitation = Invitation::with('order')->find($invitationId);

            if (!$invitation) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invitation not found'
                ], 404);
            }

            $package = Package::find($invitation->order->package_id);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Package retrieved successfully',
                'data' => $package
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve theme',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}
