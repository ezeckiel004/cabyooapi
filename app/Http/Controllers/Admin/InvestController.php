<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Investment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use App\Mail\InvestmentStatusNotification;

class InvestController extends Controller
{
    /**
     * Liste des demandes d'investissement
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $query = Investment::query();

        // Filtre par statut
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        // Filtre par date
        if ($request->has('date_from') && $request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->has('date_to') && $request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Recherche par nom, email ou téléphone
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Tri
        $sortField = $request->get('sort', 'created_at');
        $sortOrder = $request->get('order', 'desc');
        $query->orderBy($sortField, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 15);
        $investments = $query->paginate($perPage);

        // Statistiques
        $stats = [
            'total' => Investment::count(),
            'pending' => Investment::pending()->count(),
            'contacted' => Investment::contacted()->count(),
            'processed' => Investment::processed()->count(),
            'archived' => Investment::where('status', 'archived')->count(),
            'by_amount' => [
                '5000-10000' => Investment::where('amount_range', '5000-10000')->count(),
                '10000-25000' => Investment::where('amount_range', '10000-25000')->count(),
                '25000-50000' => Investment::where('amount_range', '25000-50000')->count(),
                '50000-100000' => Investment::where('amount_range', '50000-100000')->count(),
                '100000+' => Investment::where('amount_range', '100000+')->count(),
            ]
        ];

        return response()->json([
            'success' => true,
            'data' => $investments,
            'stats' => $stats,
        ]);
    }

    /**
     * Détails d'une demande d'investissement
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
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
            'data' => $investment,
        ]);
    }

    /**
     * Mettre à jour une demande (notes, statut)
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $investment = Investment::find($id);

        if (!$investment) {
            return response()->json([
                'success' => false,
                'message' => 'Demande non trouvée'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'nullable|in:pending,contacted,processed,archived',
            'admin_notes' => 'nullable|string',
            'contacted_at' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        $oldStatus = $investment->status;

        if ($request->has('status')) {
            $investment->status = $request->status;

            // Si le statut passe à "contacté", enregistrer la date
            if ($request->status === 'contacted' && !$investment->contacted_at) {
                $investment->contacted_at = now();
            }
        }

        if ($request->has('admin_notes')) {
            $investment->admin_notes = $request->admin_notes;
        }

        if ($request->has('contacted_at')) {
            $investment->contacted_at = $request->contacted_at;
        }

        $investment->save();

        // Envoyer un email de notification à l'utilisateur si le statut change vers contacted ou processed
        if ($request->has('status') && $oldStatus !== $request->status) {
            if (in_array($request->status, ['contacted', 'processed'])) {
                try {
                    Mail::to($investment->email)->send(new InvestmentStatusNotification($investment, $oldStatus, $request->status));
                    \Log::info('Email de notification de statut envoyé à: ' . $investment->email . ' pour le statut: ' . $request->status);
                } catch (\Exception $e) {
                    \Log::error('Erreur envoi email notification statut: ' . $e->getMessage());
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Demande mise à jour avec succès',
            'data' => $investment,
        ]);
    }

    /**
     * Marquer comme contacté
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function markAsContacted($id)
    {
        $investment = Investment::find($id);

        if (!$investment) {
            return response()->json([
                'success' => false,
                'message' => 'Demande non trouvée'
            ], 404);
        }

        $oldStatus = $investment->status;
        $investment->status = 'contacted';
        $investment->contacted_at = now();
        $investment->save();

        // Envoyer un email de notification à l'utilisateur
        try {
            Mail::to($investment->email)->send(new InvestmentStatusNotification($investment, $oldStatus, 'contacted'));
            \Log::info('Email de notification de contact envoyé à: ' . $investment->email);
        } catch (\Exception $e) {
            \Log::error('Erreur envoi email notification contact: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Demande marquée comme contactée',
            'data' => $investment,
        ]);
    }

    /**
     * Archiver une demande
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function archive($id)
    {
        $investment = Investment::find($id);

        if (!$investment) {
            return response()->json([
                'success' => false,
                'message' => 'Demande non trouvée'
            ], 404);
        }

        $investment->status = 'archived';
        $investment->save();

        return response()->json([
            'success' => true,
            'message' => 'Demande archivée avec succès',
            'data' => $investment,
        ]);
    }

    /**
     * Supprimer une demande
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $investment = Investment::find($id);

        if (!$investment) {
            return response()->json([
                'success' => false,
                'message' => 'Demande non trouvée'
            ], 404);
        }

        $investment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Demande supprimée avec succès',
        ]);
    }

    /**
     * Exporter les demandes en CSV
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function export(Request $request)
    {
        $query = Investment::query();

        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        $investments = $query->orderBy('created_at', 'desc')->get();

        $filename = 'investissements_' . date('Y-m-d_His') . '.csv';
        $handle = fopen('php://temp', 'w');

        // En-têtes CSV
        fputcsv($handle, [
            'ID',
            'Nom',
            'Prénom',
            'Email',
            'Téléphone',
            'Adresse',
            'Ville',
            'Montant',
            'Statut',
            'Contacté le',
            'Date de création'
        ]);

        foreach ($investments as $investment) {
            fputcsv($handle, [
                $investment->id,
                $investment->last_name,
                $investment->first_name,
                $investment->email,
                $investment->phone,
                $investment->address,
                $investment->city,
                $investment->amount_range_formatted,
                $investment->status,
                $investment->contacted_at ? $investment->contacted_at->format('d/m/Y H:i') : '',
                $investment->created_at->format('d/m/Y H:i'),
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv, 200)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    /**
     * Statistiques détaillées
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function stats()
    {
        $stats = [
            'total' => Investment::count(),
            'pending' => Investment::pending()->count(),
            'contacted' => Investment::contacted()->count(),
            'processed' => Investment::processed()->count(),
            'archived' => Investment::where('status', 'archived')->count(),
            'by_month' => Investment::selectRaw('DATE_FORMAT(created_at, "%Y-%m") as month, COUNT(*) as count')
                ->groupBy('month')
                ->orderBy('month', 'desc')
                ->limit(12)
                ->get(),
            'by_amount' => [
                'Moins de 10k€' => Investment::where('amount_range', '5000-10000')->count(),
                '10k€ - 25k€' => Investment::where('amount_range', '10000-25000')->count(),
                '25k€ - 50k€' => Investment::where('amount_range', '25000-50000')->count(),
                '50k€ - 100k€' => Investment::where('amount_range', '50000-100000')->count(),
                'Plus de 100k€' => Investment::where('amount_range', '100000+')->count(),
            ]
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
