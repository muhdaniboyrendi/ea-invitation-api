<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if (Auth::user()->role != 'admin') {
            return response()->json([
                'status' => false,
                'message' => 'Forbidden access'
            ], 403);
        }
        
        $users = User::all();

        return response()->json([
            'status' => true,
            'message' => 'ok',
            'data' => $users
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $user = User::find($id);

        return response()->json([
            'status' => true,
            'message' => 'ok',
            'data' => $user
        ]);
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
    public function destroy(string $id)
    {
        //
    }

    public function setAdmin(string $id)
    {
        if (Auth::user()->role != 'admin') {
            return response()->json([
                'status' => false,
                'message' => 'Forbidden access'
            ], 403);
        }

        $user = User::find($id);
        $user->role = 'admin';
        $user->save();

        return response()->json([
            'status' => true,
            'message' => 'User successfully promoted to admin',
            'data' => $user
        ], 200);
    }
}
