<?php
/**
 * Script pour vérifier si un QR code existe dans la table collections
 * Scanne via la caméra et vérifie l'existence dans la BD
 */

// Capture tout output parasite avant toute inclusion
ob_start();

// Détecter si la requête attend du JSON (appel fetch)
$__is_api = ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify');

// Gestion de fin pour attraper une erreur fatale et renvoyer du JSON propre
if ($__is_api) {
    register_shutdown_function(function() use ($__is_api) {
        if (!$__is_api) {
            return;
        }
        $err = error_get_last();
        if ($err && !headers_sent()) {
            $payload = [
                'success' => false,
                'message' => 'Erreur fatale côté serveur',
                'debug'   => $err['message'] . ' in ' . $err['file'] . ':' . $err['line'],
            ];
            $buffer = ob_get_contents();
            if ($buffer !== false) {
                $buffer = trim($buffer);
                if ($buffer !== '') {
                    $payload['debug_buffer'] = $buffer;
                }
            }
            ob_clean();
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($payload);
        }
    });
}

// Inclure le fichier de configuration
$config_path = dirname(dirname(__FILE__)) . '/config.php';

if (!file_exists($config_path)) {
    die('Erreur: Fichier config.php non trouvé à ' . $config_path);
}

require_once $config_path;

function respondJson(array $payload) {
    $buffer = ob_get_contents();
    if ($buffer !== false) {
        $buffer = trim($buffer);
        if ($buffer !== '') {
            $payload['debug'] = $buffer;
        }
    }
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

// Traiter l'AJAX pour vérifier le résultat
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify') {
    // Vérifier la connexion
    if (!$conx) {
        respondJson(['success' => false, 'message' => 'Erreur de connexion à la base de données: ' . mysqli_connect_error()]);
    }
    
    $nom = isset($_POST['nom']) ? trim($_POST['nom']) : '';
    
    if (empty($nom)) {
        respondJson(['success' => false, 'message' => 'Le contenu est vide']);
    }
    
    // Si l'URL commence par https ou http et contient '=', extraire ce qui est après le dernier '='
    if ((strpos($nom, 'https') === 0 || strpos($nom, 'http') === 0) && strpos($nom, '=') !== false) {
        $nom = substr($nom, strrpos($nom, '=') + 1);
    }
    
    // Préparer et exécuter la requête de recherche avec le nom du joueur depuis membres.pseudo
    $stmt = $conx->prepare("
        SELECT c.id_collection, c.nom, c.valeur, COALESCE(m.pseudo, 'Non attribué') as nom_joueur, COALESCE(ci.date, '') as date_attribution, COALESCE(ci.`id-indiv`, 0) as id_membre,
               COALESCE((SELECT p.jetons_bonus_ins FROM participation p WHERE p.`id-membre` = ci.`id-indiv` ORDER BY p.`id-participation` DESC LIMIT 1), 0) as jetons_bonus_ins
        FROM collections c
        LEFT JOIN `collections-individu` ci ON c.id_collection = ci.id_col
        LEFT JOIN membres m ON ci.`id-indiv` = m.`id-membre`
        WHERE c.nom = ?
        ORDER BY c.id_collection DESC
        LIMIT 1
    ");
    
    if ($stmt === false) {
        respondJson(['success' => false, 'message' => 'Erreur de préparation: ' . $conx->error]);
    }
    
    $stmt->bind_param("s", $nom);
    
    if ($stmt->execute()) {
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $stmt->bind_result($id, $nomResult, $valeur, $nomJoueur, $dateAttribution, $idMembre, $jetonsBonus);
            $stmt->fetch();

            // Compter les tickets distribués ce mois-ci pour ce joueur
            $ticketsMonth = 0;
            $ticketsPrevMonth = 0;
            if ($idMembre > 0) {
                $stmtCount = $conx->prepare("
                    SELECT COUNT(*) FROM `collections-individu`
                    WHERE `id-indiv` = ?
                    AND MONTH(`date`) = MONTH(NOW())
                    AND YEAR(`date`) = YEAR(NOW())
                ");
                if ($stmtCount) {
                    $stmtCount->bind_param('i', $idMembre);
                    $stmtCount->execute();
                    $stmtCount->bind_result($ticketsMonth);
                    $stmtCount->fetch();
                    $stmtCount->close();
                }
                $stmtPrev = $conx->prepare("
                    SELECT COUNT(*) FROM `collections-individu`
                    WHERE `id-indiv` = ?
                    AND MONTH(`date`) = MONTH(DATE_SUB(NOW(), INTERVAL 1 MONTH))
                    AND YEAR(`date`)  = YEAR(DATE_SUB(NOW(), INTERVAL 1 MONTH))
                ");
                if ($stmtPrev) {
                    $stmtPrev->bind_param('i', $idMembre);
                    $stmtPrev->execute();
                    $stmtPrev->bind_result($ticketsPrevMonth);
                    $stmtPrev->fetch();
                    $stmtPrev->close();
                }
                // Tickets mois précédent avec jetons_bonus_ins = 5000
                $ticketsPrevMonth5000 = 0;
                $stmtPrev5k = $conx->prepare("
                    SELECT COUNT(*) FROM `collections-individu` ci
                    JOIN participation p ON p.`id-membre` = ci.`id-indiv`
                        AND p.jetons_bonus_ins = 5000
                    WHERE ci.`id-indiv` = ?
                    AND MONTH(ci.`date`) = MONTH(DATE_SUB(NOW(), INTERVAL 1 MONTH))
                    AND YEAR(ci.`date`)  = YEAR(DATE_SUB(NOW(), INTERVAL 1 MONTH))
                ");
                if ($stmtPrev5k) {
                    $stmtPrev5k->bind_param('i', $idMembre);
                    $stmtPrev5k->execute();
                    $stmtPrev5k->bind_result($ticketsPrevMonth5000);
                    $stmtPrev5k->fetch();
                    $stmtPrev5k->close();
                }
            }

            respondJson([
                'success' => true,
                'found' => true,
                'message' => 'QR code trouvé!',
                'id' => $id,
                'nom' => $nomResult,
                'valeur' => $valeur,
                'nom_joueur' => $nomJoueur,
                'date_attribution' => $dateAttribution,
                'jetons_bonus_ins' => $jetonsBonus,
                'tickets_this_month' => $ticketsMonth,
                'tickets_prev_month' => $ticketsPrevMonth,
                'tickets_prev_month_5000' => $ticketsPrevMonth5000
            ]);
        } else {
            respondJson([
                'success' => true,
                'found' => false,
                'message' => 'QR code non trouvé dans la base de données'
            ]);
        }
    } else {
        respondJson(['success' => false, 'message' => 'Erreur lors de la vérification: ' . $stmt->error]);
    }
    
    $stmt->close();
    respondJson(['success' => false, 'message' => 'Réponse inattendue']);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vérifier QR Code</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.4/html5-qrcode.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #2196f3 0%, #1976d2 100%);
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
            background: linear-gradient(135deg, #2196f3 0%, #1976d2 100%);
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
            box-shadow: 0 5px 20px rgba(33, 150, 243, 0.4);
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
            background: linear-gradient(135deg, #ff6f00 0%, #e65100 100%);
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
        
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border-left: 4px solid #ffc107;
        }
        
        .result {
            margin-top: 30px;
            padding: 20px;
            border-radius: 8px;
            animation: slideIn 0.3s ease;
        }
        
        .result.found {
            background: #c8e6c9;
            border-left: 4px solid #4caf50;
        }
        
        .result.not-found {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
        }
        
        .result h3 {
            color: #333;
            margin-bottom: 15px;
            font-size: 18px;
        }
        
        .result-content {
            background: white;
            padding: 15px;
            border-radius: 5px;
            word-break: break-all;
            color: #666;
            font-family: 'Courier New', monospace;
            border: 1px solid #ddd;
        }
        
        .result-meta {
            margin-top: 15px;
            padding: 10px;
            background: rgba(255, 255, 255, 0.5);
            border-radius: 5px;
            font-size: 14px;
        }
        
        .status {
            text-align: center;
            color: #999;
            font-size: 13px;
            margin-top: 15px;
        }
        
        .scanning {
            color: #2196f3;
            font-weight: 500;
        }
        
        .result-icon {
            font-size: 40px;
            text-align: center;
            margin-bottom: 15px;
        }

        .debug-box {
            display: none;
            margin-top: 15px;
            padding: 12px;
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            color: #6b4c00;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            word-break: break-all;
            white-space: pre-wrap;
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
        <h1>🔍 Vérifier QR Code</h1>
        <p class="subtitle">Scannez un QR code pour vérifier s'il existe dans la base de données</p>
        
        <div class="alert alert-info" id="infoAlert">
            ℹ️ Activez la caméra pour commencer la vérification
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
        <div class="debug-box" id="debugBox"></div>
        
        <!-- Affichage du résultat -->
        <div id="resultContainer" style="display: none;">
            <div class="result" id="resultDiv">
                <div class="result-icon" id="resultIcon"></div>
                <h3 id="resultTitle"></h3>
                <div class="result-content" id="resultContent"></div>
                <div class="result-meta" id="resultMeta" style="display: none;"></div>
            </div>
            <button onclick="resetScanner()" style="width: 100%; margin-top: 20px;">Nouvelle vérification</button>
        </div>
    </div>
    
    <script>
        let html5QrcodeScanner = null;
        let isScanning = false;
        let lastScannedValue = '';
        const debugBox = document.getElementById('debugBox');

        function clearDebug() {
            debugBox.style.display = 'none';
            debugBox.textContent = '';
        }

        function showDebug(text) {
            debugBox.style.display = 'block';
            debugBox.textContent = text;
        }
        
        function startScanning() {
            document.getElementById('stopBtn').style.display = 'block';
            document.getElementById('infoAlert').style.display = 'none';
            document.getElementById('errorAlert').style.display = 'none';
            document.getElementById('resultContainer').style.display = 'none';
            clearDebug();
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
            
            // Arrêter le scan
            stopScanning();
            
            // Vérifier dans la BD
            verifyQRCode(decodedText);
        }
        
        function onScanFailure(error) {
            // Les erreurs de lecture sont normales, on les ignore
        }
        
        function verifyQRCode(content) {
            document.getElementById('status').innerHTML = '<span class="scanning">⏳ Vérification en cours...</span>';
            clearDebug();
            
            // Envoyer les données au serveur
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=verify&nom=' + encodeURIComponent(content)
            })
            .then(async (response) => {
                const text = await response.text();
                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    const statusInfo = `HTTP ${response.status} ${response.statusText || ''}`.trim();
                    showDebug((statusInfo ? statusInfo + "\n" : '') + (text || 'Réponse vide'));
                    throw new Error('Réponse non JSON');
                }
                
                document.getElementById('status').innerHTML = '';
                if (data.debug) {
                    showDebug(typeof data.debug === 'string' ? data.debug : JSON.stringify(data.debug));
                }
                
                if (data.success) {
                    if (data.found) {
                        showFoundResult(data.id, data.nom, data.valeur, data.nom_joueur, data.date_attribution, data.jetons_bonus_ins, data.tickets_this_month, data.tickets_prev_month, data.tickets_prev_month_5000);
                    } else {
                        showNotFoundResult(content);
                    }
                } else {
                    showError(data.message || 'Erreur serveur');
                }
            })
            .catch(error => {
                showDebug(error && error.message ? error.message : String(error));
                showError('Erreur de connexion au serveur');
                document.getElementById('status').innerHTML = '';
            });
        }
        
        function showFoundResult(id, nom, valeur, nomJoueur, dateAttribution, jetonsBonus, ticketsMonth, ticketsPrevMonth, ticketsPrevMonth5000) {
            const resultDiv = document.getElementById('resultDiv');
            resultDiv.className = 'result found';
            
            document.getElementById('resultIcon').textContent = '✅';
            document.getElementById('resultTitle').textContent = 'QR Code trouvé! - Appartient à: ' + nomJoueur;
            document.getElementById('resultContent').textContent = nom;
            
            const metaDiv = document.getElementById('resultMeta');
            var metaHtml = '<strong>ID:</strong> ' + id + '<br><strong>Points:</strong> ' + valeur + '<br><strong>Joueur:</strong> ' + nomJoueur + '<br><strong>Statut:</strong> Enregistré dans la base de données';
            if (dateAttribution) {
                metaHtml += '<br><strong>Date:</strong> ' + dateAttribution + ' &nbsp; <strong>Jetons bonus ins:</strong> ' + (jetonsBonus || 0);
            }
            var now  = new Date();
            var prev = new Date(now.getFullYear(), now.getMonth() - 1, 1);
            var mois     = now.toLocaleString('fr-FR', { month: 'long', year: 'numeric' });
            var moisPrev = prev.toLocaleString('fr-FR', { month: 'long', year: 'numeric' });
            metaHtml += '<br><strong>Tickets ce mois (' + mois + '):</strong> ' + (ticketsMonth || 0);
            metaHtml += '<br><strong>Tickets mois précédent (' + moisPrev + '):</strong> ' + (ticketsPrevMonth || 0) + ' <em>(dont ' + (ticketsPrevMonth5000 || 0) + ' à 5000 jetons)</em>';
            metaDiv.innerHTML = metaHtml;
            metaDiv.style.display = 'block';
            
            document.getElementById('resultContainer').style.display = 'block';
        }
        
        function showNotFoundResult(content) {
            const resultDiv = document.getElementById('resultDiv');
            resultDiv.className = 'result not-found';
            
            document.getElementById('resultIcon').textContent = '⚠️';
            document.getElementById('resultTitle').textContent = 'QR Code NON trouvé';
            document.getElementById('resultContent').textContent = content;
            
            const metaDiv = document.getElementById('resultMeta');
            metaDiv.innerHTML = '<strong>Statut:</strong> Ce QR code n\'est pas enregistré dans la base de données';
            metaDiv.style.display = 'block';
            
            document.getElementById('resultContainer').style.display = 'block';
        }
        
        function showError(message) {
            document.getElementById('errorMessage').textContent = '✗ ' + message;
            document.getElementById('errorAlert').style.display = 'block';
        }
        
        function resetScanner() {
            document.getElementById('errorAlert').style.display = 'none';
            document.getElementById('resultContainer').style.display = 'none';
            lastScannedValue = '';
            startScanning();
        }
        
        // Démarrer le scanning automatiquement au chargement
        window.addEventListener('load', () => {
            startScanning();
        });
    </script>
</body>
</html>
