<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Mise à jour de votre demande d'investissement</title>
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
        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: bold;
        }
        .status-pending { background: #fef3c7; color: #d97706; }
        .status-contacted { background: #dbeafe; color: #2563eb; }
        .status-processed { background: #d1fae5; color: #059669; }
        .btn {
            display: inline-block;
            background: #10B981;
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
        }
        .footer {
            background: #f9fafb;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #6b7280;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Mise à jour de votre demande</h1>
        </div>

        <div class="content">
            <h2>Bonjour {{ $investment->first_name }},</h2>

            <p>Nous vous informons que votre demande d'investissement a été mise à jour.</p>

            <div class="info-box">
                <strong>📋 État de votre demande :</strong><br><br>

                @if($newStatus == 'contacted')
                    <p>✅ <strong>Un conseiller Cabyoo vous a contacté</strong></p>
                    <p>Notre équipe a pris connaissance de votre projet et vous a contacté pour échanger sur les opportunités d'investissement.</p>
                    <p>Si vous n'avez pas encore été contacté, n'hésitez pas à nous rappeler au <strong>07 66 72 82 85</strong>.</p>
                @elseif($newStatus == 'processed')
                    <p>✅ <strong>Votre dossier a été traité</strong></p>
                    <p>Notre équipe a finalisé l'étude de votre demande. Un conseiller reste à votre disposition pour toute information complémentaire.</p>
                    <p>Nous vous remercions pour votre confiance et votre intérêt pour Cabyoo.</p>
                @endif
            </div>

            <div class="info-box">
                <strong>📋 Récapitulatif de votre demande :</strong><br>
                • Montant envisagé : {{ $investment->amount_range_formatted }}<br>
                • Date de soumission : {{ $investment->created_at->format('d/m/Y à H:i') }}<br>
                • Statut actuel :
                    @if($newStatus == 'contacted')
                        <span class="status-badge status-contacted">Contacté</span>
                    @elseif($newStatus == 'processed')
                        <span class="status-badge status-processed">Traité</span>
                    @endif
            </div>

            <p>Pour toute question, n'hésitez pas à nous contacter :</p>
            <ul>
                <li>📞 Par téléphone : <strong>07 66 72 82 85</strong></li>
                <li>✉️ Par email : <strong>contact@cabyoo.com</strong></li>
            </ul>

            <center>
                <a href="https://cabyoo.com" class="btn">Visiter le site Cabyoo</a>
            </center>
        </div>

        <div class="footer">
            <p>© {{ date('Y') }} Cabyoo - Tous droits réservés</p>
            <p>6 Rue Maurice Hurel, Parc d'Activités de la Plaine, 31500 Toulouse</p>
        </div>
    </div>
</body>
</html>
