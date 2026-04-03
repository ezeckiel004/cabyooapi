<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AccountDeletionController extends Controller
{
    /**
     * Affiche la page de confirmation de suppression de compte
     */
    public function showDeletionPage(Request $request)
    {
        // Récupérer le token depuis les paramètres
        $token = $request->query('token');
        $userType = $request->query('type'); // 'driver' ou 'client'
        $userId = $request->query('user_id');

        if (!$token || !$userType || !$userId) {
            return view('account-deletion.invalid', [
                'error' => 'Paramètres invalides'
            ]);
        }

        return view('account-deletion.confirm', [
            'token' => $token,
            'userType' => $userType,
            'userId' => $userId
        ]);
    }

    /**
     * Valider et supprimer le compte
     */
    public function deleteAccount(Request $request)
    {
        $token = $request->input('token');
        $userType = $request->input('user_type');
        $userId = $request->input('user_id');
        $confirmation = $request->input('confirmation');

        // Vérifier que l'utilisateur a confirmé
        if ($confirmation !== 'confirmed') {
            return back()->withErrors(['message' => 'Vous devez confirmer la suppression']);
        }

        try {
            // Supprimer l'utilisateur
            $user = User::find($userId);

            if (!$user) {
                return view('account-deletion.error', [
                    'error' => 'Utilisateur non trouvé'
                ]);
            }

            // Supprimer les données associées selon le type
            if ($userType === 'driver') {
                // Supprimer les courses du chauffeur
                DB::table('rides')->where('driver_id', $userId)->delete();
                // Supprimer les paiements du chauffeur
                DB::table('driver_payments')->where('driver_id', $userId)->delete();
                // Supprimer les détails du chauffeur (utilise la vraie table)
                DB::table('driver_details')->where('user_id', $userId)->delete();
            } elseif ($userType === 'client') {
                // Supprimer les courses du client
                DB::table('rides')->where('client_id', $userId)->delete();
            }

            // Supprimer l'utilisateur
            $user->delete();

            return view('account-deletion.success', [
                'userType' => $userType
            ]);

        } catch (\Exception $e) {
            return view('account-deletion.error', [
                'error' => 'Erreur lors de la suppression du compte: ' . $e->getMessage()
            ]);
        }
    }
}
