<?php
// app/Http/Controllers/Admin/SupportController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use App\Mail\AdminReplyMail;

class SupportController extends Controller
{
    /**
     * Liste des tickets de support
     */
    public function index(Request $request)
    {
        $query = SupportTicket::query();

        // Filtres
        if ($request->has('user_type')) {
            $query->where('user_type', $request->user_type);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('priority')) {
            $query->where('priority', $request->priority);
        }

        // Recherche
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('phone', 'LIKE', "%{$search}%")
                  ->orWhere('message', 'LIKE', "%{$search}%")
                  ->orWhere('concern_type', 'LIKE', "%{$search}%");
            });
        }

        // Tri - les urgences en premier
        $query->orderByRaw("FIELD(priority, 'urgent', 'normal')");
        $query->orderBy('created_at', 'desc');

        $tickets = $query->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $tickets
        ]);
    }

    /**
     * Détails d'un ticket
     */
    public function show($id)
    {
        $ticket = SupportTicket::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $ticket
        ]);
    }

    /**
     * Mettre à jour le statut
     */
    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,in_progress,resolved,closed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $ticket = SupportTicket::findOrFail($id);
        $ticket->update(['status' => $request->status]);

        return response()->json([
            'success' => true,
            'message' => 'Statut mis à jour',
            'data' => $ticket
        ]);
    }

    /**
     * Répondre à un ticket
     */
    public function reply(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'reply_message' => 'required|string|min:10',
            'subject' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $ticket = SupportTicket::findOrFail($id);

        try {
            // Pour l'instant, on utilise le téléphone comme identifiant
            // En production, vous récupérerez l'email depuis la base de données
            $replyData = [
                'email' => $this->getUserEmail($ticket),
                'subject' => $request->subject,
                'reply_message' => $request->reply_message,
                'recipient_name' => $this->getUserName($ticket),
                'type' => 'support',
                'ticket_id' => $ticket->id
            ];

            Mail::send(new AdminReplyMail($replyData));

            $ticket->update([
                'status' => 'resolved',
                'admin_reply' => $request->reply_message,
                'replied_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Réponse envoyée avec succès.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'envoi de la réponse.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Télécharger la pièce jointe
     */
    public function downloadAttachment($id)
    {
        $ticket = SupportTicket::findOrFail($id);

        if (!$ticket->attachment_path || !Storage::disk('public')->exists($ticket->attachment_path)) {
            return response()->json([
                'success' => false,
                'message' => 'Fichier non trouvé'
            ], 404);
        }

        return Storage::disk('public')->download($ticket->attachment_path, $ticket->attachment_name);
    }

    /**
     * Supprimer un ticket
     */
    public function destroy($id)
    {
        $ticket = SupportTicket::findOrFail($id);

        if ($ticket->attachment_path && Storage::disk('public')->exists($ticket->attachment_path)) {
            Storage::disk('public')->delete($ticket->attachment_path);
        }

        $ticket->delete();

        return response()->json([
            'success' => true,
            'message' => 'Ticket supprimé'
        ]);
    }

    /**
     * Statistiques des tickets
     */
    public function stats()
    {
        $stats = [
            'total' => SupportTicket::count(),
            'pending' => SupportTicket::where('status', 'pending')->count(),
            'in_progress' => SupportTicket::where('status', 'in_progress')->count(),
            'resolved' => SupportTicket::where('status', 'resolved')->count(),
            'closed' => SupportTicket::where('status', 'closed')->count(),
            'urgent' => SupportTicket::where('priority', 'urgent')->count(),
            'normal' => SupportTicket::where('priority', 'normal')->count(),
            'by_user_type' => [
                'passenger' => SupportTicket::where('user_type', 'passenger')->count(),
                'driver' => SupportTicket::where('user_type', 'driver')->count(),
                'partner' => SupportTicket::where('user_type', 'partner')->count(),
            ],
            'today' => SupportTicket::whereDate('created_at', today())->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Récupérer l'email de l'utilisateur
     */
    private function getUserEmail($ticket)
    {
        // Si l'utilisateur est connecté et a un email
        if ($ticket->user_id && $ticket->user) {
            return $ticket->user->email;
        }

        // Pour l'instant, on utilise un email par défaut
        // En production, vous pourriez avoir un champ email dans support_tickets
        return 'client@cabyoo.com';
    }

    /**
     * Récupérer le nom de l'utilisateur
     */
    private function getUserName($ticket)
    {
        switch ($ticket->user_type) {
            case 'passenger':
                return 'Cher passager';
            case 'driver':
                return 'Cher chauffeur';
            case 'partner':
                return 'Cher partenaire';
            default:
                return 'Cher client';
        }
    }
}
