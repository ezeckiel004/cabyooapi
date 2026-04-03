<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Nouveau ticket support</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #10B981; color: white; padding: 20px; text-align: center; }
        .header.urgent { background: #ef4444; }
        .content { padding: 20px; background: #f9fafb; }
        .info { margin-bottom: 15px; padding: 10px; background: white; border-radius: 8px; }
        .label { font-weight: bold; color: #10B981; }
        .priority { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; }
        .priority-high { background: #fee2e2; color: #ef4444; }
        .priority-normal { background: #e0e7ff; color: #3b82f6; }
        .badge { background: #f3f4f6; padding: 2px 8px; border-radius: 12px; font-size: 12px; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header {{ $ticket->priority === 'urgent' ? 'urgent' : '' }}">
            <h2>🎫 Nouveau ticket support</h2>
            @if($ticket->priority === 'urgent')
                <p style="color: white;">⚠️ PRIORITÉ HAUTE - À traiter immédiatement ⚠️</p>
            @endif
        </div>

        <div class="content">
            <div class="info">
                <p><span class="label">👤 Type d'utilisateur:</span>
                    <span class="badge">
                        @switch($ticket->user_type)
                            @case('passenger') Passager @break
                            @case('driver') Chauffeur @break
                            @case('partner') Partenaire @break
                        @endswitch
                    </span>
                </p>
                <p><span class="label">🔍 Motif:</span> {{ $ticket->concern_type }}</p>
                <p><span class="label">📱 Téléphone:</span> {{ $ticket->phone }}</p>
                <p><span class="label">📅 Date:</span> {{ $ticket->created_at->format('d/m/Y H:i') }}</p>
                <p><span class="label">🎯 Priorité:</span>
                    <span class="priority {{ $ticket->priority === 'urgent' ? 'priority-high' : 'priority-normal' }}">
                        {{ $ticket->priority === 'urgent' ? 'URGENTE' : 'Normale' }}
                    </span>
                </p>
                <p><span class="label">🌐 IP:</span> {{ $ticket->ip_address ?? 'Non disponible' }}</p>
            </div>

            <div class="info">
                <p><span class="label">💬 Message:</span></p>
                <p style="background: white; padding: 15px; border-radius: 8px; border-left: 3px solid #10B981;">
                    {{ $ticket->message }}
                </p>
            </div>

            @if($ticket->attachment_path)
            <div class="info">
                <p><span class="label">📎 Pièce jointe:</span></p>
                <p>
                    <a href="{{ url('/api/admin/support/' . $ticket->id . '/download') }}"
                       style="color: #10B981; text-decoration: none;">
                        📄 Télécharger le fichier
                    </a>
                </p>
            </div>
            @endif

            <div style="text-align: center; margin-top: 20px;">
                <a href="{{ url('/admin/support/' . $ticket->id) }}"
                   style="background: #10B981; color: white; padding: 12px 24px; text-decoration: none; border-radius: 8px;">
                    🔧 Traiter ce ticket
                </a>
            </div>
        </div>

        <div class="footer">
            <p>Cet email a été envoyé automatiquement par CABYOO.</p>
            <p>Pour gérer les tickets, connectez-vous à votre espace administrateur.</p>
        </div>
    </div>
</body>
</html>
