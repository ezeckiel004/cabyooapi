<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supprimer mon compte - CABYOO</title>
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
            padding: 40px;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #333;
            margin-bottom: 20px;
        }

        .warning-icon {
            font-size: 48px;
            color: #ff6b6b;
            margin-bottom: 15px;
        }

        h1 {
            color: #333;
            font-size: 28px;
            margin-bottom: 10px;
        }

        .subtitle {
            color: #666;
            font-size: 14px;
        }

        .warning-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
            color: #856404;
            font-size: 13px;
            line-height: 1.6;
        }

        .danger-box {
            background: #ffe6e6;
            border-left: 4px solid #ff6b6b;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
            color: #721c24;
            font-size: 13px;
            line-height: 1.6;
        }

        .danger-box strong {
            display: block;
            margin-bottom: 8px;
            font-style: italic;
        }

        .content {
            margin: 30px 0;
        }

        .section {
            margin-bottom: 20px;
        }

        .section h3 {
            color: #333;
            font-size: 16px;
            margin-bottom: 10px;
        }

        .section p {
            color: #666;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 8px;
        }

        .checkbox-group {
            display: flex;
            align-items: flex-start;
            margin: 15px 0;
        }

        .checkbox-group input[type="checkbox"] {
            margin-right: 12px;
            margin-top: 3px;
            cursor: pointer;
            accent-color: #667eea;
            width: 18px;
            height: 18px;
        }

        .checkbox-group label {
            color: #333;
            font-size: 14px;
            cursor: pointer;
            flex: 1;
            line-height: 1.5;
        }

        .checkbox-group label strong {
            color: #ff6b6b;
        }

        .actions {
            display: flex;
            gap: 12px;
            margin-top: 30px;
        }

        button {
            flex: 1;
            padding: 12px 20px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-cancel {
            background: #f0f0f0;
            color: #333;
        }

        .btn-cancel:hover {
            background: #e0e0e0;
        }

        .btn-delete {
            background: #ff6b6b;
            color: white;
        }

        .btn-delete:hover:not(:disabled) {
            background: #ff5252;
            box-shadow: 0 4px 12px rgba(255, 107, 107, 0.3);
        }

        .btn-delete:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            color: #333;
            font-size: 14px;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .footer {
            text-align: center;
            color: #999;
            font-size: 12px;
            margin-top: 20px;
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
        <div class="header">
            <div class="logo">🚕 CABYOO</div>
            <div class="warning-icon">⚠️</div>
            <h1>Supprimer mon compte</h1>
            <p class="subtitle">Cette action est irréversible</p>
        </div>

        <div class="warning-box">
            <strong>⚠️ Attention !</strong>
            La suppression de votre compte CABYOO est définitive et ne peut pas être annulée. Toutes vos données seront supprimées des serveurs.
        </div>

        <div class="danger-box">
            <strong>Les conséquences de la suppression :</strong>
            • Votre compte et toutes vos données seront supprimés<br>
            • Vous ne pourrez plus accéder à votre historique<br>
            • Vos cours {{ $userType === 'driver' ? 'effectuées' : 'réservées' }} seront archivées<br>
            • Cette action ne peut pas être annulée
        </div>

        <div class="content">
            <div class="section">
                <h3>Avant de continuer...</h3>
                <p>Si vous avez des questions ou rencontrez des problèmes avec votre compte, notre équipe support peut vous aider.</p>
                <p><a href="mailto:contact@cabyoo.com">📧 Contacter le support</a></p>
            </div>
        </div>

        <form method="POST" action="/account/delete">
            @csrf

            <input type="hidden" name="token" value="{{ $token }}">
            <input type="hidden" name="user_type" value="{{ $userType }}">
            <input type="hidden" name="user_id" value="{{ $userId }}">

            <div class="checkbox-group">
                <input type="checkbox" id="confirm1" required onchange="updateDeleteButton()">
                <label for="confirm1">
                    Je comprends que la suppression de mon compte est <strong>irréversible</strong>
                </label>
            </div>

            <div class="checkbox-group">
                <input type="checkbox" id="confirm2" required onchange="updateDeleteButton()">
                <label for="confirm2">
                    Je confirme que je veux <strong>supprimer définitivement</strong> mon compte CABYOO
                </label>
            </div>

            <div class="checkbox-group">
                <input type="checkbox" id="confirm3" required onchange="updateDeleteButton()">
                <label for="confirm3">
                    Je reconnais qu'il <strong>n'y a pas de retour en arrière</strong> possible
                </label>
            </div>

            <div class="actions">
                <button type="button" class="btn-cancel" onclick="history.back()">
                    Annuler
                </button>
                <button type="submit" class="btn-delete" id="deleteBtn" disabled>
                    Supprimer définitivement
                </button>
            </div>
            <input type="hidden" name="confirmation" value="confirmed">
        </form>

        <div class="footer">
            <p>Besoin d'aide ? <a href="mailto:contact@cabyoo.com">contact@cabyoo.com</a></p>
        </div>
    </div>

    <script>
        function updateDeleteButton() {
            const confirm1 = document.getElementById('confirm1').checked;
            const confirm2 = document.getElementById('confirm2').checked;
            const confirm3 = document.getElementById('confirm3').checked;
            const deleteBtn = document.getElementById('deleteBtn');

            if (confirm1 && confirm2 && confirm3) {
                deleteBtn.disabled = false;
            } else {
                deleteBtn.disabled = true;
            }
        }
    </script>
</body>
</html>
