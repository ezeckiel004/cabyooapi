<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Nouvelle demande d'investissement</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f4f4f4;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            background: linear-gradient(135deg, #10B981, #059669);
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            color: white;
            margin: 0;
            font-size: 24px;
        }
        .header .badge {
            background: #f59e0b;
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            margin-top: 10px;
        }
        .content {
            padding: 30px;
        }
        .info-box {
            background: #f0fdf4;
            border-left: 4px solid #10B981;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .info-box p {
            margin: 5px 0;
        }
        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .details-table td {
            padding: 10px;
            border-bottom: 1px solid #e5e7eb;
        }
        .details-table td:first-child {
            font-weight: bold;
            width: 40%;
            background-color: #f9fafb;
        }
        .btn {
            display: inline-block;
            background: #10B981;
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
        }
        .btn-secondary {
            background: #3b82f6;
            margin-left: 10px;
        }
        .footer {
            background: #f9fafb;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #6b7280;
        }
        .priority {
            background: #fef3c7;
            color: #d97706;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 12px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔔 Nouvelle demande d'investissement</h1>
            <div class="badge">À traiter</div>
        </div>

        <div class="content">
            <h2>Bonjour l'équipe Cabyoo,</h2>

            <p>Une nouvelle demande d'investissement vient d'être soumise sur le site.</p>

            <div class="info-box">
                <strong>📋 Informations du demandeur :</strong><br><br>
                <p><strong>Nom complet :</strong> {{ $investment->first_name }} {{ $investment->last_name }}</p>
                <p><strong>Email :</strong> {{ $investment->email }}</p>
                <p><strong>Téléphone :</strong> {{ $investment->phone }}</p>
                @if($investment->address)
                <p><strong>Adresse :</strong> {{ $investment->address }}</p>
                @endif
                @if($investment->city)
                <p><strong>Ville :</strong> {{ $investment->city }}</p>
                @endif
            </div>

            <table class="details-table">
                <tr>
                    <td>💰 Montant à investir</td>
                    <td><strong>{{ $investment->amount_range_formatted }}</strong></td>
                </tr>
                <tr>
                    <td>📅 Date de soumission</td>
                    <td>{{ $investment->created_at->format('d/m/Y à H:i') }}</td>
                </tr>
                <tr>
                    <td>🆔 ID de la demande</td>
                    <td>#{{ $investment->id }}</td>
                </tr>
                <tr>
                    <td>⚠️ Priorité</td>
                    <td>
                        @if($investment->amount_min >= 50000)
                            <span class="priority">Haute priorité</span>
                        @elseif($investment->amount_min >= 25000)
                            <span class="priority">Priorité moyenne</span>
                        @else
                            <span class="priority">Priorité normale</span>
                        @endif
                    </td>
                </tr>
            </table>

            <p><strong>Actions recommandées :</strong></p>
            <ul>
                <li>Contacter le potentiel investisseur dans les 24h</li>
                <li>Préparer une présentation du projet</li>
                <li>Planifier un rendez-vous de présentation</li>
            </ul>

            <center>
                <a href="{{ config('app.url') }}/admin/investments/{{ $investment->id }}" class="btn">Voir la demande</a>
                <a href="mailto:{{ $investment->email }}" class="btn btn-secondary">Contacter</a>
            </center>
        </div>

        <div class="footer">
            <p>Cet email a été envoyé automatiquement suite à une nouvelle demande d'investissement.</p>
            <p>© {{ date('Y') }} Cabyoo - Tous droits réservés</p>
            <p>6 Rue Maurice Hurel, Parc d'Activités de la Plaine, 31500 Toulouse</p>
        </div>
    </div>
</body>
</html>
