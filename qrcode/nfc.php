<?php
// Redirection vers la version Web NFC (Android)
$text = isset($_GET['text']) ? $_GET['text'] : '';
$redirect_url = 'web-nfc.html';
if (!empty($text)) {
    $redirect_url .= '?text=' . urlencode($text);
}
header("Location: $redirect_url");
exit();
?>
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Écriture NFC - iPhone</title>
    <link rel="stylesheet" href="../css/base.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 15px;
            background-color: #f5f5f5;
            text-align: center;
        }
        .container {
            max-width: 600px;
            margin: auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
        }
        .info-box {
            background-color: #e3f2fd;
            border-left: 4px solid #2196F3;
            padding: 15px;
            margin: 15px 0;
            border-radius: 4px;
            text-align: left;
        }
        .warning-box {
            background-color: #fff3e0;
            border-left: 4px solid #ff9800;
            padding: 15px;
            margin: 15px 0;
            border-radius: 4px;
            text-align: left;
        }
        .success-box {
            background-color: #f1f8e9;
            border-left: 4px solid #4caf50;
            padding: 15px;
            margin: 15px 0;
            border-radius: 4px;
            text-align: left;
        }
        textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            box-sizing: border-box;
            margin: 15px 0;
            font-family: Arial, sans-serif;
            resize: vertical;
            min-height: 120px;
        }
        button {
            padding: 12px 30px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin: 5px;
        }
        button:hover {
            background-color: #0056b3;
        }
        .copy-btn {
            background-color: #28a745;
            font-size: 14px;
            padding: 8px 15px;
        }
        .copy-btn:hover {
            background-color: #218838;
        }
        .app-links {
            text-align: center;
            margin: 20px 0;
        }
        .app-link {
            display: inline-block;
            padding: 10px 20px;
            margin: 5px;
            background-color: #f0f0f0;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            color: #333;
            transition: all 0.3s;
        }
        .app-link:hover {
            background-color: #e0e0e0;
            text-decoration: none;
        }
        .qr-section {
            margin: 20px 0;
            text-align: center;
        }
        .qr-code {
            max-width: 300px;
            margin: 20px auto;
            padding: 10px;
            background: white;
            border: 2px solid #ddd;
            border-radius: 8px;
        }
        .steps {
            text-align: left;
            background: #f9f9f9;
            padding: 15px;
            border-radius: 4px;
            margin: 15px 0;
        }
        .steps ol {
            margin: 10px 0;
            padding-left: 20px;
        }
        .steps li {
            margin: 8px 0;
            line-height: 1.6;
        }
        .data-preview {
            background: #f5f5f5;
            padding: 12px;
            border-radius: 4px;
            margin: 15px 0;
            word-break: break-all;
            font-family: monospace;
            font-size: 12px;
            text-align: left;
            border: 1px solid #ddd;
        }
        .back-link {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 20px;
            background-color: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 4px;
        }
        .back-link:hover {
            background-color: #5a6268;
        }
        @media (max-width: 600px) {
            .container {
                padding: 15px;
            }
            h1 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>✍️ Écriture NFC sur iPhone</h1>
        
        <div class="info-box">
            <strong>ℹ️ Information :</strong> Entrez le texte que vous souhaitez écrire sur une puce NFC. 
            Nous vous fournirons les instructions adaptées à votre appareil iOS.
        </div>

        <form method="post">
            <label for="nfc_text" style="display: block; text-align: left; margin: 15px 0 5px 0; font-weight: bold;">
                Texte à écrire sur la puce NFC :
            </label>
            <textarea name="nfc_text" id="nfc_text" required placeholder="Entrez le texte ou l'URL à écrire sur la puce NFC..." 
                      style="border: 2px solid #ddd;"><?php echo htmlspecialchars($nfc_data); ?></textarea>
            <button type="submit" name="write_nfc">Préparer l'écriture NFC</button>
        </form>

        <?php if ($nfc_data): ?>
            <div class="success-box">
                <strong>✓ Texte préparé avec succès !</strong><br>
                Vous pouvez maintenant utiliser l'une des méthodes ci-dessous pour écrire sur la puce NFC.
            </div>

            <div class="data-preview">
                <strong>Données à écrire :</strong><br>
                <?php echo htmlspecialchars($nfc_data); ?>
            </div>

            <h2 style="margin-top: 30px; color: #333;">📱 Méthodes pour iPhone</h2>

            <div class="warning-box">
                <strong>⚠️ Limitation iOS :</strong> Apple ne propose pas d'API d'écriture NFC pour les applications web. 
                Vous devez utiliser une application native tierce pour écrire sur les puces NFC.
            </div>

            <h3 style="text-align: left;">Option 1 : Applications NFC recommandées (App Store)</h3>
            <div class="steps">
                <ol>
                    <li>Téléchargez une application NFC compatible sur l'App Store (ex: <strong>NFC Tagwriter by NXP</strong>, <strong>TagWriter</strong>)</li>
                    <li>Ouvrez l'application</li>
                    <li>Sélectionnez "Write" ou "Écrire"</li>
                    <li>Copiez-collez le texte ci-dessous</li>
                    <li>Placez votre iPhone XS contre la puce NFC</li>
                    <li>Confirmez l'écriture dans l'application</li>
                </ol>
            </div>

            <h3 style="text-align: left;">Option 2 : Copier le texte</h3>
            <div style="background: #f5f5f5; padding: 15px; border-radius: 4px; margin: 15px 0;">
                <button class="copy-btn" onclick="copyToClipboard()">📋 Copier le texte</button>
                <p style="margin: 10px 0; font-size: 14px; color: #666;">Collez ce texte dans votre application NFC</p>
            </div>

            <h3 style="text-align: left;">Option 3 : Lien direct</h3>
            <div style="background: #f5f5f5; padding: 15px; border-radius: 4px; margin: 15px 0;">
                <p style="word-break: break-all; font-family: monospace; font-size: 12px; margin: 0;">
                    <?php echo htmlspecialchars($nfc_data); ?>
                </p>
                <button class="copy-btn" onclick="copyLink()">📋 Copier le lien</button>
            </div>

            <h3 style="text-align: left;">Option 4 : QR Code</h3>
            <div class="qr-section">
                <p>Scannez ce QR code pour accéder au texte :</p>
                <div class="qr-code">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=<?php echo $nfc_url; ?>&ecc=H" 
                         alt="QR Code NFC" style="max-width: 100%;">
                </div>
            </div>

            <h2 style="margin-top: 30px; color: #333;">📲 Pour Android (Web NFC API)</h2>
            <div class="info-box">
                <strong>Android :</strong> Si vous avez un téléphone Android, vous pouvez utiliser la <strong>Web NFC API</strong> 
                qui supporte l'écriture directe via le navigateur Chrome (sur certains appareils).
                <a href="/qrcode/web-nfc.html" style="color: #2196F3; text-decoration: underline;">Cliquez ici pour la version Android</a>
            </div>

        <?php else: ?>
            <div class="warning-box">
                <strong>ℹ️ Étapes :</strong>
                <ol style="margin-left: 20px;">
                    <li>Entrez le texte ou l'URL à écrire</li>
                    <li>Cliquez sur "Préparer l'écriture NFC"</li>
                    <li>Suivez les instructions pour votre appareil</li>
                </ol>
            </div>
        <?php endif; ?>

        <a href="index.php" class="back-link">← Retour au générateur QR</a>
    </div>

    <script>
        function copyToClipboard() {
            const text = "<?php echo htmlspecialchars(addslashes($nfc_data)); ?>";
            navigator.clipboard.writeText(text).then(() => {
                alert("✓ Texte copié dans le presse-papiers !");
            }).catch(err => {
                // Fallback for older browsers
                const textArea = document.createElement("textarea");
                textArea.value = text;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand("copy");
                document.body.removeChild(textArea);
                alert("✓ Texte copié dans le presse-papiers !");
            });
        }

        function copyLink() {
            const link = "<?php echo htmlspecialchars(addslashes($nfc_data)); ?>";
            navigator.clipboard.writeText(link).then(() => {
                alert("✓ Lien copié dans le presse-papiers !");
            }).catch(err => {
                alert("Erreur lors de la copie");
            });
        }
    </script>
</body>
</html>
