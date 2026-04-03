<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Erreur - CABYOO</title>
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

        .error-icon {
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
            color: #dc3545;
            font-size: 28px;
            margin-bottom: 10px;
        }

        .subtitle {
            color: #666;
            font-size: 14px;
            margin-bottom: 30px;
            line-height: 1.6;
        }

        .error-box {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            padding: 20px;
            border-radius: 4px;
            margin: 20px 0;
            color: #721c24;
            font-size: 13px;
            line-height: 1.6;
            text-align: left;
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

        a {
            color: #667eea;
            text-decoration: none;
        }

        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">🚕 CABYOO</div>
        <div class="error-icon">❌</div>
        
        <h1>Une erreur s'est produite</h1>
        <p class="subtitle">
            Nous n'avons pas pu supprimer votre compte. Veuillez réessayer ou contacter le support.
        </p>

        <div class="error-box">
            <strong>Détails de l'erreur :</strong>
            <p>{{ $error }}</p>
        </div>

        <p class="subtitle">
            Si le problème persiste, veuillez <a href="mailto:contact@cabyoo.com">contacter notre équipe support</a>.
        </p>

        <a href="javascript:history.back()" class="button">Retour</a>

        <div class="footer">
            <p>© 2024 CABYOO. Tous droits réservés.</p>
        </div>
    </div>
</body>
</html>
