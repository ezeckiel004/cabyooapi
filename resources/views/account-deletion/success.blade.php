<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compte supprimé - CABYOO</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            max-width: 600px;
            width: 100%;
            padding: 60px 40px;
            text-align: center;
        }

        .success-icon {
            font-size: 80px;
            margin-bottom: 20px;
        }

        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #333;
            margin-bottom: 30px;
        }

        h1 {
            color: #28a745;
            font-size: 28px;
            margin-bottom: 10px;
        }

        .subtitle {
            color: #666;
            font-size: 14px;
            margin-bottom: 30px;
            line-height: 1.6;
        }

        .info-box {
            background: #f0f8f0;
            border-left: 4px solid #28a745;
            padding: 20px;
            border-radius: 4px;
            margin: 20px 0;
            color: #155724;
            font-size: 13px;
            line-height: 1.6;
            text-align: left;
        }

        .info-box strong {
            display: block;
            margin-bottom: 10px;
        }

        .info-box p {
            margin-bottom: 8px;
        }

        .button {
            display: inline-block;
            padding: 12px 30px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            margin-top: 30px;
            font-weight: 600;
            transition: background 0.3s ease;
        }

        .button:hover {
            background: #5568d3;
        }

        .footer {
            color: #999;
            font-size: 12px;
            margin-top: 40px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">🚕 CABYOO</div>
        <div class="success-icon">✅</div>
        
        <h1>Compte supprimé avec succès</h1>
        <p class="subtitle">
            Votre compte {{ $userType === 'driver' ? 'chauffeur' : 'client' }} CABYOO a été supprimé définitivement.
        </p>

        <div class="info-box">
            <strong>Merci d'avoir utilisé CABYOO</strong>
            <p>• Tous vos données ont été supprimées</p>
            <p>• Vous ne recevrez plus de notifications</p>
            <p>• Votre historique n'est plus accessible</p>
        </div>

        <p class="subtitle" style="margin-top: 30px;">
            Si vous avez des questions concernant vos données, contactez notre équipe support.
        </p>

        <a href="https://cabyoo.com" class="button">Retour au site</a>

        <div class="footer">
            <p>© 2024 CABYOO. Tous droits réservés.</p>
        </div>
    </div>
</body>
</html>
