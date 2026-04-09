<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Investment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use App\Mail\InvestmentConfirmation;
use App\Mail\InvestmentAdminNotification;

class InvestController extends Controller
{
    /**
     * Soumettre une demande d'investissement
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function submit(Request $request)
    {
        // Validation des données
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'required|email|max:255',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:255',
            'amount_range' => 'required|string|in:5000-10000,10000-25000,25000-50000,50000-100000,100000+',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        // Extraction des montants min/max
        $amountRange = $request->amount_range;
        $amountMin = null;
        $amountMax = null;

        switch ($amountRange) {
            case '5000-10000':
                $amountMin = 5000;
                $amountMax = 10000;
                break;
            case '10000-25000':
                $amountMin = 10000;
                $amountMax = 25000;
                break;
            case '25000-50000':
                $amountMin = 25000;
                $amountMax = 50000;
                break;
            case '50000-100000':
                $amountMin = 50000;
                $amountMax = 100000;
                break;
            case '100000+':
                $amountMin = 100000;
                $amountMax = null;
                break;
        }

        // Création de la demande d'investissement
        $investment = Investment::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'phone' => $request->phone,
            'email' => $request->email,
            'address' => $request->address,
            'city' => $request->city,
            'amount_range' => $amountRange,
            'amount_min' => $amountMin,
            'amount_max' => $amountMax,
            'status' => 'pending',
        ]);

        // Envoi d'un email de confirmation à l'utilisateur
        try {
            Mail::to($request->email)->send(new InvestmentConfirmation($investment));
            \Log::info('Email de confirmation envoyé à: ' . $request->email);
        } catch (\Exception $e) {
            \Log::error('Erreur envoi email confirmation investissement: ' . $e->getMessage());
        }

        // Envoi d'une notification aux admins
        try {
            // Récupérer tous les emails des admins
            $adminEmails = User::where('role', 'admin')
                ->where('status', 'active')
                ->pluck('email')
                ->toArray();

            // Ajouter les emails par défaut si aucun admin n'existe
            if (empty($adminEmails)) {
                $adminEmails = ['admin@cabyoo.com', 'contact@cabyoo.com'];
            }

            // Envoyer l'email à chaque admin
            foreach ($adminEmails as $adminEmail) {
                Mail::to($adminEmail)->send(new InvestmentAdminNotification($investment));
            }

            \Log::info('Notification admin envoyée pour investissement #' . $investment->id);
        } catch (\Exception $e) {
            \Log::error('Erreur envoi notification admin investissement: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Votre demande d\'investissement a bien été envoyée. Un conseiller vous contactera dans les plus brefs délais.',
            'data' => [
                'id' => $investment->id,
                'status' => $investment->status,
            ]
        ], 201);
    }

    /**
     * Vérifier le statut d'une demande
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkStatus($id)
    {
        $investment = Investment::find($id);

        if (!$investment) {
            return response()->json([
                'success' => false,
                'message' => 'Demande non trouvée'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'status' => $investment->status,
                'status_label' => $this->getStatusLabel($investment->status),
                'created_at' => $investment->created_at,
            ]
        ]);
    }

    /**
     * Get status label
     */
    private function getStatusLabel($status)
    {
        return match ($status) {
            'pending' => 'En attente de traitement',
            'contacted' => 'Un conseiller vous a contacté',
            'processed' => 'Demande traitée',
            'archived' => 'Archivée',
            default => 'Inconnu',
        };
    }
}
