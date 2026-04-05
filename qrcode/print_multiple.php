<?php
require_once __DIR__ . '/../config.php';

if (!isset($_GET['ids']) || !is_array($_GET['ids'])) {
    die("Aucune étiquette sélectionnée");
}

$start_pos = isset($_GET['start']) ? (int)$_GET['start'] : 1;
if ($start_pos < 1) $start_pos = 1;
if ($start_pos > 10) $start_pos = 10;

$max_labels = 11 - $start_pos; // Nombre max d'étiquettes restantes sur la page
$ids = array_map('int_val', array_slice($_GET['ids'], 0, $max_labels));
$ids_string = implode(',', $ids);

$result = mysqli_query($conx, "SELECT * FROM qrcodes WHERE id IN ($ids_string) ORDER BY FIELD(id, $ids_string)");
$qrcodes = [];
while ($row = mysqli_fetch_assoc($result)) {
    $display_user = $row['content'];
    if (preg_match('/pseudo=([^&]+)/', $row['content'], $matches)) {
        $display_user = urldecode($matches[1]);
    }
    $qrcodes[] = [
        'content' => $row['content'],
        'user' => $display_user
    ];
}

function int_val($v) { return (int)$v; }

// Paramètres ajustables avec valeurs par défaut
// Pour page 209×296mm : ml+mr=30mm, mt+mb=16mm
$ml = isset($_GET['ml']) ? floatval($_GET['ml']) : 16.5; // left
$mr = isset($_GET['mr']) ? floatval($_GET['mr']) : 17; // right
$mt = isset($_GET['mt']) ? floatval($_GET['mt']) : 12.5; // top
$mb = isset($_GET['mb']) ? floatval($_GET['mb']) : 8; // bottom
$hgap = isset($_GET['hgap']) ? floatval($_GET['hgap']) : 12; // horizontal gap
$vgap = isset($_GET['vgap']) ? floatval($_GET['vgap']) : 2;  // vertical gap
$show_border = isset($_GET['border']) && $_GET['border'] === '1'; // afficher cadre
$border_thickness = isset($_GET['border_thickness']) ? floatval($_GET['border_thickness']) : 0.5; // épaisseur du cadre en mm

