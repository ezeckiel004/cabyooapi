<?php
// app/Http/Controllers/Api/SupportController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Mail\NewSupportTicketMail;

class SupportController extends Controller
{
    /**
     * Soumettre une demande de support (public)
     */
    public function submitTicket(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_type' => 'required|in:passenger,driver,partner',
            'concern_type' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'message' => 'required|string|min:10',
            'attachment' => 'nullable|file|max:5120|mimes:jpg,jpeg,png,pdf',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $attachmentPath = null;
            $attachmentName = null;

            if ($request->hasFile('attachment')) {
                $file = $request->file('attachment');
                $attachmentName = $file->getClientOriginalName();
                $extension = $file->getClientOriginalExtension();
                $fileName = Str::random(40) . '.' . $extension;
                $attachmentPath = $file->storeAs('support-attachments', $fileName, 'public');
            }

            $priority = 'normal';
            $urgentConcerns = ['lost_item', 'client_issue', 'payment_issue', 'trip_problem'];
            if (in_array($request->concern_type, $urgentConcerns)) {
                $priority = 'urgent';
            }

            $ticket = SupportTicket::create([
                'user_type' => $request->user_type,
                'concern_type' => $request->concern_type,
                'phone' => $request->phone,
                'message' => $request->message,
                'attachment_path' => $attachmentPath,
                'attachment_name' => $attachmentName,
                'priority' => $priority,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'user_id' => auth()->id(),
            ]);

            // Envoyer notification email à l'admin
            Mail::send(new NewSupportTicketMail($ticket));

            return response()->json([
                'success' => true,
                'message' => 'Votre demande a été envoyée avec succès.',
                'priority' => $priority,
                'data' => [
                    'ticket_id' => $ticket->id,
                    'status' => $ticket->status,
                    'priority' => $ticket->priority,
                    'created_at' => $ticket->created_at
                ]
            ], 201);

        } catch (\Exception $e) {
            if (isset($attachmentPath) && Storage::disk('public')->exists($attachmentPath)) {
                Storage::disk('public')->delete($attachmentPath);
            }

            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de l\'envoi de votre demande.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}
