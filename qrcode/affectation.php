<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Config DB
$config_path = dirname(__DIR__) . '/config.php';
if (!file_exists($config_path)) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Config introuvable: ' . $config_path]);
    exit;
}
require_once $config_path; // apporte $conx

if (!$conx) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Erreur de connexion DB: ' . mysqli_connect_error()]);
    exit;
}
mysqli_set_charset($conx, 'utf8mb4');

// API pour récupérer les QR codes disponibles
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_available_qrcodes') {
    header('Content-Type: application/json; charset=utf-8');
    
    try {
        // Récupérer les QR codes non attribués
        $stmt = $conx->prepare('SELECT c.id_collection, c.nom, c.valeur FROM collections c LEFT JOIN `collections-individu` ci ON c.id_collection = ci.id_col WHERE ci.id IS NULL ORDER BY c.id_collection ASC');
        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => 'Erreur SQL: ' . $conx->error]);
            exit;
        }
        $stmt->execute();
        $qrcodes_res = $stmt->get_result();
        $qrcodes = [];
        
        while ($qr = $qrcodes_res->fetch_assoc()) {
            $qrcodes[] = [
                'id' => $qr['id_collection'],
                'nom' => $qr['nom'],
                'valeur' => $qr['valeur']
            ];
        }
        $stmt->close();
        
        echo json_encode(['success' => true, 'qrcodes' => $qrcodes, 'count' => count($qrcodes)]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()]);
    }
    exit;
}

// API pour récupérer les activités
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_activities') {
    header('Content-Type: application/json; charset=utf-8');
    
    try {
        // Récupérer les activités dans une fenêtre de 9 jours (-6 jours à +3 jours)
        $stmt = $conx->prepare('SELECT `id-activite`, `titre-activite`, `date_depart`, `heure_depart`, `ville`, `places`, `reserves` FROM activite WHERE `date_depart` >= DATE_SUB(NOW(), INTERVAL 36 DAY) AND `date_depart` <= DATE_ADD(NOW(), INTERVAL 33 DAY) ORDER BY `date_depart` DESC, `heure_depart` DESC');
        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => 'Erreur SQL: ' . $conx->error]);
            exit;
        }
        $stmt->execute();
        $activities_res = $stmt->get_result();
        $activities = [];
    
        while ($activity = $activities_res->fetch_assoc()) {
                $activity_id = intval($activity['id-activite']);
        
                // Récupérer les participants réellement rattachés à cette activité :
                // on exclut uniquement les statuts d'annulation / désinscription / en attente (Option).
                $stmt_participants = $conx->prepare('SELECT m.`id-membre`, m.pseudo, p.option
                                                                                            FROM participation p
                                                                                            INNER JOIN membres m ON p.`id-membre` = m.`id-membre`
                                                                                            WHERE p.`id-activite` = ?
                                                                                                AND (p.option IS NULL OR p.option NOT IN ("Annule", "Desinscrit", "None", "Option"))
                                                                                            ORDER BY m.pseudo ASC');
                $stmt_participants->bind_param('i', $activity_id);
        $stmt_participants->execute();
        $participants_res = $stmt_participants->get_result();
        
        $participants = [];
        while ($participant = $participants_res->fetch_assoc()) {
            $participants[] = [
                'id' => $participant['id-membre'],
                'pseudo' => $participant['pseudo'],
                'status' => $participant['option']
            ];
        }
        $stmt_participants->close();
        
        $activities[] = [
            'id' => $activity_id,
            'titre' => $activity['titre-activite'],
            'date' => $activity['date_depart'],
            'heure' => $activity['heure_depart'],
            'ville' => $activity['ville'],
            'places' => intval($activity['places']),
            'reserves' => intval($activity['reserves']),
            'participants' => $participants
        ];
    }
    $stmt->close();
    
    echo json_encode(['success' => true, 'activities' => $activities]);
    
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()]);
    }
    exit;
}

