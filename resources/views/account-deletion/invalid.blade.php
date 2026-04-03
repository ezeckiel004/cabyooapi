<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lien invalide - CABYOO</title>
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

        .warning-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 20px;
            border-radius: 4px;
            margin: 20px 0;
            color: #856404;
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
        <div class="error-icon">🔗</div>
        
        <h1>Lien invalide</h1>
        <p class="subtitle">
            Le lien de suppression de compte que vous avez utilisé est invalide ou expiré.
        </p>

        <div class="warning-box">
            <strong>Causes possibles :</strong>
            <p>• Le lien a expiré</p>
            <p>• Les paramètres sont incomplets</p>
            <p>• Le lien a été déjà utilisé</p>
        </div>

        <p class="subtitle">
            Pour supprimer votre compte, veuillez utiliser le bouton dans votre application mobile CABYOO, ou <a href="mailto:contact@cabyoo.com">contacter le support</a>.
        </p>

        <a href="https://cabyoo.com" class="button">Retour au site</a>

        <div class="footer">
            <p>© 2024 CABYOO. Tous droits réservés.</p>
        </div>
    </div>
</body>
</html>
