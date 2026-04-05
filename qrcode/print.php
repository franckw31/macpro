<?php
require_once __DIR__ . '/../config.php';

if (!isset($_GET['id'])) {
    die("ID manquant");
}

$id = (int)$_GET['id'];
$shape = isset($_GET['shape']) ? $_GET['shape'] : 'square';
$result = mysqli_query($conx, "SELECT content FROM qrcodes WHERE id = $id");
if (!$row = mysqli_fetch_assoc($result)) {
    die("QR Code non trouvé");
}

$qr_content = $row['content'];
$display_user = $qr_content;
if (preg_match('/pseudo=([^&]+)/', $qr_content, $matches)) {
    $display_user = urldecode($matches[1]);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Imprimer QR Code</title>
    <style>
        body {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 15mm;
            font-family: Arial, sans-serif;
        }
        .qr-container {
            text-align: center;
            border: 1px solid #ccc;
            padding: 10px;
            border-radius: 5px;
            position: relative;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
            <?php if ($shape === 'round'): ?>
            width: 3cm;
            height: 3cm;
            border-radius: 50% !important;
            background: black !important;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            border: none;
            overflow: hidden;
            box-shadow: inset 0 0 0 1.5cm black; /* Force le noir même si le background est désactivé */
            <?php elseif ($shape === 'card'): ?>
            width: 81mm;
            height: 52mm;
            background-color: black !important;
            background-image: url('joker_bg.jpg') !important;
            background-size: cover !important;
            background-position: calc(50% - 15mm) center !important;
            background-repeat: no-repeat !important;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding-right: 3mm;
            border: none;
            border-radius: 3mm;
            box-shadow: 0 0 10px rgba(0,0,0,0.5);
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
            <?php endif; ?>
        }
        .qr-wrapper {
            position: relative;
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            background: #000;
            padding: 0.5px;
            <?php if ($shape === 'round'): ?>
            background: transparent;
            <?php elseif ($shape === 'card'): ?>
            transform: translateY(-5mm);
            <?php endif; ?>
        }
        .qr-overlay {
            position: absolute;
            top: 14mm; /* Centre du QR 28mm */
            left: 50%;
            transform: translate(-50%, -50%);
            padding: 0;
            border-radius: 2px;
            display: flex;
            flex-direction: column;
            align-items: center;
            box-shadow: 0 0 2px rgba(255,255,255,0.2);
        }
        .qr-logo {
            width: 7mm;
            height: 7mm;
            background: black !important;
            <?php if ($shape === 'round'): ?>
            width: 6mm;
            height: 6mm;
            <?php elseif ($shape === 'card'): ?>
            width: 8.5mm;
            height: 8.5mm;
            <?php endif; ?>
        }
        .qr-user-text {
            font-size: 9pt;
            font-weight: bold;
            margin-top: 2mm;
            color: #ffffff;
            text-transform: capitalize;
            line-height: 1;
            text-shadow: 1px 1px 2px rgba(0,0,0,1);
        }
        img.qr-code {
            width: 28mm;
            height: 28mm;
            <?php if ($shape === 'round'): ?>
            width: 21mm;
            height: 21mm;
            <?php endif; ?>
        }
        .content-text {
            margin-top: 20px;
            font-size: 18px;
            color: #555;
        }
        .info-button {
            margin-top: 10px;
            margin-left: 5px;
            padding: 10px 20px;
            background-color: #17a2b8;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            transition: background-color 0.3s;
        }
        .info-button:hover {
            background-color: #138496;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.4);
        }
        .modal-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            border-radius: 8px;
            width: 400px;
            max-width: 90%;
            box-shadow: 0 4px 6px rgba(0,0,0,0.3);
        }
        .modal-header {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 15px;
            color: #333;
        }
        .modal-body {
            font-size: 16px;
            line-height: 1.8;
            color: #555;
        }
        .modal-body div {
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 1px solid #eee;
        }
        .modal-body div:last-child {
            border-bottom: none;
        }
        .modal-body strong {
            color: #333;
        }
        .close-modal {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            line-height: 20px;
        }
        .close-modal:hover {
            color: #000;
        }
        @media print {
            .no-print {
                display: none;
            }
            body {
                height: auto;
                background: white;
                padding: 15mm;
            }
            .qr-container {
                border: none;
                <?php if ($shape === 'round'): ?>
                width: 3cm;
                height: 3cm;
                border-radius: 50% !important;
                background: black !important;
                box-shadow: inset 0 0 0 1.5cm black !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                <?php elseif ($shape === 'card'): ?>
                width: 81mm;
                height: 52mm;
                background-color: black !important;
                background-image: url('joker_bg.jpg') !important;
                background-size: cover !important;
                background-position: calc(50% - 15mm) center !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                <?php endif; ?>
            }
        }
    </style>
</head>
<body>
    <div class="qr-container">
        <div class="qr-wrapper">
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=<?php echo urlencode($qr_content); ?>&ecc=H&color=ffffff&bgcolor=000000&margin=1&v=<?php echo time(); ?>" class="qr-code" alt="QR Code">
            <div class="qr-overlay">
                <img src="vip.png" class="qr-logo" alt="Logo">
            </div>
            <div class="qr-user-text"><?php echo htmlspecialchars($display_user); ?></div>
        </div>
    </div>
    <br>
    <div style="text-align: center;">
        <button class="no-print" style="padding: 10px 20px; margin: 5px; background-color: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 14px;" onclick="window.print()">Imprimer maintenant</button>
        <button class="no-print" style="padding: 10px 20px; margin: 5px; background-color: #6c757d; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 14px;" onclick="window.history.back()">Retour</button>
        <button class="info-button no-print" onclick="openModal()">ℹ️ Dimensions & marges</button>
    </div>

    <!-- Modal Popup -->
    <div id="infoModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal()">&times;</span>
            <div class="modal-header">Marges et Dimensions</div>
            <div class="modal-body">
                <div>
                    <strong>Marges de la page :</strong><br>
                    15mm (haut, bas, gauche, droite)
                </div>
                <div>
                    <strong>Taille de l'étiquette :</strong><br>
                    <?php if ($shape === 'round'): ?>
                        Rond - Ø 30mm
                    <?php elseif ($shape === 'card'): ?>
                        Carte - 81mm × 52mm
                    <?php else: ?>
                        Carré - 28mm × 28mm
                    <?php endif; ?>
                </div>
                <div>
                    <strong>Format :</strong><br>
                    <?php echo ucfirst($shape === 'square' ? 'carré' : $shape); ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openModal() {
            document.getElementById("infoModal").style.display = "block";
        }

        function closeModal() {
            document.getElementById("infoModal").style.display = "none";
        }

        window.onclick = function(event) {
            const modal = document.getElementById("infoModal");
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }

        // Optionnel: lancer l'impression automatiquement
        // window.onload = function() { window.print(); }
    </script>
</body>
</html>
