<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->stateless()->redirect();
    }

    public function handleGoogleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();

            $user = User::where('email', $googleUser->email)->first();

            if (!$user) {
                $user = User::create([
                    'name' => $googleUser->name,
                    'email' => $googleUser->email,
                    'google_id' => $googleUser->id,
                    'password' => bcrypt(rand(100000, 999999)),
                    'email_verified_at' => now(),
                ]);
            } else {
                if (empty($user->google_id)) {
                    $user->update([
                        'google_id' => $googleUser->id,
                    ]);
                }
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            // if ($request->wantsJson() || $request->ajax() || $request->expectsJson()) {
                return response()->json([
                    'status' => true,
                    'message' => 'User authenticated successfully',
                    'data' => [
                        'token' => $token,
                        'user' => $user,
                    ],
                ]);
            // }

            // $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
            // $redirectUrl = "{$frontendUrl}/auth/callback?token={$token}&user=" . urlencode(json_encode($user));
            
            // return redirect($redirectUrl);
        } catch (\Exception $e) {
            // if ($request->wantsJson() || $request->ajax() || $request->expectsJson()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Authentication failed',
                    'errors' => [
                        'google' => [$e->getMessage()],
                    ],
                ], 500);
            // }
            
            $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
            return redirect("{$frontendUrl}/login?error=" . urlencode('Authentication failed: ' . $e->getMessage()));
        }
    }
}
