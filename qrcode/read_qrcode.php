<?php
/**
 * Script pour scanner un QR code via la caméra du téléphone
 * Utilise la WebRTC API et la bibliothèque html5-qrcode
 * Enregistre les résultats dans la table collections
 */

// Inclure le fichier de configuration
$config_path = dirname(dirname(__FILE__)) . '/config.php';

if (!file_exists($config_path)) {
    die('Erreur: Fichier config.php non trouvé à ' . $config_path);
}

require_once $config_path;

// Traiter l'AJAX pour sauvegarder le résultat
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
    header('Content-Type: application/json; charset=utf-8');
    
    // Vérifier la connexion
    if (!$conx) {
        echo json_encode(['success' => false, 'message' => 'Erreur de connexion à la base de données: ' . mysqli_connect_error()]);
        exit;
    }
    
    $nom_origine = isset($_POST['nom']) ? trim($_POST['nom']) : '';

    if (empty($nom_origine)) {
        echo json_encode(['success' => false, 'message' => 'Le contenu est vide']);
        exit;
    }

    // Déterminer la valeur : 2 si commence par http, sinon 1
    $valeur = (strpos($nom_origine, 'http') === 0) ? 1 : 2;

    // Vérifier si les 16 derniers caractères existent déjà (doublon)
    $last16 = substr($nom_origine, -16);
    $stmt_check = $conx->prepare("SELECT id_collection FROM collections WHERE RIGHT(nom, 16) = ? LIMIT 1");
    if ($stmt_check === false) {
        echo json_encode(['success' => false, 'message' => 'Erreur de préparation (check doublon): ' . $conx->error]);
        exit;
    }
    $stmt_check->bind_param("s", $last16);
    $stmt_check->execute();
    $stmt_check->store_result();
    if ($stmt_check->num_rows > 0) {
        $stmt_check->close();
        echo json_encode(['success' => false, 'message' => 'Ce QR code a déjà été enregistré.']);
        exit;
    }
    $stmt_check->close();

    // Si l'URL commence par https ou http et contient '=', extraire ce qui est après le dernier '='
    $nom = $nom_origine;
    if ((strpos($nom_origine, 'https') === 0 || strpos($nom_origine, 'http') === 0) && strpos($nom_origine, '=') !== false) {
        $nom = substr($nom_origine, strrpos($nom_origine, '=') + 1);
    }

    // Préparer et exécuter la requête d'insertion
    $stmt = $conx->prepare("INSERT INTO collections (nom, valeur) VALUES (?, ?)");
    if ($stmt === false) {
        echo json_encode(['success' => false, 'message' => 'Erreur de préparation: ' . $conx->error]);
        exit;
    }
    $stmt->bind_param("si", $nom, $valeur);
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Résultat enregistré avec succès',
            'id' => $stmt->insert_id
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'enregistrement: ' . $stmt->error]);
    }
    $stmt->close();
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scanner QR Code v1.1</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.4/html5-qrcode.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            padding: 30px;
            max-width: 600px;
            width: 100%;
        }
        
        h1 {
            color: #333;
            margin-bottom: 10px;
            text-align: center;
            font-size: 28px;
        }
        
        .subtitle {
            color: #666;
            text-align: center;
            margin-bottom: 30px;
            font-size: 14px;
        }
        
        #qr-reader {
            width: 100%;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .button-group {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        button {
            flex: 1;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        
        button:active {
            transform: translateY(0);
        }
        
        button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: none;
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .alert.show {
            display: block;
        }
        
        .alert-success {
            background: #c8e6c9;
            color: #2e7d32;
            border-left: 4px solid #4caf50;
        }
        
        .alert-error {
            background: #ffcdd2;
            color: #c62828;
            border-left: 4px solid #f44336;
        }
        
        .alert-info {
            background: #bbdefb;
            color: #1565c0;
            border-left: 4px solid #2196f3;
        }
        
        .result {
            margin-top: 30px;
            padding: 20px;
            background: #f5f5f5;
            border-radius: 8px;
            border-left: 4px solid #4caf50;
            animation: slideIn 0.3s ease;
        }
        
        .result h3 {
            color: #333;
            margin-bottom: 15px;
            font-size: 16px;
        }
        
        .result-content {
            background: white;
            padding: 15px;
            border-radius: 5px;
            word-break: break-all;
            color: #666;
            font-family: 'Courier New', monospace;
            min-height: 30px;
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #ddd;
        }
        
        .result-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .copy-btn, .open-btn {
            flex: 1;
            padding: 10px;
            font-size: 14px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .copy-btn {
            background: #4caf50;
            color: white;
        }
        
        .copy-btn:hover {
            background: #45a049;
        }
        
        .open-btn {
            background: #2196f3;
            color: white;
        }
        
        .open-btn:hover {
            background: #0b7dda;
        }
        
        .status {
            text-align: center;
            color: #999;
            font-size: 13px;
            margin-top: 15px;
        }
        
        .scanning {
            color: #4caf50;
            font-weight: 500;
        }
        
        @media (max-width: 600px) {
            .container {
                padding: 20px;
            }
            
            h1 {
                font-size: 24px;
            }
            
            button {
                font-size: 14px;
                padding: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📱 Scanner QR Code</h1>
        <p class="subtitle">Scannez un QR code avec votre caméra</p>
        
        <div class="alert alert-info" id="infoAlert">
            ℹ️ Activez la caméra pour commencer le scan
        </div>
        
        <div class="alert alert-success" id="successAlert" style="display: none;">
            ✓ QR code détecté!
        </div>
        
        <div class="alert alert-error" id="errorAlert" style="display: none;">
            <span id="errorMessage"></span>
        </div>
        
        <!-- Zone de prévisualisation de la caméra -->
        <div id="qr-reader"></div>
        
        <div class="button-group">
            <button id="stopBtn" class="btn-secondary" onclick="stopScanning()">⏹️ Arrêter</button>
        </div>
        
        <div class="status" id="status"></div>
        
        <!-- Affichage du résultat -->
        <div id="resultContainer" style="display: none;">
            <div class="result">
                <h3>📖 Contenu détecté:</h3>
                <div class="result-content" id="resultContent"></div>
                <div class="result-actions">
                    <button class="open-btn" onclick="saveResult()" id="saveBtn" style="background: #4caf50;">💾 Enregistrer</button>
                </div>
                <div id="saveStatus"></div>
            </div>
            <button onclick="resetScanner()" style="width: 100%; margin-top: 20px;">Nouveau scan</button>
        </div>
    </div>
    
    <script>
        let html5QrcodeScanner = null;
        let isScanning = false;
        let lastScannedValue = '';
        
        function startScanning() {
            document.getElementById('stopBtn').style.display = 'block';
            document.getElementById('infoAlert').style.display = 'none';
            document.getElementById('errorAlert').style.display = 'none';
            document.getElementById('resultContainer').style.display = 'none';
            document.getElementById('status').innerHTML = '<span class="scanning">📹 Caméra en cours...</span>';
            
            // Initialiser le scanner s'il n'existe pas
            if (!html5QrcodeScanner) {
                html5QrcodeScanner = new Html5Qrcode("qr-reader");
            }
            
            // Démarrer la caméra arrière
            html5QrcodeScanner.start(
                { facingMode: "environment" }, // Caméra arrière
                {
                    fps: 15,
                    qrbox: { width: 150, height: 150 },
                    disableFlip: false
                },
                onScanSuccess,
                onScanFailure
            ).catch(err => {
                showError('Erreur d\'accès à la caméra: ' + err);
                document.getElementById('stopBtn').style.display = 'none';
                document.getElementById('status').innerHTML = '';
            });
            
            isScanning = true;
        }
        
        function stopScanning() {
            if (html5QrcodeScanner) {
                html5QrcodeScanner.stop().then(() => {
                    document.getElementById('stopBtn').style.display = 'none';
                    document.getElementById('status').innerHTML = '';
                    isScanning = false;
                });
            }
        }
        
        function onScanSuccess(decodedText, decodedResult) {
            // Éviter les doublons rapides
            if (decodedText === lastScannedValue) {
                return;
            }
            
            lastScannedValue = decodedText;
            
            // Afficher le résultat
            showResult(decodedText);
            
            // Arrêter le scan après détection
            stopScanning();
        }
        
        function onScanFailure(error) {
            // Les erreurs de lecture sont normales, on les ignore
        }
        
        function showResult(content) {
            document.getElementById('successAlert').style.display = 'block';
            document.getElementById('resultContent').textContent = content;
            document.getElementById('resultContainer').style.display = 'block';
            
            // Bouton Ouvrir supprimé
        }
        
        function showError(message) {
            document.getElementById('errorMessage').textContent = '✗ ' + message;
            document.getElementById('errorAlert').style.display = 'block';
        }
        
        // Fonctions Copier et Ouvrir supprimées
        
        function saveResult() {
            const content = document.getElementById('resultContent').textContent;
            const btn = document.getElementById('saveBtn');
            const saveStatus = document.getElementById('saveStatus');
            
            btn.disabled = true;
            btn.textContent = '⏳ Enregistrement...';
            
            // Envoyer les données au serveur
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=save&nom=' + encodeURIComponent(content)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    saveStatus.innerHTML = '<div style="color: #4caf50; margin-top: 10px; font-weight: 500;">✓ Enregistré avec succès! (ID: ' + data.id + ')</div>';
                    btn.textContent = '✓ Enregistré';
                    btn.style.background = '#45a049';
                    setTimeout(() => {
                        resetScanner();
                    }, 1200); // Relance un scan après 1,2s
                } else {
                    saveStatus.innerHTML = '<div style="color: #f44336; margin-top: 10px; font-weight: 500;">✗ Erreur: ' + data.message + '</div>';
                    btn.textContent = '💾 Enregistrer';
                    btn.disabled = false;
                    btn.style.background = '#4caf50';
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                saveStatus.innerHTML = '<div style="color: #f44336; margin-top: 10px; font-weight: 500;">✗ Erreur de connexion</div>';
                btn.textContent = '💾 Enregistrer';
                btn.disabled = false;
                btn.style.background = '#4caf50';
            });
        }
        
        function resetScanner() {
            document.getElementById('successAlert').style.display = 'none';
            document.getElementById('resultContainer').style.display = 'none';
            lastScannedValue = '';
            // Réactiver le bouton Enregistrer
            const btn = document.getElementById('saveBtn');
            if (btn) {
                btn.disabled = false;
                btn.textContent = '💾 Enregistrer';
                btn.style.background = '#4caf50';
            }
            document.getElementById('saveStatus').innerHTML = '';
            startScanning();
        }
        
        // Démarrer le scanning automatiquement au chargement
        window.addEventListener('load', () => {
            startScanning();
        });
    </script>
</body>
</html>
