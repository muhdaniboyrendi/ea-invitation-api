<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Support\Str;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    public function redirectToGoogle()
    {
        return response()->json([
            'url' => Socialite::driver('google')
                ->stateless()
                ->redirect()
                ->getTargetUrl(),
        ]);
    }

    public function handleGoogleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
            
            // Cek apakah email sudah terdaftar
            $user = User::where('email', $googleUser->email)->first();
            
            if (!$user) {
                // Buat user baru jika belum terdaftar
                $user = User::create([
                    'name' => $googleUser->name,
                    'email' => $googleUser->email,
                    'password' => Hash::make(Str::random(16)),
                    'google_id' => $googleUser->id,
                ]);
            } else {
                // Update google_id jika belum ada
                $user->update([
                    'google_id' => $googleUser->id,
                ]);
            }
            
            // Generate token untuk user
            $token = $user->createToken('auth_token')->plainTextToken;
            
            // Redirect ke frontend dengan token
            return redirect(env('FRONTEND_URL') . '/auth/google/callback?token=' . $token);
            
        } catch (\Exception $e) {
            return redirect(env('FRONTEND_URL') . '/auth/google/callback?error=Autentikasi gagal');
        }
    }
}
