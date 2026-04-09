<?php

namespace App\Mail;

use App\Models\Investment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvestmentStatusNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $investment;
    public $oldStatus;
    public $newStatus;

    /**
     * Create a new message instance.
     */
    public function __construct(Investment $investment, $oldStatus, $newStatus)
    {
        $this->investment = $investment;
        $this->oldStatus = $oldStatus;
        $this->newStatus = $newStatus;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = match($this->newStatus) {
            'contacted' => 'Mise à jour de votre demande d\'investissement - Prise de contact',
            'processed' => 'Mise à jour de votre demande d\'investissement - Dossier traité',
            default => 'Mise à jour de votre demande d\'investissement - Cabyoo',
        };

        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.investment-status-notification',
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }
}
