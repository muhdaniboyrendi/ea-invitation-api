<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class GoogleAuthController extends Controller
{
    public function handleGoogleToken(Request $request)
    {
        $code = $request->input('code');
        $codeVerifier = $request->input('codeVerifier');

        $response = Http::post('https://oauth2.googleapis.com/token', [
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'redirect_uri' => 'http://localhost:3000/login/google/callback',
            'grant_type' => 'authorization_code',
            'code' => $code,
            'code_verifier' => $codeVerifier,
        ]);

        if ($response->failed()) {
            return response()->json(['status' => false, 'message' => 'Failed to exchange code'], 400);
        }

        $tokenData = $response->json();
        $accessToken = $tokenData['access_token'];

        $userResponse = Http::withToken($accessToken)->get('https://www.googleapis.com/oauth2/v3/userinfo');
        $googleUser = $userResponse->json();

        $user = User::where('email', $googleUser['email'])->first();

        if (!$user) {
            $user = User::create([
                'name' => $googleUser['name'],
                'email' => $googleUser['email'],
                'password' => bcrypt(Str::random(16)),
                'google_id' => $googleUser['sub'],
            ]);
        } else {
            if (!$user->google_id) {
                $user->google_id = $googleUser['sub'];
                $user->save();
            }
        }

        Auth::login($user);
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json(['status' => true, 'data' => ['token' => $token, 'user' => $user]]);
    }
}
