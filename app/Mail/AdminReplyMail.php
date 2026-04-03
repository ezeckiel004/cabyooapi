<?php
// app/Mail/AdminReplyMail.php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminReplyMail extends Mailable
{
    use Queueable, SerializesModels;

    public $replyData;

    public function __construct($replyData)
    {
        $this->replyData = $replyData;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->replyData['subject'],
            to: [$this->replyData['email']],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin-reply',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