// API pour attribuer automatiquement les QR codes aux participants
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'assign_qrcodes') {
    header('Content-Type: application/json; charset=utf-8');
    
    $activity_id = isset($_POST['activity_id']) ? intval($_POST['activity_id']) : 0;
    
    if ($activity_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID activité invalide.']);
        exit;
    }
    
    try {
        // Récupérer les informations de l'activité
        $stmt_activity = $conx->prepare('SELECT `titre-activite`, `date_depart` FROM activite WHERE `id-activite` = ?');
        $stmt_activity->bind_param('i', $activity_id);
        $stmt_activity->execute();
        $activity_res = $stmt_activity->get_result();
        $activity_info = $activity_res->fetch_assoc();
        $stmt_activity->close();
        
        if (!$activity_info) {
            echo json_encode(['success' => false, 'message' => 'Activité introuvable.']);
            exit;
        }
        
        $activity_title = $activity_info['titre-activite'];
        $activity_date = date('d/m/Y', strtotime($activity_info['date_depart']));
        $activity_date_full = $activity_info['date_depart']; // Date complète pour la BDD
        $co_value = "Activité #$activity_id - $activity_title - $activity_date";
        
                // Récupérer les participants de l'activité (même logique que pour l'affichage) :
                // on exclut uniquement Annule / Desinscrit / None / Option.
                $stmt = $conx->prepare('SELECT m.`id-membre`, m.pseudo
                                                                 FROM participation p
                                                                 INNER JOIN membres m ON p.`id-membre` = m.`id-membre`
                                                                 WHERE p.`id-activite` = ?
                                                                     AND (p.option IS NULL OR p.option NOT IN ("Annule", "Desinscrit", "None", "Option"))
                                                                 ORDER BY m.pseudo ASC');
        $stmt->bind_param('i', $activity_id);
        $stmt->execute();
        $participants_res = $stmt->get_result();
        $participants = [];
        while ($p = $participants_res->fetch_assoc()) {
            $participants[] = $p;
        }
        $stmt->close();
        
        if (empty($participants)) {
            echo json_encode(['success' => false, 'message' => 'Aucun participant inscrit à cette activité.']);
            exit;
        }
        
        $assigned = 0;
        $already_assigned = 0;
        $errors = [];
        
        foreach ($participants as $participant) {
            $member_id = intval($participant['id-membre']);
            
            // Exclure l'utilisateur 265 de l'attribution automatique
            if ($member_id === 265) {
                continue;
            }
            
            // Vérifier si le membre a déjà un QR code pour CETTE activité spécifique
            $stmt_check = $conx->prepare('SELECT COUNT(*) as count FROM `collections-individu` WHERE `id-indiv` = ? AND `date` = ?');
            $stmt_check->bind_param('is', $member_id, $activity_date_full);
            $stmt_check->execute();
            $check_res = $stmt_check->get_result();
            $has_qr = $check_res->fetch_assoc()['count'] > 0;
            $stmt_check->close();
            
            if ($has_qr) {
                $already_assigned++;
                continue;
            }
            
            // Trouver un QR code disponible (non attribué)
            $stmt_qr = $conx->prepare('SELECT c.id_collection, c.nom, c.valeur FROM collections c LEFT JOIN `collections-individu` ci ON c.id_collection = ci.id_col WHERE ci.id IS NULL LIMIT 1');
            $stmt_qr->execute();
            $qr_res = $stmt_qr->get_result();
            $qr = $qr_res->fetch_assoc();
            $stmt_qr->close();
            
            if (!$qr) {
                $errors[] = $participant['pseudo'] . ': Aucun QR code disponible';
                continue;
            }
            
            // Attribuer le QR code
            $qr_id = intval($qr['id_collection']);
            $qr_valeur = isset($qr['valeur']) ? intval($qr['valeur']) : 1;
            $stmt_assign = $conx->prepare('INSERT INTO `collections-individu` (id_col, `id-indiv`, co, valeur, `date`) VALUES (?, ?, ?, ?, ?)');
            $stmt_assign->bind_param('iisis', $qr_id, $member_id, $co_value, $qr_valeur, $activity_date_full);
            if ($stmt_assign->execute()) {
                $assigned++;
            } else {
                $errors[] = $participant['pseudo'] . ': Erreur d\'attribution';
            }
            $stmt_assign->close();
        }
        
        $message = "Attribution terminée: $assigned attribué(s)";
        if ($already_assigned > 0) {
            $message .= ", $already_assigned déjà attribué(s)";
        }
        if (!empty($errors)) {
            $message .= ". Erreurs: " . implode(', ', $errors);
        }
        
        echo json_encode([
            'success' => true,
            'message' => $message,
            'assigned' => $assigned,
            'already_assigned' => $already_assigned,
            'errors' => $errors
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()]);
    }
    exit;
}

// API d'affectation via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'link') {
    header('Content-Type: application/json; charset=utf-8');

    $wallet_code = isset($_POST['wallet']) ? trim($_POST['wallet']) : '';
    $member_code = isset($_POST['member']) ? trim($_POST['member']) : '';

    if ($wallet_code === '' || $member_code === '') {
        echo json_encode(['success' => false, 'message' => 'Deux scans sont requis.']);
        exit;
    }

    // Extraction du contenu du portefeuille (après le dernier '=')
    if (strpos($wallet_code, '=') !== false) {
        $wallet_code = substr($wallet_code, strrpos($wallet_code, '=') + 1);
    }

    $pseudo = '';
    $passwd = '';
    if (strpos($member_code, 'pseudo=') !== false) {
        preg_match('/pseudo=([^&]+)/', $member_code, $m_pseudo);
        $pseudo = isset($m_pseudo[1]) ? urldecode($m_pseudo[1]) : '';
        preg_match('/passwd=([^&]+)/', $member_code, $m_passwd);
        $passwd = isset($m_passwd[1]) ? urldecode($m_passwd[1]) : '';
    } else {
        $pseudo = $member_code;
    }

    // Trouver le portefeuille (collections)
    $stmt = $conx->prepare('SELECT id_collection, nom, valeur FROM collections WHERE id_collection = ? OR nom = ? LIMIT 1');
    $wallet_id_int = ctype_digit($wallet_code) ? intval($wallet_code) : 0;
    $stmt->bind_param('is', $wallet_id_int, $wallet_code);
    $stmt->execute();
    $wallet_res = $stmt->get_result();
    $wallet = $wallet_res ? $wallet_res->fetch_assoc() : null;
    $stmt->close();

    if (!$wallet) {
        echo json_encode(['success' => false, 'message' => 'Carte portefeuille introuvable.']);
        exit;
    }

    $wallet_valeur = isset($wallet['valeur']) ? intval($wallet['valeur']) : 1;

    // Trouver le membre
    if ($passwd !== '') {
        $stmt = $conx->prepare('SELECT `id-membre`, pseudo, email FROM membres WHERE (`id-membre` = ? OR pseudo = ?) AND (`password` = ? OR `password_ext` = ?) LIMIT 1');
        $member_id_int = ctype_digit($pseudo) ? intval($pseudo) : 0;
        $stmt->bind_param('isss', $member_id_int, $pseudo, $passwd, $passwd);
    } else {
        $stmt = $conx->prepare('SELECT `id-membre`, pseudo, email FROM membres WHERE `id-membre` = ? OR pseudo = ? OR email = ? LIMIT 1');
        $member_id_int = ctype_digit($pseudo) ? intval($pseudo) : 0;
        $stmt->bind_param('iss', $member_id_int, $pseudo, $pseudo);
    }
    
    $stmt->execute();
    $member_res = $stmt->get_result();
    $member = $member_res ? $member_res->fetch_assoc() : null;
    $stmt->close();

    if (!$member) {
        echo json_encode(['success' => false, 'message' => 'Membre introuvable ou mauvais mot de passe.']);
        exit;
    }

    $wallet_id = intval($wallet['id_collection']);
    $member_id = intval($member['id-membre']);

    // Vérifier si l'association existe déjà
    $stmt = $conx->prepare('SELECT id FROM `collections-individu` WHERE id_col = ? AND `id-indiv` = ? LIMIT 1');
    $stmt->bind_param('ii', $wallet_id, $member_id);
    $stmt->execute();
    $exists_res = $stmt->get_result();
    $already = $exists_res && $exists_res->num_rows > 0;
    $stmt->close();

    if ($already) {
        echo json_encode([
            'success' => true,
            'message' => 'Association déjà présente.',
            'wallet_id' => $wallet_id,
            'wallet_nom' => $wallet['nom'],
            'member_id' => $member_id,
            'member_pseudo' => $member['pseudo']
        ]);
        exit;
    }

    // Insérer l'association
    $stmt = $conx->prepare('INSERT INTO `collections-individu` (id_col, `id-indiv`, co, valeur) VALUES (?, ?, "Auto", ?)');
    $stmt->bind_param('iii', $wallet_id, $member_id, $wallet_valeur);
    $ok = $stmt->execute();
    $stmt->close();

    if ($ok) {
        echo json_encode([
            'success' => true,
            'message' => 'Association créée avec succès.',
            'wallet_id' => $wallet_id,
            'wallet_nom' => $wallet['nom'],
            'member_id' => $member_id,
            'member_pseudo' => $member['pseudo']
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la création de l\'association.']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Affectation Portefeuille v1.2</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.4/html5-qrcode.min.js"></script>
    <style>
        body {font-family: Arial, sans-serif; background: #f5f7fb; margin: 0; padding: 20px;}
        .container {max-width: 760px; margin: 0 auto; background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 12px 36px rgba(0,0,0,0.08);}    
        h1 {margin-top: 0; color: #222; text-align: center;}
        .actions {display: flex; gap: 12px; margin-bottom: 16px; flex-wrap: wrap;}
        button {padding: 12px 18px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; color: #fff; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);}    
        button.secondary {background: #f05a7e;}
        button:disabled {opacity: 0.6; cursor: not-allowed;}
        #qr-reader {width: 100%; max-width: 520px; margin: 0 auto 12px;}
        .status {text-align: center; margin-bottom: 12px; color: #555;}
        .pill {display: inline-block; padding: 8px 12px; border-radius: 999px; background: #eef2ff; color: #4c51bf; margin: 4px;}
        .result-box {background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px; margin-top: 8px;}
        .alert {padding: 12px 14px; border-radius: 8px; margin-top: 12px; display: none;}
        .alert.success {background: #e6fffa; color: #0f766e; border: 1px solid #99f6e4;}
        .alert.error {background: #fff5f5; color: #c53030; border: 1px solid #fed7d7;}
        .activity-card {background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px; margin-bottom: 12px; cursor: pointer; transition: all 0.2s;}
        .activity-card:hover {background: #eef2ff; border-color: #c7d2fe;}
        .activity-header {display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;}
        .activity-title {font-weight: 600; color: #1e293b; font-size: 16px;}
        .activity-date {color: #64748b; font-size: 14px;}
        .activity-info {color: #475569; font-size: 14px; margin-top: 4px;}
        .participants-list {margin-top: 12px; padding-top: 12px; border-top: 1px solid #e2e8f0; display: none;}
        .participants-list.show {display: block;}
        .participant-item {display: inline-block; padding: 6px 12px; margin: 4px; background: #dbeafe; color: #1e40af; border-radius: 6px; font-size: 13px;}
        .participant-item.option {background: #fef3c7; color: #92400e;}
        .no-participants {color: #94a3b8; font-style: italic; font-size: 14px;}
        .activity-stats {color: #64748b; font-size: 13px; margin-top: 8px;}
    </style>
</head>
<body>
    <div class="container">
        <h1>Associer une carte portefeuille à un membre</h1>
        <p class="status">Étape 1 : scanner le QR de la carte portefeuille, puis Étape 2 : scanner le QR du membre.</p>

        <div id="qr-reader"></div>

        <div class="actions">
            <button id="scanWalletBtn" onclick="startScan('wallet')">Scanner portefeuille</button>
            <button id="scanMemberBtn" class="secondary" onclick="startScan('member')">Scanner membre</button>
            <button id="stopBtn" onclick="stopScan()" style="display:none; background:#6b7280;">Arrêter</button>
            <button onclick="showActivities()" style="background:#10b981;">Voir les activités</button>
        </div>

        <div class="result-box">
            <div>Portefeuille scanné : <span id="walletValue" class="pill">—</span></div>
            <div>Membre scanné : <span id="memberValue" class="pill">—</span></div>
        </div>

        <div id="alert" class="alert"></div>
        
        <!-- Modal pour les activités -->
        <div id="activitiesModal" style="display:none; margin-top:20px;">
            <h2 style="margin-bottom:16px; color:#222;">Liste des activités</h2>
            <div id="activitiesList"></div>
        </div>
    </div>

    <script>
        let scanner = null;
        let currentStep = null;
        let walletCode = '';
        let memberCode = '';

        function startScan(step) {
            currentStep = step;
            showAlert('info', 'Caméra en cours...');
            document.getElementById('stopBtn').style.display = 'inline-block';

            if (!scanner) {
                scanner = new Html5Qrcode('qr-reader');
            }

            scanner.start(
                { facingMode: 'environment' },
                { fps: 15, qrbox: { width: 150, height: 150 }, disableFlip: false },
                onScanSuccess,
                () => {}
            ).catch(err => {
                showAlert('error', 'Impossible d\'accéder à la caméra: ' + err);
                document.getElementById('stopBtn').style.display = 'none';
            });
        }

        function stopScan() {
            if (scanner) {
                scanner.stop().then(() => {
                    document.getElementById('stopBtn').style.display = 'none';
                    showAlert('info', 'Caméra arrêtée');
                });
            }
        }

        function onScanSuccess(decodedText) {
            if (currentStep === 'wallet') {
                walletCode = decodedText.trim();
                document.getElementById('walletValue').textContent = walletCode;
                showAlert('success', 'Portefeuille scanné. Passez au membre.');
            } else if (currentStep === 'member') {
                memberCode = decodedText.trim();
                document.getElementById('memberValue').textContent = memberCode;
                showAlert('success', 'Membre scanné. Envoi en cours...');
            }

            stopScan();
            currentStep = null;
            maybeSendLink();
        }

        function maybeSendLink() {
            if (!walletCode || !memberCode) return;

            const params = new URLSearchParams();
            params.append('action', 'link');
            params.append('wallet', walletCode);
            params.append('member', memberCode);

            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params.toString()
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', data.message);
                } else {
                    showAlert('error', data.message || 'Erreur.');
                }
            })
            .catch(() => showAlert('error', 'Erreur réseau.'));
        }

        function showAlert(type, msg) {
            const box = document.getElementById('alert');
            box.style.display = 'block';
            box.className = 'alert ' + (type === 'success' ? 'success' : (type === 'error' ? 'error' : ''));
            box.textContent = msg;
        }

        function showActivities() {
            showAlert('info', 'Chargement des QR codes et activités...');
            
            // D'abord récupérer les QR codes disponibles
            const paramsQR = new URLSearchParams();
            paramsQR.append('action', 'get_available_qrcodes');
            
            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: paramsQR.toString()
            })
            .then(r => {
                if (!r.ok) {
                    throw new Error('HTTP ' + r.status);
                }
                return r.text();
            })
            .then(text => {
                try {
                    const dataQR = JSON.parse(text);
                    if (dataQR.success) {
                        displayQRCodes(dataQR.qrcodes, dataQR.count);
                        
                        // Ensuite charger les activités
                        const params = new URLSearchParams();
                        params.append('action', 'get_activities');
                        
                        return fetch(window.location.href, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: params.toString()
                        });
                    } else {
                        showAlert('error', dataQR.message || 'Erreur lors du chargement des QR codes.');
                        throw new Error('QR codes loading failed');
                    }
                } catch (e) {
                    console.error('Réponse serveur QR:', text);
                    showAlert('error', 'Réponse invalide du serveur: ' + text.substring(0, 100));
                    throw e;
                }
            })
            .then(r => {
                if (!r.ok) {
                    throw new Error('HTTP ' + r.status);
                }
                return r.text();
            })
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if (data.success && data.activities) {
                        displayActivities(data.activities);
                        document.getElementById('activitiesModal').style.display = 'block';
                        showAlert('success', data.activities.length + ' activité(s) trouvée(s).');
                    } else {
                        showAlert('error', data.message || 'Erreur lors du chargement des activités.');
                    }
                } catch (e) {
                    console.error('Réponse serveur:', text);
                    showAlert('error', 'Réponse invalide du serveur: ' + text.substring(0, 100));
                }
            })
            .catch(err => showAlert('error', 'Erreur réseau: ' + err.message));
        }

        function displayQRCodes(qrcodes, count) {
            const container = document.getElementById('activitiesList');
            
            let html = `
                <div style="background: #ecfdf5; border: 2px solid #10b981; border-radius: 10px; padding: 16px; margin-bottom: 20px;">
                    <h3 style="margin-top: 0; color: #065f46; font-size: 18px;">🎫 QR Codes Disponibles</h3>
                    <div style="font-size: 16px; color: #047857; font-weight: 600; margin-bottom: 12px;">
                        ${count} QR code(s) disponible(s)
                    </div>
            `;
            
            if (count > 0) {
                html += '<div style="display: flex; flex-wrap: wrap; gap: 8px;">';
                qrcodes.forEach(qr => {
                    html += `
                        <span style="display: inline-block; padding: 8px 12px; background: #d1fae5; color: #065f46; border-radius: 6px; font-size: 13px; font-weight: 500;">
                            ${qr.nom} (ID: ${qr.id})
                        </span>
                    `;
                });
                html += '</div>';
            } else {
                html += '<p style="color: #dc2626; font-weight: 500;">⚠️ Aucun QR code disponible</p>';
            }
            
            html += '</div>';
            container.innerHTML = html;
        }

        function displayActivities(activities) {
            const container = document.getElementById('activitiesList');
            
            if (activities.length === 0) {
                container.innerHTML += '<p class="no-participants">Aucune activité trouvée.</p>';
                return;
            }
            
            let html = '';
            activities.forEach((activity, index) => {
                const date = new Date(activity.date.replace(' ', 'T'));
                const dateStr = date.toLocaleDateString('fr-FR', { 
                    weekday: 'long', 
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric' 
                });
                const heureStr = date.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
                
                html += `
                    <div class="activity-card" onclick="toggleParticipants(${index})">
                        <div class="activity-header">
                            <div class="activity-title">${activity.titre}</div>
                            <div class="activity-date">${dateStr} à ${heureStr}</div>
                        </div>
                        <div class="activity-info">
                            <strong>Ville:</strong> ${activity.ville || 'Non spécifiée'}
                        </div>
                        <div class="activity-stats">
                            <strong>Places:</strong> ${activity.places} | 
                            <strong>Réservées:</strong> ${activity.reserves} | 
                            <strong>Inscrits:</strong> ${activity.participants.length}
                        </div>
                        <div style="margin-top: 12px;">
                            <button onclick="assignQRCodes(${activity.id}, event)" style="padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; color: #fff; background: #10b981; font-size: 13px;">
                                🎫 Attribuer QR codes aux joueurs
                            </button>
                        </div>
                        <div id="participants-${index}" class="participants-list">
                            <strong>Joueurs inscrits:</strong><br/>
                            ${activity.participants.length > 0 ? 
                                activity.participants.map(p => 
                                    `<span class="participant-item ${p.status === 'Option' ? 'option' : ''}">
                                        ${p.pseudo}
                                        ${p.status === 'Option' ? ' 🔸' : ''}
                                    </span>`
                                ).join('') 
                                : '<span class="no-participants">Aucun participant inscrit.</span>'
                            }
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML += html;
        }

        function toggleParticipants(index) {
            const elem = document.getElementById('participants-' + index);
            if (elem) {
                elem.classList.toggle('show');
            }
        }

        function assignQRCodes(activityId, event) {
            event.stopPropagation();
            
            if (!confirm('Voulez-vous attribuer automatiquement un QR code à chaque joueur inscrit qui n\'en a pas encore ?')) {
                return;
            }
            
            showAlert('info', 'Attribution en cours...');
            
            const params = new URLSearchParams();
            params.append('action', 'assign_qrcodes');
            params.append('activity_id', activityId);
            
            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params.toString()
            })
            .then(r => {
                if (!r.ok) {
                    throw new Error('HTTP ' + r.status);
                }
                return r.text();
            })
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        showAlert('success', data.message);
                    } else {
                        showAlert('error', data.message || 'Erreur lors de l\'attribution.');
                    }
                } catch (e) {
                    console.error('Réponse serveur:', text);
                    showAlert('error', 'Réponse invalide du serveur: ' + text.substring(0, 100));
                }
            })
            .catch(err => showAlert('error', 'Erreur réseau: ' + err.message));
        }
    </script>
</body>
</html>
