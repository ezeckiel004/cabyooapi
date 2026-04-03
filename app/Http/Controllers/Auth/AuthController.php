<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    // Login
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'device_name' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            Log::warning('Failed login attempt', ['email' => $request->email]);
            throw ValidationException::withMessages([
                'email' => ['Les identifiants sont incorrects.'],
            ]);
        }

        if ($user->status !== 'active') {
            Log::warning('Login attempt with inactive account', ['user_id' => $user->id, 'email' => $user->email]);
            throw ValidationException::withMessages([
                'email' => ['Votre compte est désactivé. Contactez l\'administration.'],
            ]);
        }

        // Load driver details if user is a driver
        if ($user->isDriver()) {
            $user->load('driverDetail');
        }

        $token = $user->createToken($request->device_name)->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    // Logout
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Déconnexion réussie']);
    }

    // Me (utilisateur courant)
    public function me(Request $request)
    {
        $user = $request->user();

        if ($user->isDriver()) {
            $user->load('driverDetail');
        }

        return response()->json($user);
    }
}
