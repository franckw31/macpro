<?php
/**
 * Générateur de fond d'écran avec motif de cartes
 * Génère une image PNG directement
 */

// Configuration
$width = isset($_GET['w']) ? (int)$_GET['w'] : 1920;
$height = isset($_GET['h']) ? (int)$_GET['h'] : 1080;
$symbolSize = isset($_GET['size']) ? (int)$_GET['size'] : 80;
$spacing = isset($_GET['spacing']) ? (int)$_GET['spacing'] : 120;
$opacity = isset($_GET['opacity']) ? (float)$_GET['opacity'] : 0.15;
$download = isset($_GET['download']) ? true : false;

// Limites de sécurité
$width = min(max($width, 800), 7680);
$height = min(max($height, 600), 4320);
$symbolSize = min(max($symbolSize, 30), 200);
$spacing = min(max($spacing, 50), 300);
$opacity = min(max($opacity, 0.05), 1.0);

// Créer l'image
$image = imagecreatetruecolor($width, $height);

// Couleurs
$black = imagecolorallocate($image, 0, 0, 0);
$white = imagecolorallocatealpha($image, 255, 255, 255, (int)((1 - $opacity) * 127));
$red = imagecolorallocatealpha($image, 255, 0, 0, (int)((1 - $opacity) * 127));

// Remplir le fond en noir
imagefilledrectangle($image, 0, 0, $width, $height, $black);

// Activer l'alpha blending
imagealphablending($image, true);

// Police (utiliser une police TrueType pour de meilleurs symboles)
$fontFile = __DIR__ . '/fonts/Arial.ttf';
// Utiliser la police système si disponible
if (!file_exists($fontFile)) {
    $fontFile = 'C:/Windows/Fonts/arial.ttf';
}

// Symboles de cartes en UTF-8
$suits = [
    ['symbol' => '♠', 'color' => $white],  // Pique
    ['symbol' => '♣', 'color' => $white],  // Trèfle  
    ['symbol' => '♥', 'color' => $red],    // Cœur
    ['symbol' => '♦', 'color' => $red]     // Carreau
];

// Calculer la grille
$cols = ceil($width / $spacing);
$rows = ceil($height / $spacing);

// Dessiner le motif
$suitIndex = 0;
for ($row = 0; $row < $rows; $row++) {
    for ($col = 0; $col < $cols; $col++) {
        $x = $col * $spacing + $spacing / 2;
        $y = $row * $spacing + $spacing / 2;
        
        $suit = $suits[$suitIndex % 4];
        
        // Si police TrueType disponible
        if (file_exists($fontFile)) {
            // Calculer la position pour centrer le texte
            $bbox = imagettfbbox($symbolSize, 0, $fontFile, $suit['symbol']);
            $textWidth = $bbox[2] - $bbox[0];
            $textHeight = $bbox[1] - $bbox[7];
            
            imagettftext(
                $image,
                $symbolSize,
                0,
                $x - $textWidth / 2,
                $y + $textHeight / 2,
                $suit['color'],
                $fontFile,
                $suit['symbol']
            );
        } else {
            // Fallback avec police système (moins beau)
            imagestring($image, 5, $x - 10, $y - 10, $suit['symbol'], $suit['color']);
        }
        
        $suitIndex++;
    }
}

// Headers pour l'image
header('Content-Type: image/png');
if ($download) {
    header('Content-Disposition: attachment; filename="card_suits_wallpaper_' . $width . 'x' . $height . '.png"');
} else {
    header('Content-Disposition: inline; filename="wallpaper.png"');
}

// Générer l'image
imagepng($image);

// Libérer la mémoire
imagedestroy($image);
?>
