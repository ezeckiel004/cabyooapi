<?php
// app/Mail/NewSupportTicketMail.php

namespace App\Mail;

use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewSupportTicketMail extends Mailable
{
    use Queueable, SerializesModels;

    public $ticket;

    public function __construct(SupportTicket $ticket)
    {
        $this->ticket = $ticket;
    }

    public function envelope(): Envelope
    {
        // Récupérer TOUS les emails des admins (quel que soit leur email)
        $adminEmails = User::where('role', 'admin')
            ->where('status', 'active')
            ->pluck('email')
            ->toArray();

        $priorityLabel = $this->ticket->priority === 'urgent' ? '⚠️ URGENT - ' : '';

        return new Envelope(
            subject: $priorityLabel . '🎫 Nouveau ticket support - CABYOO',
            to: $adminEmails,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.new-support-ticket',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