// Garde-fous simples
foreach ([&$ml, &$mr, &$mt, &$mb, &$hgap, &$vgap, &$border_thickness] as &$val) {
    if ($val < 0) $val = 0;
    if ($val > 50) $val = 50; // limites raisonnables
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Impression Planche A4 (10 étiquettes)</title>
    <style>
        @page {
            size: A4;
            margin: 0;
        }
        body {
            margin: 0;
            padding: 10mm; /* Marges de sécurité A4 */
            font-family: Arial, sans-serif;
            background: #f0f0f0;
        }
        .page-a4 {
            width: 209mm;
            height: 296mm;
            background: white;
            margin: auto;
            display: grid;
            grid-template-columns: repeat(2, 83mm);
            grid-template-rows: repeat(5, 53mm);
            /* Espacement entre étiquettes */
            column-gap: <?php echo $hgap; ?>mm; /* Espace horizontal entre colonnes */
            row-gap: <?php echo $vgap; ?>mm;     /* Espace vertical entre lignes */
            /* 209mm horizontal: ml + 82 + hgap + 82 + mr */
            /* 296mm vertical: mt + (5×52) + (4×vgap) + mb */
            padding: <?php echo $mt; ?>mm <?php echo $mr; ?>mm <?php echo $mb; ?>mm <?php echo $ml; ?>mm; /* top right bottom left */
            box-sizing: border-box;
            justify-content: center; /* Centre horizontalement le contenu */
            /* Contour visible pour matérialiser la page A4 à l'écran */
            box-shadow: inset 0 0 0 0.5mm #000;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .label-card {
            width: 83mm;
            height: 53mm;
            background-color: black !important;
            background-image: url('joker_bg.jpg') !important;
            background-size: cover !important;
            background-position: calc(50% - 15mm) center !important;
            background-repeat: no-repeat !important;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding-right: 3mm;
            border-radius: 2mm;
            position: relative;
            overflow: hidden;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
            box-sizing: border-box;
            border: 2mm solid #000;
        }
        .label-empty {
            width: 83mm;
            height: 53mm;
            box-sizing: border-box;
            border-radius: 2mm;
        }
        <?php if ($show_border): ?>
        .label-card,
        .label-empty {
            outline: 0.5mm solid #000;
            outline-offset: 1mm;
        }
        <?php endif; ?>
        .qr-wrapper {
            position: relative;
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            background: #000;
            padding: 0.5px;
            transform: translateY(-5mm);
        }
        .qr-code {
            width: 28mm;
            height: 28mm;
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
        }
        .qr-logo {
            width: 8.5mm;
            height: 8.5mm;
            background: black !important;
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
        .no-print-zone {
            position: fixed;
            top: 10px;
            right: 10px;
            z-index: 1000;
        }
        button {
            padding: 10px 20px;
            cursor: pointer;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            font-weight: bold;
            margin-right: 5px;
        }
        .info-button {
            background-color: #17a2b8;
            font-weight: bold;
            transition: background-color 0.3s;
        }
        .info-button:hover {
            background-color: #138496;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
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
            body { background: none; padding: 0; }
            .no-print-zone { display: none; }
            /* Conserver un fin contour interne pour matérialiser 210x297mm */
            .page-a4 { 
                margin: 0; 
                border: none; 
                box-shadow: inset 0 0 0 0.5mm #000; 
                -webkit-print-color-adjust: exact; 
                print-color-adjust: exact; 
            }
            .label-card { border: none; }
        }
    </style>
</head>
<body>
    <div class="no-print-zone">
        <button onclick="window.print()">Imprimer la planche A4</button>
        <button onclick="window.close()" style="background: #6c757d;">Fermer</button>
        <button class="info-button" onclick="openModal()">ℹ️ Dimensions</button>
    </div>

    <!-- Modal Popup -->
    <div id="infoModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal()">&times;</span>
            <div class="modal-header">Marges et Dimensions</div>
            <div class="modal-body">
                <div>
                    <strong>Marges de la page (calculées) :</strong><br>
                    Gauche : <span id="marginLeftMm">–</span> | Droite : <span id="marginRightMm">–</span><br>
                    Haut : <span id="marginTopMm">–</span> | Bas : <span id="marginBottomMm">–</span><br>
                    Espace horizontal entre étiquettes : <span id="hGapMm">–</span>
                </div>
                <div style="margin-top: 10px;">
                    <strong>Modifier (mm) :</strong>
                    <form method="get" style="margin-top: 8px; display: grid; grid-template-columns: repeat(6, 1fr); gap: 6px; align-items: end;">
                        <label style="font-size: 12px;">Haut
                            <input type="number" step="0.1" name="mt" value="<?php echo htmlspecialchars($mt); ?>" style="width:100%; padding:6px;">
                        </label>
                        <label style="font-size: 12px;">Droite
                            <input type="number" step="0.1" name="mr" value="<?php echo htmlspecialchars($mr); ?>" style="width:100%; padding:6px;">
                        </label>
                        <label style="font-size: 12px;">Bas
                            <input type="number" step="0.1" name="mb" value="<?php echo htmlspecialchars($mb); ?>" style="width:100%; padding:6px;">
                        </label>
                        <label style="font-size: 12px;">Gauche
                            <input type="number" step="0.1" name="ml" value="<?php echo htmlspecialchars($ml); ?>" style="width:100%; padding:6px;">
                        </label>
                        <label style="font-size: 12px;">H. gap
                            <input type="number" step="0.1" name="hgap" value="<?php echo htmlspecialchars($hgap); ?>" style="width:100%; padding:6px;">
                        </label>
                        <label style="font-size: 12px;">V. gap
                            <input type="number" step="0.1" name="vgap" value="<?php echo htmlspecialchars($vgap); ?>" style="width:100%; padding:6px;">
                        </label>

                        <!-- Préserver la sélection -->
                        <input type="hidden" name="start" value="<?php echo htmlspecialchars($start_pos); ?>">
                        <?php foreach ($ids as $hid): ?>
                            <input type="hidden" name="ids[]" value="<?php echo (int)$hid; ?>">
                        <?php endforeach; ?>

                        <div style="grid-column: 1 / -1; margin-top: 8px; padding: 8px 0; border-top: 1px solid #eee;">
                            <label style="display: flex; align-items: center; gap: 6px; font-size: 13px;">
                                <input type="checkbox" name="border" value="1" <?php if ($show_border) echo 'checked'; ?> style="cursor: pointer;">
                                Cadre 1mm autour des étiquettes
                            </label>
                        </div>

                        <div style="grid-column: 1 / -1; margin-top: 4px;">
                            <button type="submit" style="padding:8px 12px; background:#28a745; border:none; color:#fff; border-radius:4px; cursor:pointer;">Appliquer</button>
                            <button type="button" onclick="resetDefaults()" style="padding:8px 12px; background:#6c757d; border:none; color:#fff; border-radius:4px; cursor:pointer; margin-left:6px;">Valeurs par défaut</button>
                        </div>
                    </form>
                </div>
                <div>
                    <strong>Taille de chaque étiquette :</strong><br>
                    83mm × 53mm (format carte avec cadre)
                </div>
                <div>
                    <strong>Espacement :</strong><br>
                    5mm entre les étiquettes
                </div>
                <div>
                    <strong>Disposition :</strong><br>
                    2 colonnes × 5 lignes (10 étiquettes max)
                </div>
            </div>
        </div>
    </div>

    <div class="page-a4">
        <?php 
        // Ajouter les emplacements vides avant
        for ($i = 1; $i < $start_pos; $i++) {
            echo '<div class="label-empty" style="width: 83mm; height: 53mm;"></div>';
        }

        foreach ($qrcodes as $qr): ?>
            <div class="label-card">
                <div class="qr-wrapper">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=<?php echo urlencode($qr['content']); ?>&ecc=H&color=ffffff&bgcolor=000000&margin=1&v=<?php echo time(); ?>" class="qr-code" alt="QR Code">
                    <div class="qr-overlay">
                        <img src="vip.png" class="qr-logo" alt="Logo">
                    </div>
                    <div class="qr-user-text"><?php echo htmlspecialchars($qr['user']); ?></div>
                </div>
            </div>
        <?php endforeach; 
        
        // Ajouter les emplacements vides après pour toujours avoir 10 emplacements
        $total_filled = ($start_pos - 1) + count($qrcodes);
        $empty_after = 10 - $total_filled;
        for ($i = 0; $i < $empty_after; $i++) {
            echo '<div class="label-empty" style="width: 83mm; height: 53mm;"></div>';
        }
        ?>
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

        // Afficher les marges gauche/droite exactes en mm (conversion depuis px)
        function pxToMm(px) {
            return (px * 25.4 / 96); // 96dpi CSS -> mm
        }

        function updateMarginInfo() {
            const page = document.querySelector('.page-a4');
            if (!page) return;
            const styles = getComputedStyle(page);
            const pl = parseFloat(styles.paddingLeft);
            const pr = parseFloat(styles.paddingRight);
            const pt = parseFloat(styles.paddingTop);
            const pb = parseFloat(styles.paddingBottom);
            const leftMm = pxToMm(pl);
            const rightMm = pxToMm(pr);
            const topMm = pxToMm(pt);
            const bottomMm = pxToMm(pb);
            document.getElementById('marginLeftMm').textContent = leftMm.toFixed(1) + ' mm';
            document.getElementById('marginRightMm').textContent = rightMm.toFixed(1) + ' mm';
            const mt = document.getElementById('marginTopMm');
            const mb = document.getElementById('marginBottomMm');
            if (mt) mt.textContent = topMm.toFixed(1) + ' mm';
            if (mb) mb.textContent = bottomMm.toFixed(1) + ' mm';

            // Espace horizontal entre colonnes (column-gap)
            const cg = parseFloat(styles.columnGap);
            if (!isNaN(cg)) {
                const hGap = pxToMm(cg);
                const span = document.getElementById('hGapMm');
                if (span) span.textContent = hGap.toFixed(1) + ' mm';
            }
        }

        function resetDefaults() {
            const params = new URLSearchParams(window.location.search);
            params.set('ml', '15');
            params.set('mr', '15');
            params.set('mt', '8');
            params.set('mb', '8');
            params.set('hgap', '13');
            params.set('vgap', '5');
            window.location.search = params.toString();
        }

        window.addEventListener('load', updateMarginInfo);
        window.addEventListener('resize', updateMarginInfo);
    </script>
</body>
</html>
