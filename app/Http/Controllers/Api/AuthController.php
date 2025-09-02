<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'required|string|max:15|unique:users',
            'password' => 'required|string|min:8|confirmed',

        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => Hash::make($request->password),
            ]);

            // Create token for the user
            $token = $user->createToken('auth_token')->plainTextToken;

            // Set HTTP-only cookie
            $cookie = cookie(
                name: 'auth_token',
                value: $token,
                minutes: config('sanctum.expiration', 525600), // Default 1 year
                path: '/',
                domain: null,
                secure: app()->environment('production'),
                httpOnly: true,
                raw: false,
                sameSite: 'lax'
            );

            return response()->json([
                'status' => 'success',
                'message' => 'User registered successfully',
                'data' => $user
            ], 201)->withCookie($cookie);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Registration failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // public function register(Request $request)
    // {
    //     $validator = Validator::make($request->all(), [
    //         'name' => 'required|string|max:255',
    //         'email' => 'required|string|email|max:255|unique:users',
    //         'phone' => 'required|string|max:15|unique:users',
    //         'password' => 'required|string|min:8|confirmed',
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Validation failed',
    //             'errors' => $validator->errors()
    //         ], 422);
    //     }

    //     $user = User::create([
    //         'name' => $request->name,
    //         'email' => $request->email,
    //         'phone' => $request->phone,
    //         'password' => Hash::make($request->password),
    //     ]);

    //     $token = $user->createToken('auth_token')->plainTextToken;

    //     return response()->json([
    //         'status' => 'success',
    //         'message' => 'User registered successfully',
    //         'data' => [
    //             'user' => $user,
    //             'token' => $token
    //         ]
    //     ], 201);
    // }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid credentials'
            ], 401);
        }

        $user = Auth::user();
        
        // Revoke all existing tokens
        $user->tokens()->delete();
        
        // Create new token
        $token = $user->createToken('auth_token')->plainTextToken;

        // Set HTTP-only cookie
        $cookie = cookie(
            name: 'auth_token',
            value: $token,
            minutes: config('sanctum.expiration', 525600),
            path: '/',
            domain: null,
            secure: app()->environment('production'),
            httpOnly: true,
            raw: false,
            sameSite: 'lax'
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Login successful',
            'data' => $user
        ], 200)->withCookie($cookie);
    }

    // public function login(Request $request)
    // {
    //     $validator = Validator::make($request->all(), [
    //         'email' => 'required|string|email',
    //         'password' => 'required|string',
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Validation failed',
    //             'errors' => $validator->errors()
    //         ], 422);
    //     }

    //     if (!Auth::attempt($request->only('email', 'password'))) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Invalid login details'
    //         ], 401);
    //     }

    //     $user = User::where('email', $request->email)->firstOrFail();
        
    //     $user->tokens()->delete();
        
    //     $token = $user->createToken('auth_token')->plainTextToken;

    //     return response()->json([
    //         'status' => 'success',
    //         'message' => 'Login successful',
    //         'data' => [
    //             'user' => $user,
    //             'token' => $token
    //         ]
    //     ], 200);
    // }

    public function me(Request $request)
    {
        return response()->json([
            'status' => 'success',
            'data' => $request->user()
        ], 200);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        $cookie = cookie()->forget('auth_token');

        return response()->json([
            'status' => 'success',
            'message' => 'Logged out successfully'
        ], 200)->withCookie($cookie);
    }

    public function refresh(Request $request)
    {
        $user = $request->user();
        
        // Delete current token
        $request->user()->currentAccessToken()->delete();
        
        // Create new token
        $token = $user->createToken('auth_token')->plainTextToken;

        // Set new HTTP-only cookie
        $cookie = cookie(
            name: 'auth_token',
            value: $token,
            minutes: config('sanctum.expiration', 525600),
            path: '/',
            domain: null,
            secure: app()->environment('production'),
            httpOnly: true,
            raw: false,
            sameSite: 'lax'
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Token refreshed successfully',
            'data' => $user
        ])->withCookie($cookie);
    }
}
