<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../include/functions_logs.php';

$message = "";
if (isset($_POST['generate'])) {
    $pseudo = mysqli_real_escape_string($conx, $_POST['pseudo']);
    $network = isset($_POST['network']) ? $_POST['network'] : 'internet';
    $host = ($network === 'internet') ? 'viendez.com' : 'localhost';
    
    if (!empty($pseudo)) {
        // Récupérer le mot de passe du membre
        $res_member = mysqli_query($conx, "SELECT password_ext FROM membres WHERE pseudo = '$pseudo'");
        if ($member = mysqli_fetch_assoc($res_member)) {
            $password = $member['password_ext'];
            $content = "http://$host/panel/quickview.php?pseudo=" . urlencode($pseudo) . "&passwd=" . urlencode($password);
            
            $sql = "INSERT INTO qrcodes (content) VALUES ('$content')";
            if (mysqli_query($conx, $sql)) {
                $last_id = mysqli_insert_id($conx);
                log_activity($conx, "QR Code Generated", "Pseudo: $pseudo, Network: $network, ID: $last_id");
                header("Location: index.php?id=$last_id");
                exit();
            } else {
                $message = "Error: " . mysqli_error($conx);
            }
        } else {
            $message = "Membre non trouvé.";
        }
    } else {
        $message = "Veuillez sélectionner un membre.";
    }
}

$qr_content = "";
if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $result = mysqli_query($conx, "SELECT content FROM qrcodes WHERE id = $id");
    if ($row = mysqli_fetch_assoc($result)) {
        $qr_content = $row['content'];
        log_activity($conx, "QR Code Viewed", "ID: $id, Content: " . $qr_content);
    }
}

// Pagination de l'historique (DISABLED - afficher tout)
$items_per_page = 10000; // Très grand nombre pour afficher tout
$current_page = 1;
$offset = 0;

