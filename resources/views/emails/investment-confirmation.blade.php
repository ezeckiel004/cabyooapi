<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Confirmation investissement Cabyoo</title>
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
            <h1>Merci pour votre intérêt</h1>
        </div>
        
        <div class="content">
            <h2>Bonjour {{ $investment->first_name }},</h2>
            
            <p>Nous accusons bonne réception de votre demande d'investissement pour Cabyoo.</p>
            
            <div class="info-box">
                <strong>📋 Récapitulatif de votre demande :</strong><br>
                • Montant envisagé : {{ $investment->amount_range_formatted }}<br>
                • Date de soumission : {{ $investment->created_at->format('d/m/Y à H:i') }}
            </div>
            
            <p>Notre équipe dédiée aux investisseurs va étudier votre dossier et vous contactera dans les plus brefs délais (sous 48h ouvrées).</p>
            
            <p>En attendant, n'hésitez pas à :</p>
            <ul>
                <li>Visiter notre site web pour en savoir plus sur notre projet</li>
                <li>Nous contacter directement au 07 66 72 82 85 pour toute question urgente</li>
            </ul>
            
            <center>
                <a href="https://cabyoo.com" class="btn">Découvrir Cabyoo</a>
            </center>
        </div>
        
        <div class="footer">
            <p>© {{ date('Y') }} Cabyoo - Tous droits réservés</p>
            <p>6 Rue Maurice Hurel, Parc d'Activités de la Plaine, 31500 Toulouse</p>
        </div>
    </div>
</body>
</html>