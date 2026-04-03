<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    // Voir le profil
    public function show()
    {
        $user = Auth::user();

        // Statistiques
        $stats = [
            'total_rides' => $user->clientRides()->count(),
            'completed_rides' => $user->clientRides()->where('status', 'completed')->count(),
            'cancelled_rides' => $user->clientRides()->where('status', 'cancelled')->count(),
            'total_spent' => (float) $user->clientRides()->where('status', 'completed')->sum('final_price'),
        ];

        // Dernières courses
        $recentRides = $user->clientRides()
            ->with('driver')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return response()->json([
            'user' => $user,
            'stats' => $stats,
            'recent_rides' => $recentRides,
        ]);
    }

    // Mettre à jour le profil
    public function update(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'email', Rule::unique('users')->ignore($user->id)],
            'phone' => ['sometimes', 'string', Rule::unique('users')->ignore($user->id)],
            'current_password' => 'sometimes|required_with:password',
            'password' => 'sometimes|min:8|confirmed',
        ]);

        // Vérifier le mot de passe actuel si changement de mot de passe
        if ($request->has('password')) {
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'message' => 'Le mot de passe actuel est incorrect'
                ], 422);
            }

            $validated['password'] = Hash::make($request->password);
        }

        $user->update($validated);

        return response()->json([
            'message' => 'Profil mis à jour avec succès',
            'user' => $user
        ]);
    }

    // Uploader la photo de profil
    public function uploadProfilePicture(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'profile_picture' => 'required|image|mimes:jpeg,png,jpg,gif|max:5120', // 5MB max
        ]);

        // Supprimer l'ancienne photo si elle existe
        if ($user->profile_picture && Storage::disk('public')->exists($user->profile_picture)) {
            Storage::disk('public')->delete($user->profile_picture);
        }

        // Sauvegarder la nouvelle photo
        if ($request->hasFile('profile_picture')) {
            $file = $request->file('profile_picture');
            $path = $file->store('profile-pictures/' . $user->id, 'public');

            // Mettre à jour la base de données avec le chemin
            $user->update([
                'profile_picture' => $path,
            ]);

            // Recharger l'utilisateur depuis la base de données
            $user = Auth::user();

            // Retourner l'URL complète avec le domaine
            $photoUrl = url(Storage::url($path));

            return response()->json([
                'message' => 'Photo de profil mise à jour avec succès',
                'data' => [
                    'profile_picture' => $photoUrl,
                    'profile_picture_path' => $path,
                    'user' => $user,
                ]
            ]);
        }

        return response()->json([
            'message' => 'Erreur lors du téléchargement de la photo'
        ], 422);
    }

    // 🔴 NOUVEAU: Récupérer les stats du client
    public function stats()
    {
        $user = Auth::user();

        // Récupérer le total des courses complétées
        $completedRides = $user->clientRides()->where('status', 'completed')->get();
        
        $totalRides = $user->clientRides()->count();
        $totalSpent = (float) $completedRides->sum('final_price');
        
        // Calculer la note moyenne à partir de la note moyenne des chauffeurs avec lesquels il a roulé
        // (hypothèse: car vous notez les chauffeurs, la note moyenne que les chauffeurs vous donnent en retour)
        $averageRating = 0;
        if ($completedRides->count() > 0) {
            $totalRating = 0;
            $rideCount = 0;
            foreach ($completedRides as $ride) {
                // Si le chauffeur a une note, on la considère comme la satisfaction du client
                if ($ride->driver && $ride->driver->driverDetail && $ride->driver->driverDetail->rating) {
                    $totalRating += $ride->driver->driverDetail->rating;
                    $rideCount++;
                }
            }
            $averageRating = $rideCount > 0 ? $totalRating / $rideCount : 5.0;
        } else {
            $averageRating = 5.0; // Note par défaut si pas de courses
        }

        return response()->json([
            'data' => [
                'total_rides' => $totalRides,
                'total_spent' => round($totalSpent, 2),
                'average_rating' => round($averageRating, 1),
            ]
        ]);
    }
}
