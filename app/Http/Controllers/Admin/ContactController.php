<?php
// app/Http/Controllers/Admin/ContactController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use App\Mail\AdminReplyMail;

class ContactController extends Controller
{
    /**
     * Liste des messages de contact
     */
    public function index(Request $request)
    {
        $query = ContactMessage::query();

        // Filtre par statut
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Recherche
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%")
                  ->orWhere('subject', 'LIKE', "%{$search}%");
            });
        }

        // Tri
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $messages = $query->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $messages
        ]);
    }

    /**
     * Détails d'un message
     */
    public function show($id)
    {
        $message = ContactMessage::findOrFail($id);

        // Marquer comme lu si c'est la première fois
        if ($message->status === 'pending') {
            $message->update(['status' => 'read']);
        }

        return response()->json([
            'success' => true,
            'data' => $message
        ]);
    }

    /**
     * Marquer comme lu
     */
    public function markAsRead($id)
    {
        $message = ContactMessage::findOrFail($id);
        $message->update(['status' => 'read']);

        return response()->json([
            'success' => true,
            'message' => 'Message marqué comme lu'
        ]);
    }

    /**
     * Marquer comme traité
     */
    public function markAsProcessed($id)
    {
        $message = ContactMessage::findOrFail($id);
        $message->update(['status' => 'processed']);

        return response()->json([
            'success' => true,
            'message' => 'Message marqué comme traité'
        ]);
    }

    /**
     * Archiver un message
     */
    public function archive($id)
    {
        $message = ContactMessage::findOrFail($id);
        $message->update(['status' => 'archived']);

        return response()->json([
            'success' => true,
            'message' => 'Message archivé'
        ]);
    }

    /**
     * Répondre à un message
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

        $message = ContactMessage::findOrFail($id);

        try {
            $replyData = [
                'email' => $message->email,
                'subject' => $request->subject,
                'reply_message' => $request->reply_message,
                'recipient_name' => $message->name,
                'type' => 'contact'
            ];

            Mail::send(new AdminReplyMail($replyData));

            $message->update([
                'status' => 'processed',
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
     * Supprimer un message
     */
    public function destroy($id)
    {
        $message = ContactMessage::findOrFail($id);
        $message->delete();

        return response()->json([
            'success' => true,
            'message' => 'Message supprimé'
        ]);
    }

    /**
     * Statistiques des messages
     */
    public function stats()
    {
        $stats = [
            'total' => ContactMessage::count(),
            'pending' => ContactMessage::where('status', 'pending')->count(),
            'read' => ContactMessage::where('status', 'read')->count(),
            'processed' => ContactMessage::where('status', 'processed')->count(),
            'archived' => ContactMessage::where('status', 'archived')->count(),
            'today' => ContactMessage::whereDate('created_at', today())->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }
}
