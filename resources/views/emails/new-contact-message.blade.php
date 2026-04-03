<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Nouveau message de contact</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #10B981; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background: #f9fafb; }
        .info { margin-bottom: 15px; padding: 10px; background: white; border-radius: 8px; }
        .label { font-weight: bold; color: #10B981; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
        .urgent { background: #fee2e2; border-left: 4px solid #ef4444; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>📬 Nouveau message de contact</h2>
        </div>

        <div class="content">
            <div class="info">
                <p><span class="label">👤 Nom:</span> {{ $contactMessage->name }}</p>
                <p><span class="label">📧 Email:</span> {{ $contactMessage->email }}</p>
                <p><span class="label">📱 Téléphone:</span> {{ $contactMessage->phone ?? 'Non renseigné' }}</p>
                <p><span class="label">🏷️ Sujet:</span> {{ $contactMessage->subject }}</p>
                <p><span class="label">📅 Date:</span> {{ $contactMessage->created_at->format('d/m/Y H:i') }}</p>
                <p><span class="label">🌐 IP:</span> {{ $contactMessage->ip_address ?? 'Non disponible' }}</p>
            </div>

            <div class="info">
                <p><span class="label">💬 Message:</span></p>
                <p style="background: white; padding: 15px; border-radius: 8px; border-left: 3px solid #10B981;">
                    {{ $contactMessage->message }}
                </p>
            </div>

            <div style="text-align: center; margin-top: 20px;">
                <a href="{{ url('/admin/contact/' . $contactMessage->id) }}"
                   style="background: #10B981; color: white; padding: 12px 24px; text-decoration: none; border-radius: 8px;">
                    ✉️ Répondre à ce message
                </a>
            </div>
        </div>

        <div class="footer">
            <p>Cet email a été envoyé automatiquement par CABYOO.</p>
            <p>Pour gérer les messages, connectez-vous à votre espace administrateur.</p>
        </div>
    </div>
</body>
</html>
