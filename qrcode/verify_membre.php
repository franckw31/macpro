<?php
session_start();
error_reporting(0);

$config_path = dirname(__DIR__) . '/config.php';
if (!file_exists($config_path)) die('config.php introuvable');
require_once $config_path;

if (!$conx) die('DB error');
mysqli_set_charset($conx, 'utf8mb4');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify') {
    header('Content-Type: application/json; charset=utf-8');
    $code = isset($_POST['code']) ? trim($_POST['code']) : '';
    if ($code === '') {
        echo json_encode(['success' => false, 'message' => 'Scan requis.']);
        exit;
    }
    
    $pseudo = '';
    $passwd = '';

    // Extraction pseudo et passwd du QR code (Format: ...?pseudo=XXX&passwd=YYY)
    if (strpos($code, 'pseudo=') !== false) {
        preg_match('/pseudo=([^&]+)/', $code, $m_pseudo);
        $pseudo = isset($m_pseudo[1]) ? urldecode($m_pseudo[1]) : '';
        
        preg_match('/passwd=([^&]+)/', $code, $m_passwd);
        $passwd = isset($m_passwd[1]) ? urldecode($m_passwd[1]) : '';
    } else {
        // Fallback si le QR code ne contient que le pseudo ou l'ID
        $pseudo = $code;
    }

    if ($pseudo === '') {
        echo json_encode(['success' => false, 'message' => 'Format QR code non reconnu.']);
        exit;
    }

    $id_int = ctype_digit($pseudo) ? intval($pseudo) : 0;
    
    if ($passwd !== '') {
        // Vérification avec Pseudo ET Mot de passe (plus sécurisé)
        $stmt = $conx->prepare('SELECT `id-membre`, `pseudo`, `email`, `fname`, `lname`, `password`, `password_ext` FROM membres WHERE (`id-membre` = ? OR `pseudo` = ?) AND (`password` = ? OR `password_ext` = ?) LIMIT 1');
        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => 'Erreur SQL: ' . $conx->error]);
            exit;
        }
        $stmt->bind_param('isss', $id_int, $pseudo, $passwd, $passwd);
    } else {
        // Vérification simple (compatibilité avec anciens QR codes)
        $stmt = $conx->prepare('SELECT `id-membre`, `pseudo`, `email`, `fname`, `lname`, `password`, `password_ext` FROM membres WHERE `id-membre` = ? OR `pseudo` = ? OR `email` = ? LIMIT 1');
        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => 'Erreur SQL: ' . $conx->error]);
            exit;
        }
        $stmt->bind_param('iss', $id_int, $pseudo, $pseudo);
    }

    $stmt->execute();
    $res = $stmt->get_result();
    $m = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    
    if (!$m) {
        echo json_encode(['success' => false, 'found' => false, 'message' => 'Membre non trouvé ou mot de passe invalide']);
        exit;
    }
    
    echo json_encode([
        'success' => true, 
        'found' => true, 
        'id' => intval($m['id-membre']), 
        'pseudo' => $m['pseudo'], 
        'email' => $m['email'], 
        'nom' => $m['lname'], 
        'prenom' => $m['fname'],
        'pass' => $m['password'],
        'pass_ext' => $m['password_ext']
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <title>Vérif Membre v2.8</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.4/html5-qrcode.min.js?v=4"></script>
    <style>
        * {margin: 0; padding: 0; box-sizing: border-box;}
        body {font-family: sans-serif; background: #2c3e50; color: #fff; padding: 15px; min-height: 100vh;}
        .app-card {background: #fff; color: #333; border-radius: 12px; padding: 20px; max-width: 500px; margin: 0 auto; box-shadow: 0 5px 20px rgba(0,0,0,0.3);}
        h1 {text-align: center; margin-bottom: 5px; color: #2c3e50; font-size: 22px;}
        .v-tag {text-align: center; font-size: 11px; color: #999; margin-bottom: 20px;}
        .tabs {display: flex; gap: 8px; margin-bottom: 15px;}
        .tab {flex: 1; padding: 12px; border: none; border-radius: 6px; background: #eee; font-weight: bold; cursor: pointer;}
        .tab.active {background: #3498db; color: #fff;}
        .pane {display: none;}
        .pane.active {display: block;}
        .btn-scan {width: 100%; padding: 15px; background: #27ae60; color: #fff; border: none; border-radius: 6px; font-size: 18px; font-weight: bold; cursor: pointer;}
        #qr-reader {width: 100%; border-radius: 8px; overflow: hidden; background: #000;}
        #debug-box {background: #222; color: #0f0; padding: 10px; font-family: monospace; font-size: 10px; margin-top: 15px; border-radius: 5px; max-height: 120px; overflow-y: auto;}
        .result-tpl {margin-top: 20px; border: 2px solid #27ae60; border-radius: 8px; padding: 15px; display: none;}
        .alert {padding: 10px; border-radius: 4px; margin-bottom: 10px; display: none;}
        .alert-error {background: #e74c3c; color: #fff;}
        .alert-info {background: #3498db; color: #fff;}
        .status {text-align: center; margin-top: 10px; font-style: italic; color: #666;}
    </style>
</head>
<body>
    <div class="app-card">
        <h1>Vérification Membre</h1>
        <div class="v-tag">Version 2.4 - Stable</div>
        
        <div class="tabs">
            <button id="t-upload" class="tab" onclick="setMode(`upload`)"> PHOTO</button>
            <button id="t-camera" class="tab active" onclick="setMode(`camera`)"> VIDÉO</button>
        </div>

        <div id="msg-err" class="alert alert-error"></div>
        <div id="msg-info" class="alert alert-info"></div>

        <div id="p-upload" class="pane">
            <button class="btn-scan" onclick="document.getElementById(`f-input`).click()">TAKE / PICK PHOTO</button>
            <input type="file" id="f-input" accept="image/*" style="display:none" onchange="processFile(event)">
            <p style="font-size: 12px; color: #7f8c8d; text-align: center; margin-top: 10px;">
                Utilisez ce mode pour les QR codes difficiles.
            </p>
        </div>

        <div id="p-camera" class="pane active">
            <div id="qr-reader"></div>
            <button id="btn-stop" style="display:none; margin-top:10px; padding:10px; width:100%" onclick="stopCam()">STOP CAMERA</button>
        </div>

        <div id="status" class="status"></div>

        <div id="res-box" class="result-tpl">
            <h2 style="color:#27ae60; margin-bottom:10px; font-size:16px"> MEMBRE TROUVÉ</h2>
            <p><strong>ID :</strong> <span id="r-id"></span></p>
            <p><strong>Pseudo :</strong> <span id="r-ps"></span></p>
            <p><strong>Nom :</strong> <span id="r-no"></span></p>
            <p><strong>Prénom :</strong> <span id="r-pr"></span></p>
            <p><strong>Email :</strong> <span id="r-em"></span></p>
            <hr style="margin: 10px 0; border: 0; border-top: 1px solid #eee;">
            <p><strong>Pass :</strong> <span id="r-pa" style="color: #e67e22; font-weight: bold;"></span></p>
            <p><strong>Pass Ext :</strong> <span id="r-px" style="color: #e67e22; font-weight: bold;"></span></p>
            <button style="margin-top: 15px; width: 100%; padding: 10px; background: #34495e; color: #fff; border: none; border-radius: 4px;" onclick="resetUI()">NOUVEAU SCAN</button>
        </div>

        <div id="debug-box">Console prête.</div>
    </div>

    <div id="qr-file-hidden" style="display:none"></div>

    <script>
        const dbg = document.getElementById(`debug-box`);
        const st = document.getElementById(`status`);
        let scanner = null;
        let scanActive = false;

        function log(m) {
            if(dbg) dbg.innerHTML = `> ` + m + `<br>` + dbg.innerHTML;
            console.log(m);
        }

        function setMode(m) {
            log(`Mode: ` + m);
            document.querySelectorAll(`.tab`).forEach(t => t.classList.remove(`active`));
            document.querySelectorAll(`.pane`).forEach(p => p.classList.remove(`active`));
            document.getElementById(`res-box`).style.display = `none`;
            document.getElementById(`msg-err`).style.display = `none`;
            
            if(m === `upload`) {
                document.getElementById(`t-upload`).classList.add(`active`);
                document.getElementById(`p-upload`).classList.add(`active`);
                stopCam();
            } else {
                document.getElementById(`t-camera`).classList.add(`active`);
                document.getElementById(`p-camera`).classList.add(`active`);
                setTimeout(startCam, 300);
            }
        }

        function processFile(e) {
            const f = e.target.files[0];
            if(!f) return;
            log(`Photo: ` + f.name + ` (` + Math.round(f.size/1024) + `KB)`);
            st.innerHTML = `Analyse de la photo...`;
            
            const q = new Html5Qrcode("qr-file-hidden");
            q.scanFile(f, false)
                .then(code => {
                    log('QR Détecté OK');
                    st.innerHTML = '';
                    verify(code);
                })
                .catch(err => {
                    // Si err est un objet, on essaie de d'extraire le message d'erreur
                    const errorMsg = (typeof err === 'string') ? err : (err.message || JSON.stringify(err));
                    log('Échec lecture: ' + errorMsg);
                    st.innerHTML = '';
                    showErr('QR non reconnu. Essayez de vous éloigner un peu ou de mettre plus de lumière.');
                });
        }

        function startCam() {
            log(`Démarrage Caméra...`);
            if(!scanner) scanner = new Html5Qrcode("qr-reader");
            
            st.innerHTML = `Initialisation...`;
            document.getElementById(`btn-stop`).style.display = `block`;

            scanner.start(
                { facingMode: "environment" },
                { 
                    fps: 10, 
                    qrbox: {width: 250, height: 250},
                    experimentalFeatures: {
                        useBarCodeDetectorIfSupported: true
                    }
                },
                (code) => {
                    log(`QR Vidéo OK`);
                    stopCam();
                    verify(code);
                },
                (err) => {}
            ).then(() => {
                log(`Vidéo ACTIVE`);
                scanActive = true;
                st.innerHTML = `Scannez le QR code`;
            }).catch(err => {
                log(`Err Cam: ` + err);
                showErr(`Caméra bloquée. Utilisez le mode PHOTO.`);
                document.getElementById(`btn-stop`).style.display = `none`;
                st.innerHTML = ``;
            });
        }

        function stopCam() {
            if(scanner && scanActive) {
                scanner.stop().then(() => {
                    log(`Vidéo STOP`);
                    scanActive = false;
                    document.getElementById(`btn-stop`).style.display = `none`;
                }).catch(e => {
                    scanActive = false;
                });
            }
        }

        function verify(code) {
            log('Vérif base: ' + code.substring(0, 15) + '...');
            st.innerHTML = '⏳ Connexion serveur...';
            
            const formData = new URLSearchParams();
            formData.append('action', 'verify');
            formData.append('code', code);

            fetch(window.location.pathname, {
                method: 'POST',
                body: formData
            })
            .then(async response => {
                const text = await response.text();
                log('Réponse reçue (' + response.status + ')');
                try {
                    return JSON.parse(text);
                } catch(e) {
                    log('ERREUR JSON: ' + text.substring(0, 100));
                    throw new Error('Réponse invalide du serveur');
                }
            })
            .then(d => {
                st.innerHTML = '';
                if(d.success) {
                    log('✅ Membre identifié');
                    document.getElementById('r-id').innerText = d.id;
                    document.getElementById('r-ps').innerText = d.pseudo;
                    document.getElementById('r-no').innerText = d.nom;
                    document.getElementById('r-pr').innerText = d.prenom;
                    document.getElementById('r-em').innerText = d.email;
                    document.getElementById('r-pa').innerText = d.pass;
                    document.getElementById('r-px').innerText = d.pass_ext;
                    document.getElementById('res-box').style.display = 'block';
                } else {
                    log('ℹ️ ' + d.message);
                    showErr(d.message);
                }
            })
            .catch(err => {
                log('❌ Erreur: ' + err.message);
                st.innerHTML = '';
                showErr('Erreur de connexion : ' + err.message);
            });
        }

        function showErr(m) {
            const e = document.getElementById(`msg-err`);
            if(e) {
                e.innerText = m;
                e.style.display = `block`;
            }
        }

        function resetUI() {
            document.getElementById(`res-box`).style.display = `none`;
            document.getElementById(`f-input`).value = ``;
            st.innerHTML = ``;
            // Relancer la caméra si on est en mode caméra
            if(document.getElementById('t-camera').classList.contains('active')) {
                startCam();
            }
        }

        // Lancement automatique du mode Vidéo au chargement
        window.addEventListener('load', () => {
            log(`Chargement auto...`);
            setMode('camera');
        });

        log(`Prêt v2.8`);
    </script>
</body>
</html>
