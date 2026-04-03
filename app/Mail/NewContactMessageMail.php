<?php
// app/Mail/NewContactMessageMail.php

namespace App\Mail;

use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewContactMessageMail extends Mailable
{
    use Queueable, SerializesModels;

    public $contactMessage;

    public function __construct(ContactMessage $contactMessage)
    {
        $this->contactMessage = $contactMessage;
    }

    public function envelope(): Envelope
    {
        // Récupérer TOUS les emails des admins (quel que soit leur email)
        $adminEmails = User::where('role', 'admin')
            ->where('status', 'active')
            ->pluck('email')
            ->toArray();

        return new Envelope(
            subject: '📬 Nouveau message de contact - CABYOO',
            to: $adminEmails,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.new-contact-message',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