// Compter le nombre total d'items
$count_result = mysqli_query($conx, "SELECT COUNT(*) as total FROM qrcodes");
$count_row = mysqli_fetch_assoc($count_result);
$total_items = $count_row['total'];
$total_pages = 1;
?>
<!DOCTYPE html>
vip<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Générateur de QR Code</title>
    <link rel="stylesheet" href="../css/base.css">
    <style>
        body { font-family: Arial, sans-serif; padding: 10px; text-align: center; background-color: #eee; }
        .container { 
            width: 100%; 
            max-width: 500px; 
            margin: auto; 
            background: #f4f4f4; 
            padding: 15px; 
            border-radius: 8px; 
            box-sizing: border-box; 
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        input[type="text"], select { 
            width: 100%; 
            padding: 12px; 
            margin-bottom: 15px; 
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 16px; /* Empêche le zoom auto sur iOS */
        }
        button { 
            width: 100%;
            padding: 12px 20px; 
            cursor: pointer; 
            background-color: #007bff; 
            color: white; 
            border: none; 
            border-radius: 4px; 
            font-size: 16px;
            margin-bottom: 10px;
        }
        .qr-result { margin-top: 20px; }
        .qr-container-wrapper {
            position: relative;
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            background: #000;
            padding: 2px;
            border-radius: 4px;
            max-width: 100%;
        }
        .qr-container-wrapper img {
            max-width: 100%;
            height: auto;
        }
        .qr-overlay {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -100%); /* Ajusté car le texte en bas décentre le visuel */
            padding: 0;
            border-radius: 4px;
            display: flex;
            flex-direction: column;
            align-items: center;
            box-shadow: 0 0 5px rgba(255,255,255,0.2);
        }
        /* Ajustement spécifique pour l'image 200x200 */
        .qr-result .qr-overlay {
            top: 100px; 
        }
        .qr-logo {
            width: 70px;
            height: 70px;
            background: black;
        }
        .qr-user-text {
            font-size: 10px;
            font-weight: bold;
            margin-top: 2px;
            color: #ffffff;
            text-transform: capitalize;
        }
        .print-btn { background-color: #28a745; margin-top: 5px; }
        .print-btn.round { background-color: #6f42c1; }
        .print-btn.card { background-color: #ffc107; color: black; }
        
        .history-header {
            display: flex; 
            flex-direction: column;
            margin-bottom: 15px;
            text-align: left;
        }
        .history-controls {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .history-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 10px;
            padding: 10px;
            background: white;
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .history-item label {
            flex: 1;
            cursor: pointer;
            word-break: break-all;
            font-size: 0.9em;
            text-align: left;
        }
        .history-item input {
            margin-right: 10px;
        }

        @media (min-width: 480px) {
            .history-header {
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
            }
            .history-controls {
                flex-direction: row;
                align-items: center;
            }
            button {
                width: auto;
            }
            .full-width-mobile {
                width: 100%;
            }
        }

        @media print {
            body * { visibility: hidden; }
            #printableArea, #printableArea * { visibility: visible; }
            #printableArea { position: absolute; left: 0; top: 0; width: 100%; }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Générateur de QR Code</h1>
        <form method="post" id="generateForm">
            <div style="margin-bottom: 15px; text-align: left;">
                <a href="/logs.php" style="float: right;">Logs</a>
                <label style="display: block; margin-bottom: 5px;"><input type="radio" name="network" value="intranet"> Intranet (Localhost)</label>
                <label style="display: block;"><input type="radio" name="network" value="internet" checked> Internet (viendez.com)</label>
            </div>
            <select name="pseudo" required>
                <option value="">-- Sélectionner un membre --</option>
                <?php
                $members_list = mysqli_query($conx, "SELECT pseudo FROM membres ORDER BY pseudo ASC");
                while ($m = mysqli_fetch_assoc($members_list)) {
                    echo "<option value=\"" . htmlspecialchars($m['pseudo']) . "\">" . htmlspecialchars($m['pseudo']) . "</option>";
                }
                ?>
            </select>
            <br>
            <button type="submit" name="generate" class="full-width-mobile">Générer</button>
        </form>

        <?php if ($message): ?>
            <p style="color: red;"><?php echo $message; ?></p>
        <?php endif; ?>

        <?php if ($qr_content): ?>
            <?php 
            $display_user = $qr_content;
            if (preg_match('/pseudo=([^&]+)/', $qr_content, $matches)) {
                $display_user = urldecode($matches[1]);
            }
            ?>
            <div class="qr-result" id="printableArea">
                <h3>Votre QR Code :</h3>
                <div class="qr-container-wrapper">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=<?php echo urlencode($qr_content); ?>&ecc=H&color=ffffff&bgcolor=000000&margin=1&v=<?php echo time(); ?>" alt="QR Code">
                    <div class="qr-overlay">
                        <img src="vip.png" class="qr-logo" alt="Logo">
                    </div>
                    <div class="qr-user-text"><?php echo htmlspecialchars($display_user); ?></div>
                </div>
            </div>
            <div style="display: flex; flex-direction: column; gap: 5px; margin-top: 10px;">
                <a href="print.php?id=<?php echo $id; ?>" target="_blank" style="text-decoration: none;">
                    <button class="print-btn full-width-mobile">Imprimer Carré (28mm)</button>
                </a>
                <a href="print.php?id=<?php echo $id; ?>&shape=round" target="_blank" style="text-decoration: none;">
                    <button class="print-btn round full-width-mobile">Imprimer Rond (Ø 30mm)</button>
                </a>
                <a href="print.php?id=<?php echo $id; ?>&shape=card" target="_blank" style="text-decoration: none;">
                    <button class="print-btn card full-width-mobile">Imprimer Carte (81x52)</button>
                </a>
                <a href="nfc.php?text=<?php echo urlencode($qr_content); ?>" target="_blank" style="text-decoration: none;">
                    <button style="background-color: #9c27b0; margin-top: 5px; width: 100%; padding: 12px 20px; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px;">
                        📱 Écrire sur puce NFC
                    </button>
                </a>
            </div>
        <?php endif; ?>

        <hr style="margin: 20px 0;">
        <div style="text-align: center; margin: 20px 0;">
            <a href="nfc.php" style="display: inline-block; text-decoration: none;">
                <button style="background-color: #9c27b0; color: white; padding: 12px 25px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px;">
                    📱 Écriture NFC
                </button>
            </a>
        </div>

        <hr style="margin: 20px 0;">
        <div style="background: white; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.15); padding: 16px; text-align: center;">
            <div style="font-size: 22px; margin-bottom: 6px;">🗂️</div>
            <div style="font-weight: bold; font-size: 15px; margin-bottom: 4px;">Affectation Collection / Activité</div>
            <div style="font-size: 12px; color: #666; margin-bottom: 12px;">Associer des collections QR à une activité</div>
            <a href="/qrcode/affectation_collection_activite.php" style="text-decoration: none;">
                <button style="background-color: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 15px; width: 100%;">
                    Ouvrir
                </button>
            </a>
        </div>

        <hr style="margin: 20px 0;">
        <form action="print_multiple.php" method="get" target="_blank">
            <div class="history-header">
                <h3 style="margin: 0 0 10px 0;">Historique récents</h3>
                <div class="history-controls">
                    <div style="display: flex; align-items: center; gap: 5px;">
                        <label for="start_pos" style="font-size: 0.9em; white-space: nowrap;">Départ :</label>
                        <select name="start" id="start_pos" style="padding: 5px; margin: 0; width: auto;">
                            <?php for($i=1; $i<=10; $i++) echo "<option value='$i'>$i</option>"; ?>
                        </select>
                    </div>
                    <button type="submit" class="print-btn card" style="margin: 0; padding: 10px; font-size: 0.9em;">Imprimer sélection (A4)</button>
                </div>
            </div>
            <p style="font-size: 0.85em; color: #666; text-align: left;">Sélectionnez jusqu'à 10 étiquettes.</p>
            <div style="text-align: left;">
                <?php
                $history = mysqli_query($conx, "SELECT * FROM qrcodes ORDER BY id DESC LIMIT $offset, $items_per_page");
                while ($h = mysqli_fetch_assoc($history)) {
                    $h_pseudo = "Inconnu";
                    if (preg_match('/pseudo=([^&]+)/', $h['content'], $m_h)) {
                        $h_pseudo = urldecode($m_h[1]);
                    }
                    echo "<div class='history-item'>
                            <label>
                                <input type='checkbox' name='ids[]' value='{$h['id']}'> 
                                <strong>" . htmlspecialchars(ucfirst($h_pseudo)) . "</strong> : 
                                <span style='color: #666;'>" . htmlspecialchars($h['content']) . "</span>
                            </label>
                            <a href='index.php?id={$h['id']}' style='font-size: 0.8em; margin-left: 10px; white-space: nowrap;'>[Voir]</a>
                            <a href='nfc.php?text=" . urlencode($h['content']) . "' style='font-size: 0.8em; margin-left: 5px; white-space: nowrap; color: #7b3ff2;'>[NFC]</a>
                          </div>";
                }
                ?>
            </div>
        </form>
    </div>
</body>
</html>
