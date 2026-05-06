<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
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

    // Mettre à jour le profil
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:20',
            'avatar' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Mettre à jour les champs
        $user->name = $request->name;
        $user->email = $request->email;
        if ($request->phone) {
            $user->phone = $request->phone;
        }

        // Gérer l'avatar
        if ($request->hasFile('avatar')) {
            // Supprimer l'ancien avatar
            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }

            $avatarPath = $request->file('avatar')->store('avatars', 'public');
            $user->avatar = $avatarPath;
            $user->profile_picture = $avatarPath;
        }

        $user->save();

        return response()->json([
            'message' => 'Profil mis à jour avec succès',
            'user' => $user
        ]);
    }

    // Changer le mot de passe
    public function updatePassword(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Vérifier le mot de passe actuel
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'Le mot de passe actuel est incorrect',
                'errors' => ['current_password' => ['Le mot de passe actuel est incorrect']]
            ], 422);
        }

        // Mettre à jour le mot de passe
        $user->password = Hash::make($request->password);
        $user->save();

        return response()->json([
            'message' => 'Mot de passe mis à jour avec succès'
        ]);
    }
}
