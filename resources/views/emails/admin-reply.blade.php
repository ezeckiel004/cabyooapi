<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $replyData['subject'] }}</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #10B981; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background: #f9fafb; }
        .message { background: white; padding: 20px; border-radius: 8px; border-left: 4px solid #10B981; margin: 20px 0; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; border-top: 1px solid #e5e7eb; margin-top: 20px; }
        .contact { background: #f3f4f6; padding: 15px; border-radius: 8px; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>✉️ {{ $replyData['subject'] }}</h2>
        </div>

        <div class="content">
            <p>Bonjour <strong>{{ $replyData['recipient_name'] ?? 'Client' }}</strong>,</p>

            <div class="message">
                {!! nl2br(e($replyData['reply_message'])) !!}
            </div>

            <p>Cordialement,</p>
            <p><strong>L'équipe CABYOO</strong></p>

            <div class="contact">
                <p style="margin: 0; font-size: 13px;">
                    📞 Besoin d'aide ? Contactez-nous au <strong>07 66 72 82 85</strong><br>
                    📧 Ou par email : <strong>support@cabyoo.com</strong>
                </p>
            </div>
        </div>

        <div class="footer">
            <p>Cet email est une réponse automatique à votre demande.</p>
            <p>© {{ date('Y') }} CABYOO - Tous droits réservés.</p>
        </div>
    </div>
</body>
</html>
