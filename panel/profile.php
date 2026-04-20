<?php
session_start();
error_reporting(0);
include(__DIR__ . '/include/config.php');
include(__DIR__ . '/../include/functions_logs.php');

// French date formatter helper (Intl when available, lightweight fallback otherwise)
function fmt_fr_date($dt, $pattern = "EEEE d MMM (dd/MM)", $tz = 'Europe/Paris'){
    if (empty($dt)) return '—';
    try{
        if (class_exists('IntlDateFormatter')){
            $fmt = new IntlDateFormatter('fr_FR', IntlDateFormatter::NONE, IntlDateFormatter::NONE, $tz, IntlDateFormatter::GREGORIAN, $pattern);
            $d = new DateTime($dt);
            return $fmt->format($d);
        }
    }catch(Throwable $e){}
    // fallback: simple French mapping for the patterns we use
    $ts = strtotime($dt);
    if (!$ts) return $dt;
    $days = ['lundi','mardi','mercredi','jeudi','vendredi','samedi','dimanche'];
    $months = ['janv','fév','mars','avr','mai','juin','juil','août','sept','oct','nov','déc'];
    if (strpos($pattern, 'EEEE') !== false) {
        $day = $days[intval(date('N',$ts)) - 1];
        $d = intval(date('j',$ts));
        $month = $months[intval(date('n',$ts)) - 1];
        $dd = date('d',$ts);
        return ucfirst($day) . ' ' . $d . ' ' . $month . ' (' . $dd . '/' . date('m',$ts) . ')';
    }
    // default fallback: d MMM HH:mm
    $d = intval(date('j',$ts));
    $month = $months[intval(date('n',$ts)) - 1];
    $time = date('H:i',$ts);
    return $d . ' ' . $month . ' ' . $time;
}

function create_avatar_image_resource(string $tmp_name, string $extension) {
    switch ($extension) {
        case 'jpg':
        case 'jpeg':
            return function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($tmp_name) : false;
        case 'png':
            return function_exists('imagecreatefrompng') ? @imagecreatefrompng($tmp_name) : false;
        case 'gif':
            return function_exists('imagecreatefromgif') ? @imagecreatefromgif($tmp_name) : false;
        case 'webp':
            return function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmp_name) : false;
        default:
            return false;
    }
}

function save_avatar_image_resource($image, string $target_file, string $extension): bool {
    switch ($extension) {
        case 'jpg':
        case 'jpeg':
            return function_exists('imagejpeg') ? @imagejpeg($image, $target_file, 88) : false;
        case 'png':
            return function_exists('imagepng') ? @imagepng($image, $target_file, 6) : false;
        case 'gif':
            return function_exists('imagegif') ? @imagegif($image, $target_file) : false;
        case 'webp':
            return function_exists('imagewebp') ? @imagewebp($image, $target_file, 88) : false;
        default:
            return false;
    }
}

function create_square_avatar_file(string $tmp_name, string $target_file, string $extension, int $final_size = 512): bool {
    if (!function_exists('imagecreatetruecolor') || !function_exists('imagecopyresampled')) {
        return false;
    }

    $source = create_avatar_image_resource($tmp_name, $extension);
    if (!$source) {
        return false;
    }

    $source_width = imagesx($source);
    $source_height = imagesy($source);
    $crop_size = min($source_width, $source_height);
    $src_x = (int) floor(($source_width - $crop_size) / 2);
    $src_y = (int) floor(($source_height - $crop_size) / 2);

    $destination = imagecreatetruecolor($final_size, $final_size);
    if (!$destination) {
        imagedestroy($source);
        return false;
    }

    if (in_array($extension, ['png', 'gif', 'webp'], true)) {
        imagealphablending($destination, false);
        imagesavealpha($destination, true);
        $transparent = imagecolorallocatealpha($destination, 0, 0, 0, 127);
        imagefilledrectangle($destination, 0, 0, $final_size, $final_size, $transparent);
    } else {
        $background = imagecolorallocate($destination, 255, 255, 255);
        imagefilledrectangle($destination, 0, 0, $final_size, $final_size, $background);
    }

    $resampled = imagecopyresampled(
        $destination,
        $source,
        0,
        0,
        $src_x,
        $src_y,
        $final_size,
        $final_size,
        $crop_size,
        $crop_size
    );

    $saved = $resampled ? save_avatar_image_resource($destination, $target_file, $extension) : false;

    imagedestroy($destination);
    imagedestroy($source);

    return $saved;
}

$uid = isset($_SESSION['id']) ? intval($_SESSION['id']) : 0;
if ($uid <= 0) {
    // redirect to login flow if needed
    $_SESSION['redirect'] = 'panel/profile.php';
    header('Location: logout.php');
    exit;
}

$profile_flash = $_SESSION['profile_avatar_flash'] ?? null;
unset($_SESSION['profile_avatar_flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload_avatar') {
    $redirect_uri = $_SERVER['REQUEST_URI'] ?? '/panel/profile.php';

    $set_flash = function(string $type, string $message) {
        $_SESSION['profile_avatar_flash'] = ['type' => $type, 'message' => $message];
    };

    $target_dir = __DIR__ . '/../images/faces/';
    if (!is_dir($target_dir) && !mkdir($target_dir, 0755, true)) {
        $set_flash('error', "Impossible de créer le dossier des avatars.");
        header('Location: ' . $redirect_uri);
        exit;
    }

    if (!is_writable($target_dir)) {
        $set_flash('error', "Le dossier des avatars n'est pas accessible en écriture.");
        header('Location: ' . $redirect_uri);
        exit;
    }

    if (empty($_FILES['fileToUpload']) || !isset($_FILES['fileToUpload']['error'])) {
        $set_flash('error', "Aucun fichier sélectionné.");
        header('Location: ' . $redirect_uri);
        exit;
    }

    if ((int)$_FILES['fileToUpload']['error'] !== UPLOAD_ERR_OK) {
        $set_flash('error', "Le téléchargement a échoué.");
        header('Location: ' . $redirect_uri);
        exit;
    }

    $tmp_name = $_FILES['fileToUpload']['tmp_name'];
    $file_size = (int)($_FILES['fileToUpload']['size'] ?? 0);
    $extension = strtolower(pathinfo($_FILES['fileToUpload']['name'] ?? '', PATHINFO_EXTENSION));
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    if (!in_array($extension, $allowed_extensions, true)) {
        $set_flash('error', "Seuls les fichiers JPG, JPEG, PNG, GIF et WEBP sont autorisés.");
        header('Location: ' . $redirect_uri);
        exit;
    }

    $image_info = @getimagesize($tmp_name);
    if ($image_info === false) {
        $set_flash('error', "Le fichier sélectionné n'est pas une image valide.");
        header('Location: ' . $redirect_uri);
        exit;
    }

    if ($file_size > 5 * 1024 * 1024) {
        $set_flash('error', "L'image est trop volumineuse (max 5 Mo).");
        header('Location: ' . $redirect_uri);
        exit;
    }

    $new_filename = 'profile_' . $uid . '_' . time() . '.' . $extension;
    $target_file = $target_dir . $new_filename;

    $old_photo = '';
    if ($stmt_old = @mysqli_prepare($con, "SELECT photo FROM membres WHERE `id-membre` = ? LIMIT 1")) {
        mysqli_stmt_bind_param($stmt_old, 'i', $uid);
        mysqli_stmt_execute($stmt_old);
        mysqli_stmt_bind_result($stmt_old, $old_photo);
        mysqli_stmt_fetch($stmt_old);
        mysqli_stmt_close($stmt_old);
    }

    $saved_file = create_square_avatar_file($tmp_name, $target_file, $extension, 512);
    if (!$saved_file) {
        $saved_file = @move_uploaded_file($tmp_name, $target_file);
    }

    if (!$saved_file) {
        $set_flash('error', "Impossible d'enregistrer l'image téléchargée.");
        header('Location: ' . $redirect_uri);
        exit;
    }

    $updated = false;
    if ($stmt_update = @mysqli_prepare($con, "UPDATE membres SET photo = ? WHERE `id-membre` = ?")) {
        mysqli_stmt_bind_param($stmt_update, 'si', $new_filename, $uid);
        $updated = mysqli_stmt_execute($stmt_update);
        mysqli_stmt_close($stmt_update);
    }

    if (!$updated) {
        @unlink($target_file);
        $set_flash('error', "La mise à jour de la photo a échoué.");
        header('Location: ' . $redirect_uri);
        exit;
    }

    $old_photo = trim((string)$old_photo);
    if ($old_photo !== '' && !in_array($old_photo, ['noprofil.jpg', 'man.png'], true)) {
        $old_path = $target_dir . basename($old_photo);
        if (is_file($old_path)) {
            @unlink($old_path);
        }
    }

    $set_flash('success', "Photo de profil mise à jour avec succès.");
    header('Location: ' . $redirect_uri);
    exit;
}

$user = ['pseudo' => 'Visiteur', 'photo' => 'noprofil.jpg'];
$q = @mysqli_query($con, "SELECT * FROM membres WHERE `id-membre` = '" . intval($uid) . "' LIMIT 1");
if ($q && ($r = mysqli_fetch_assoc($q))) {
    $user = $r;
}

// Count tombola tickets for this member
$tombola_count = 0;
$qt = @mysqli_query($con, "SELECT COUNT(*) AS c FROM `collections-individu` WHERE `id-indiv` = '".intval($uid)."'");
if ($qt && ($rt = mysqli_fetch_assoc($qt))) $tombola_count = intval($rt['c']);

// last inscription date
$last_insc = null;
$q2 = @mysqli_query($con, "SELECT MAX(ds) as last_ds FROM participation WHERE `id-membre` = '".intval($uid)."'");
if ($q2 && ($r2 = mysqli_fetch_assoc($q2))) $last_insc = $r2['last_ds'];

// If a specific activity uid is provided, fetch its title to display in header
$activity_title = null;
$activity_date = null;
if (isset($_GET['uid']) && is_numeric($_GET['uid'])) {
    $aid = intval($_GET['uid']);
    $qa = @mysqli_query($con, "SELECT `titre-activite`, `date_depart` FROM activite WHERE `id-activite` = '".intval($aid)."' LIMIT 1");
    if ($qa && ($ra = mysqli_fetch_assoc($qa))) { $activity_title = $ra['titre-activite']; $activity_date = $ra['date_depart']; }
}

// basic stats (best-effort, tolerant to missing columns)
$stats = ['buyins' => 0, 'parts' => 0, 'gains' => 0, 'gains_sum' => 0, 'net' => 0, 'victories' => 0, 'podiums' => 0, 'recaves' => 0, 'best_gain' => 0];
// Sum total expenses (buyin + rake + recaves/addons) for this member, excluding desinscrit
$q3 = @mysqli_query($con, "SELECT COUNT(p.`id-activite`) AS parts, COALESCE(SUM(COALESCE(a.buyin, 0) + COALESCE(a.rake, 0) + (COALESCE(p.recave,0) * COALESCE(a.recave_montant,0)) + (COALESCE(p.addon,0) * COALESCE(a.recave_montant,0))),0) AS buyins FROM participation p LEFT JOIN activite a ON a.`id-activite` = p.`id-activite` WHERE p.`id-membre` = '".intval($uid)."' AND COALESCE(p.`option`, 'None') NOT IN ('Desinscrit', 'None')");
if ($q3 && ($r3 = mysqli_fetch_assoc($q3))) { $stats['parts'] = intval($r3['parts']); $stats['buyins'] = intval(round(floatval($r3['buyins']))); }
// victories, podiums, recaves, best_gain (count only real participations)
$q4 = @mysqli_query($con, "SELECT SUM(CASE WHEN COALESCE(p.classement,0)=1 AND COALESCE(p.gain,0)>0 THEN 1 ELSE 0 END) AS victories, SUM(CASE WHEN COALESCE(p.classement,999)>0 AND COALESCE(p.classement,999)<=3 THEN 1 ELSE 0 END) AS podiums, COALESCE(MAX(COALESCE(p.gain,0)),0) AS best_gain FROM participation p WHERE p.`id-membre` = '".intval($uid)."' AND COALESCE(p.`option`, 'None') NOT IN ('Desinscrit', 'None')");
if ($q4 && ($r4 = mysqli_fetch_assoc($q4))) { $stats['victories'] = intval($r4['victories']); $stats['podiums'] = intval($r4['podiums']); $stats['best_gain'] = intval($r4['best_gain']); }
// gains: count how many participations had a positive gain, and also fetch sum
$qg = @mysqli_query($con, "SELECT COALESCE(SUM(COALESCE(p.gain,0)),0) AS gains_sum, COALESCE(SUM(COALESCE(p.gain_total,0)),0) AS gains_total_sum, SUM(CASE WHEN COALESCE(p.gain,0)>0 THEN 1 ELSE 0 END) AS gains_count FROM participation p WHERE p.`id-membre` = '".intval($uid)."'");
if ($qg && ($rg = mysqli_fetch_assoc($qg))) {
    $stats['gains'] = intval($rg['gains_count']);
    // keep total sum available if needed elsewhere
    $gains_sum = intval($rg['gains_sum']);
    if ($gains_sum === 0 && !empty($rg['gains_total_sum'])) {
        $gains_sum = intval(round(floatval($rg['gains_total_sum'])));
    }
    $stats['gains_sum'] = $gains_sum;
}
// recaves: sum the recave count recorded on participation rows (p.recave)
$q5 = @mysqli_query($con, "SELECT COALESCE(SUM(COALESCE(p.recave,0)),0) AS recaves FROM participation p WHERE p.`id-membre` = '".intval($uid)."'");
if ($q5 && ($r5 = mysqli_fetch_assoc($q5))) { $stats['recaves'] = intval($r5['recaves']); }

// compute net = total gains sum - total buyins
$gsum = isset($stats['gains_sum']) ? floatval($stats['gains_sum']) : 0;
$buyins_total = isset($stats['buyins']) ? floatval($stats['buyins']) : 0;
$stats['net'] = intval(round($gsum - $buyins_total));

// percentages relative to played parts
$parts_total = max(0, intval($stats['parts']));
$victory_pct = $parts_total > 0 ? round(intval($stats['victories']) / $parts_total * 100, 1) : 0;
$podium_pct = $parts_total > 0 ? round(intval($stats['podiums']) / $parts_total * 100, 1) : 0;
$recave_pct = $parts_total > 0 ? round(intval($stats['recaves']) / $parts_total * 100, 1) : 0;

// bonus inscription tokens: sum participation.jetons_bonus_ins (exclude Desinscrit/None)
$q_bonus = @mysqli_query($con, "SELECT COALESCE(SUM(COALESCE(p.jetons_bonus_ins,0)),0) AS bonus_ins FROM participation p WHERE p.`id-membre` = '".intval($uid)."' AND COALESCE(p.`option`, 'None') NOT IN ('Desinscrit', 'None')");
if ($q_bonus && ($rb = mysqli_fetch_assoc($q_bonus))) { $stats['bonus_ins'] = intval($rb['bonus_ins']); } else { $stats['bonus_ins'] = 0; }

// avatar URL
$avatar_url = 'https://viendez.com/images/noprofil.jpg';
if (!empty($user['photo'])) {
    $avatar_url = 'https://viendez.com/images/faces/' . rawurlencode(basename($user['photo']));
}

function fmt_money($n){ return number_format($n,0,',',' ') . ' €'; }
?><!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Profil - <?php echo htmlspecialchars($user['pseudo']); ?></title>
    <style>
        /* Dark backdrop */
        body{background:rgba(0,0,0,0.85);font-family:system-ui, -apple-system, 'Segoe UI', Roboto, Arial;margin:0;padding:18px;color:#eef6fb}
        /* Centered sheet */
        .sheet{max-width:520px;margin:18px auto;background:#071019;color:#eef6fb;border-radius:18px;padding:16px;box-shadow:0 12px 40px rgba(0,0,0,0.6)}
        .avatar{display:block;flex:none;width:180px;height:180px;border-radius:50%;overflow:hidden;margin:0 auto;line-height:0}
        .avatar img{width:100%;height:100%;object-fit:cover}
        .avatar-upload-form{display:none}
        .avatar-trigger{display:flex;flex-direction:column;align-items:center;justify-content:center;width:-moz-fit-content;width:fit-content;max-width:100%;margin:6px auto 0;background:none;border:0;padding:0;color:inherit;cursor:pointer;text-align:center}
        .avatar-wrap{position:relative;display:inline-block}
        .avatar-edit-badge{position:absolute;right:-6px;bottom:-6px;width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:#111;color:#fff;font-size:18px;font-weight:800;box-shadow:0 8px 18px rgba(0,0,0,0.35)}
        .avatar-hint{margin-top:6px;color:#9aa6b1;font-size:11px}
        .profile-identity{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;margin-bottom:12px}
        .profile-identity-text{display:flex;flex-direction:column;align-items:center;text-align:center}
        .avatar-trigger:hover .avatar-edit-badge{transform:scale(1.05)}
        .avatar-modal{position:fixed;inset:0;background:rgba(1,8,15,0.88);display:none;align-items:center;justify-content:center;padding:12px;z-index:60}
        .avatar-modal.is-open{display:flex}
        .avatar-modal-card{width:min(100%,380px);max-height:min(88vh,720px);overflow:auto;background:#071019;border:1px solid rgba(255,255,255,0.08);border-radius:20px;padding:16px;box-shadow:0 16px 48px rgba(0,0,0,0.48)}
        .avatar-modal-title{font-size:18px;font-weight:800;margin:0 0 4px}
        .avatar-modal-subtitle{margin:0 0 14px;color:#9aa6b1;font-size:13px;line-height:1.4}
        .avatar-editor{display:flex;justify-content:center;margin:0 auto 12px}
        .avatar-canvas-wrap{position:relative;width:min(100%,248px);aspect-ratio:1/1;border-radius:24px;overflow:hidden;background:linear-gradient(180deg,#112130,#071019);border:1px solid rgba(255,255,255,0.08);touch-action:none}
        .avatar-canvas-wrap::after{content:'';position:absolute;inset:12px;border:2px solid rgba(255,255,255,0.65);border-radius:28px;box-shadow:0 0 0 200vmax rgba(0,0,0,0.18);pointer-events:none}
        .avatar-canvas{display:block;width:100%;height:100%}
        .avatar-preview-row{display:flex;align-items:center;justify-content:space-between;gap:12px;margin:0 0 12px}
        .avatar-preview-label{color:#9aa6b1;font-size:13px;font-weight:700}
        .avatar-preview-bubble{width:64px;height:64px;border-radius:50%;overflow:hidden;border:2px solid rgba(8,176,255,0.5);background:#0c1823;flex:none}
        .avatar-preview-bubble img{width:100%;height:100%;object-fit:cover;display:block}
        .avatar-controls{display:grid;gap:12px}
        .avatar-control label{display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;color:#c9d6e2;font-size:13px;font-weight:700}
        .avatar-range{width:100%}
        .avatar-helper{margin:0;color:#9aa6b1;font-size:12px;line-height:1.4}
        .avatar-modal-actions{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-top:14px}
        .avatar-action{border:0;border-radius:12px;padding:11px 10px;font-weight:800;font-size:13px;cursor:pointer}
        .avatar-action.secondary{background:rgba(255,255,255,0.07);color:#eef6fb}
        .avatar-action.primary{background:#08b0ff;color:#04131d}
        .avatar-action.ghost{background:transparent;color:#9aa6b1;border:1px solid rgba(255,255,255,0.1)}
        .avatar-status{min-height:18px;margin-top:10px;color:#ffb86b;font-size:12px;font-weight:700}
        .flash{margin:0 0 14px;padding:10px 12px;border-radius:12px;font-size:14px;font-weight:700}
        .flash.success{background:rgba(22,163,74,0.14);color:#7cf0a8;border:1px solid rgba(22,163,74,0.28)}
        .flash.error{background:rgba(255,77,77,0.12);color:#ff9c9c;border:1px solid rgba(255,77,77,0.24)}
        .name{text-align:center;font-weight:800;font-size:20px;margin-top:0}
        /* Cards use subtle contrast on dark sheet */
        .card{background:rgba(255,255,255,0.03);padding:12px;border-radius:12px;margin-top:12px;border:1px solid rgba(255,255,255,0.03)}
        .card-row{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid rgba(255,255,255,0.03)}
        .card-row:last-child{border-bottom:none}
        .label{color:#9aa6b1}
        .value{font-weight:700;color:#eef6fb}
        .stats{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-top:12px}
        .stat{background:rgba(255,255,255,0.02);padding:10px;border-radius:10px;text-align:center}
        .stat .num{font-weight:800;font-size:18px}
        .stat .sub{color:#9aa6b1;font-size:12px}
        .top-actions{position:fixed;right:18px;top:18px;display:flex;gap:10px;z-index:20}
        .top-action{background:rgba(255,255,255,0.06);padding:8px 12px;border-radius:20px;border:0;color:#ff9d3b;font-weight:700;backdrop-filter:blur(4px);cursor:pointer;text-decoration:none;display:inline-flex;align-items:center}
        .profile-footer-action{display:flex;justify-content:center;margin-top:18px}
        .profile-footer-action .top-action.logout{color:#ff6b6b;min-width:160px;justify-content:center}
        @media (max-width: 480px){
            body{padding:12px}
            .sheet{margin:10px auto;padding:14px;border-radius:16px}
            .avatar{width:132px;height:132px}
            .avatar-edit-badge{width:32px;height:32px;font-size:15px}
            .profile-identity{gap:8px;margin-bottom:10px}
            .avatar-modal-card{width:min(100%,340px);padding:14px;border-radius:18px}
            .avatar-canvas-wrap{width:min(100%,208px)}
            .avatar-modal-subtitle,.avatar-helper,.avatar-status{font-size:12px}
            .avatar-preview-bubble{width:56px;height:56px}
            .avatar-modal-actions{grid-template-columns:1fr}
            .avatar-action{padding:12px 10px}
        }
    </style>
</head>
<body>
    <script>
    // Si on est sur profile.php avec un uid, on remplace l'entrée d'historique par quickview.php?uid=xxx
    (function(){
        var params = new URLSearchParams(window.location.search);
        var uid = params.get('uid');
        if (uid && window.location.pathname.endsWith('/profile.php')) {
            var quickviewUrl = '/panel/quickview.php?uid=' + encodeURIComponent(uid);
            history.replaceState({}, '', quickviewUrl);
        }
    })();
    </script>
    <div class="top-actions">
        <button class="top-action" onclick="closeProfileOrModal();">Fermer</button>
        <script>
        function closeProfileOrModal() {
            var modal = document.getElementById('avatarCropModal');
            if (modal && modal.classList.contains('is-open')) {
                // Appelle la fonction JS de fermeture du modal si elle existe
                if (typeof closeModal === 'function') closeModal();
                else modal.classList.remove('is-open');
            } else {
                // Redirige explicitement vers quickview.php avec l'uid si présent
                var params = new URLSearchParams(window.location.search);
                var uid = params.get('uid');
                if (uid) {
                    window.location.href = '/panel/quickview.php?uid=' + encodeURIComponent(uid);
                } else {
                    window.location.href = '/panel/quickview.php';
                }
            }
        }
        </script>
    </div>
    <div class="sheet">
        <?php if (!empty($profile_flash['message'])): ?>
            <div class="flash <?php echo (($profile_flash['type'] ?? '') === 'success') ? 'success' : 'error'; ?>"><?php echo htmlspecialchars($profile_flash['message']); ?></div>
        <?php endif; ?>

        <form id="avatarUploadForm" class="avatar-upload-form" method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="upload_avatar">
            <input type="file" name="fileToUpload" id="avatarFileInput" accept="image/png,image/jpeg,image/gif,image/webp">
        </form>

        <div class="profile-identity">
            <button type="button" class="avatar-trigger" onclick="document.getElementById('avatarFileInput').click();">
                <span class="avatar-wrap">
                    <span class="avatar"><img id="profileAvatarImage" src="<?php echo htmlspecialchars($avatar_url); ?>" alt="avatar"></span>
                    <span class="avatar-edit-badge">📷</span>
                </span>
            </button>
            <div class="profile-identity-text">
                <div class="name"><span style="color:#16a34a"><?php echo htmlspecialchars($user['pseudo']); ?></span></div>
                <div class="avatar-hint">Touchez la photo pour la changer</div>
            </div>
        </div>
        <div id="avatarCropModal" class="avatar-modal" aria-hidden="true">
            <div class="avatar-modal-card" role="dialog" aria-modal="true" aria-labelledby="avatarCropTitle">
                <h2 id="avatarCropTitle" class="avatar-modal-title">Ajuster votre photo</h2>
                <p class="avatar-modal-subtitle">Déplacez l’image avec le doigt ou la souris, puis ajustez le zoom avant d’enregistrer.</p>
                <div class="avatar-editor">
                    <div id="avatarCanvasWrap" class="avatar-canvas-wrap">
                        <canvas id="avatarCropCanvas" class="avatar-canvas" width="560" height="560"></canvas>
                    </div>
                </div>
                <div class="avatar-preview-row">
                    <div>
                        <div class="avatar-preview-label">Aperçu</div>
                        <p class="avatar-helper">Le serveur garde aussi un recadrage de secours pour uniformiser l’avatar.</p>
                    </div>
                    <div class="avatar-preview-bubble"><img id="avatarPreviewImage" src="<?php echo htmlspecialchars($avatar_url); ?>" alt="aperçu avatar"></div>
                </div>
                <div class="avatar-controls">
                    <div class="avatar-control">
                        <label for="avatarZoomRange"><span>Zoom</span><span id="avatarZoomValue">100%</span></label>
                        <input id="avatarZoomRange" class="avatar-range" type="range" min="1" max="3" step="0.01" value="1">
                    </div>
                </div>
                <div id="avatarCropStatus" class="avatar-status"></div>
                <div class="avatar-modal-actions">
                    <button type="button" id="avatarCancelButton" class="avatar-action ghost">Annuler</button>
                    <button type="button" id="avatarChooseOtherButton" class="avatar-action secondary">Autre photo</button>
                    <button type="button" id="avatarSaveButton" class="avatar-action primary">Enregistrer</button>
                </div>
            </div>
        </div>
        <div class="card">
            <div style="font-weight:700;margin-bottom:8px"><?php echo !empty($activity_title) ? htmlspecialchars($activity_title) : ((!empty($last_insc))? fmt_fr_date($last_insc, 'EEEE d MMM (dd/MM)') : '—'); ?></div>
            <div class="card-row"><div class="label">Inscription</div><div class="value"><?php echo $last_insc ? fmt_fr_date($last_insc, 'd MMM HH:mm') : '—'; ?><?php $bi = intval(isset($stats['bonus_ins']) ? $stats['bonus_ins'] : 0); if ($bi > 0) { echo ' (<span style="color:#ffd100">+'.intval(min($bi, 5000)).'</span>)'; } ?></div></div>
        </div>

        <div class="card">
            <?php $challenge_uid = isset($_GET['uid']) && is_numeric($_GET['uid']) ? intval($_GET['uid']) : null;
            $challenge_rank_display = '#—';
            if ($challenge_uid && !empty($con)) {
                $today = date('Y-m-d');
                $activity_cols = array();
                $colres = @mysqli_query($con, "SHOW COLUMNS FROM activite");
                if ($colres) { while ($cr = mysqli_fetch_assoc($colres)) { $activity_cols[] = $cr['Field']; } }
                $challenge_col = null;
                foreach (array('id_challenge','id-challenge','challenge_id','idchall','id_chall') as $c) { if (in_array($c, $activity_cols)) { $challenge_col = $c; break; } }
                $challenge_id = null;
                $actq = @mysqli_query($con, "SELECT * FROM activite WHERE `id-activite`='".intval($challenge_uid)."' LIMIT 1");
                if ($actq && ($act = mysqli_fetch_assoc($actq))) { foreach (array('id_challenge','id-challenge','challenge_id','idchall','id_chall') as $c) { if (isset($act[$c]) && $act[$c] !== '') { $challenge_id = intval($act[$c]); $challenge_col = $c; break; } } }
                if ($challenge_id && $challenge_col) {
                    $where = "(a.`" . $challenge_col . "` = " . $challenge_id . ") AND a.date_depart < '" . $today . "'";
                    $sql = "SELECT p.`id-membre` AS mid, COALESCE(SUM(COALESCE(p.points,0)),0) AS pts, SUM(CASE WHEN COALESCE(p.classement,0)=1 AND COALESCE(p.gain,0)>0 THEN 1 ELSE 0 END) AS vic FROM participation p JOIN activite a ON a.`id-activite` = p.`id-activite` WHERE " . $where . " GROUP BY p.`id-membre` ORDER BY pts DESC, vic DESC";
                    $q = @mysqli_query($con, $sql);
                    if ($q) { $i = 0; while ($r = mysqli_fetch_assoc($q)) { $i++; if (intval($r['mid']) === intval($uid)) { $challenge_rank_display = '#' . $i; break; } } }
                }
            }
            ?>
            <div class="card-row"><div class="label">Rang Challenge</div><div class="value"><?php echo htmlspecialchars($challenge_rank_display); ?> <a id="link-challenge" href="/panel/challenge_rank.php<?php echo $challenge_uid? '?uid=' . $challenge_uid : ''; ?>" style="margin-left:8px;color:#ff9d3b;font-weight:700">Visualiser</a></div></div>
            <div class="card-row"><div class="label">Vos Tickets de Tombola</div><div class="value"><?php echo intval($tombola_count); ?> <a id="link-tombola" href="/panel/tickets_tombolas.php?id=<?php echo intval($uid); ?>" onclick="window.location.href=this.href;" style="margin-left:8px;color:#16a34a;font-weight:700">Voir</a></div></div>
            <div class="card-row"><div class="label">Notes (Traker)</div><div class="value">— <a id="link-notes" href="#" style="margin-left:8px;color:#08b0ff;font-weight:700">Voir</a></div></div>
        </div>

        <div style="font-weight:700;margin-top:12px;margin-bottom:8px">Statistiques</div>
        <div class="card" style="padding:12px">
            <div style="display:flex;gap:12px">
                <div style="flex:1;text-align:center">
                    <div class="num" style="font-weight:800;color:#9aa6b1"><?php echo htmlspecialchars(number_format($stats['buyins'],0,',',' ')); ?> €</div>
                    <div class="sub"><?php echo intval($stats['parts']); ?> parties</div>
                </div>
                <div style="flex:1;text-align:center">
                    <div class="num" style="font-weight:800;color:#16a34a"><?php echo htmlspecialchars(number_format(isset($stats['gains_sum']) ? $stats['gains_sum'] : 0,0,',',' ')); ?> €</div>
                    <div class="sub"><?php echo intval(isset($stats['gains']) ? $stats['gains'] : 0); ?> fois</div>
                </div>
                <div style="flex:1;text-align:center">
                    <div class="num" style="font-weight:800;color:<?php echo ($stats['net'] < 0) ? '#ff4d4d' : '#16a34a'; ?>"><?php echo htmlspecialchars(number_format($stats['net'],0,',',' ')); ?> €</div>
                    <div class="sub">NET</div>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:12px">
                <div style="text-align:center"><div class="num" style="color:#ffd100"><?php echo intval($stats['victories']); ?></div><div class="sub">Victoires <span style="font-size:11px;color:#9aa6b1">(<?php echo $victory_pct; ?>%)</span></div></div>
                <div style="text-align:center"><div class="num" style="color:#ff9d3b"><?php echo intval($stats['podiums']); ?></div><div class="sub">ITM <span style="font-size:11px;color:#9aa6b1">(<?php echo $podium_pct; ?>%)</span></div></div>
                <div style="text-align:center"><div class="num" style="color:#08b0ff"><?php echo intval($stats['recaves']); ?></div><div class="sub">Recaves <span style="font-size:11px;color:#9aa6b1">(<?php echo $recave_pct; ?>%)</span></div></div>
            </div>

            <div style="border-top:1px solid rgba(0,0,0,0.06);margin-top:12px;padding-top:10px;display:flex;justify-content:space-between;align-items:center">
                <div>Meilleur gain</div>
                <div style="font-weight:800;color:#16a34a"><?php echo htmlspecialchars(number_format($stats['best_gain'],0,',',' ')) . ' €'; ?></div>
            </div>
        </div>

        <div class="profile-footer-action">
            <a class="top-action logout" href="/panel/logout.php">Déconnexion</a>
        </div>
    </div>
    <script>
        const avatarFileInput = document.getElementById('avatarFileInput');
        const avatarUploadForm = document.getElementById('avatarUploadForm');
        const avatarCropModal = document.getElementById('avatarCropModal');
        const avatarCanvasWrap = document.getElementById('avatarCanvasWrap');
        const avatarCropCanvas = document.getElementById('avatarCropCanvas');
        const avatarPreviewImage = document.getElementById('avatarPreviewImage');
        const profileAvatarImage = document.getElementById('profileAvatarImage');
        const avatarZoomRange = document.getElementById('avatarZoomRange');
        const avatarZoomValue = document.getElementById('avatarZoomValue');
        const avatarCropStatus = document.getElementById('avatarCropStatus');
        const avatarCancelButton = document.getElementById('avatarCancelButton');
        const avatarChooseOtherButton = document.getElementById('avatarChooseOtherButton');
        const avatarSaveButton = document.getElementById('avatarSaveButton');

        if (avatarFileInput && avatarUploadForm && avatarCropModal && avatarCanvasWrap && avatarCropCanvas && avatarPreviewImage && avatarZoomRange && avatarSaveButton) {
            const canvasContext = avatarCropCanvas.getContext('2d');
            const state = {
                image: null,
                imageUrl: '',
                scale: 1,
                minScale: 1,
                offsetX: 0,
                offsetY: 0,
                isDragging: false,
                isPinching: false,
                dragStartX: 0,
                dragStartY: 0,
                startOffsetX: 0,
                startOffsetY: 0,
                pinchStartDistance: 0,
                pinchStartScale: 1,
                pinchAnchorX: 0,
                pinchAnchorY: 0,
                selectedName: 'avatar.jpg'
            };

            const getCanvasSize = () => {
                const wrapRect = avatarCanvasWrap.getBoundingClientRect();
                const size = Math.max(208, Math.round(Math.min(wrapRect.width || 248, 280)));
                if (avatarCropCanvas.width !== size || avatarCropCanvas.height !== size) {
                    avatarCropCanvas.width = size;
                    avatarCropCanvas.height = size;
                    avatarCropCanvas.style.width = size + 'px';
                    avatarCropCanvas.style.height = size + 'px';
                }
                return avatarCropCanvas.width;
            };

            const revokeImageUrl = () => {
                if (state.imageUrl) {
                    URL.revokeObjectURL(state.imageUrl);
                    state.imageUrl = '';
                }
            };

            const clampOffsets = () => {
                if (!state.image) {
                    return;
                }
                const size = getCanvasSize();
                const drawWidth = state.image.width * state.scale;
                const drawHeight = state.image.height * state.scale;
                const minOffsetX = Math.min(0, size - drawWidth);
                const minOffsetY = Math.min(0, size - drawHeight);
                const maxOffsetX = Math.max(0, size - drawWidth);
                const maxOffsetY = Math.max(0, size - drawHeight);

                if (drawWidth <= size) {
                    state.offsetX = (size - drawWidth) / 2;
                } else {
                    state.offsetX = Math.min(0, Math.max(minOffsetX, state.offsetX));
                }

                if (drawHeight <= size) {
                    state.offsetY = (size - drawHeight) / 2;
                } else {
                    state.offsetY = Math.min(0, Math.max(minOffsetY, state.offsetY));
                }
            };

            const drawAvatarCanvas = () => {
                const size = getCanvasSize();
                canvasContext.clearRect(0, 0, size, size);
                canvasContext.fillStyle = '#0a1722';
                canvasContext.fillRect(0, 0, size, size);

                if (!state.image) {
                    return;
                }

                clampOffsets();
                canvasContext.drawImage(
                    state.image,
                    state.offsetX,
                    state.offsetY,
                    state.image.width * state.scale,
                    state.image.height * state.scale
                );

                updatePreview();
            };

            const updatePreview = () => {
                const dataUrl = avatarCropCanvas.toDataURL('image/jpeg', 0.92);
                avatarPreviewImage.src = dataUrl;
            };

            const syncZoomLabel = () => {
                avatarZoomValue.textContent = Math.round(state.scale * 100) + '%';
            };

            const resetStatus = (message = '') => {
                avatarCropStatus.textContent = message;
            };

            const openModal = () => {
                avatarCropModal.classList.add('is-open');
                avatarCropModal.setAttribute('aria-hidden', 'false');
                document.body.style.overflow = 'hidden';
                drawAvatarCanvas();
            };

            const closeModal = () => {
                avatarCropModal.classList.remove('is-open');
                avatarCropModal.setAttribute('aria-hidden', 'true');
                document.body.style.overflow = '';
                state.isDragging = false;
                state.isPinching = false;
                resetStatus('');
            };

            const getTouchDistance = (event) => {
                if (!event.touches || event.touches.length < 2) {
                    return 0;
                }
                const first = event.touches[0];
                const second = event.touches[1];
                return Math.hypot(second.clientX - first.clientX, second.clientY - first.clientY);
            };

            const getTouchCenter = (event) => {
                const rect = avatarCropCanvas.getBoundingClientRect();
                const first = event.touches[0];
                const second = event.touches[1];
                return {
                    x: ((first.clientX + second.clientX) / 2) - rect.left,
                    y: ((first.clientY + second.clientY) / 2) - rect.top
                };
            };

            const setScaleAroundPoint = (nextScale, anchorX, anchorY) => {
                const clampedScale = Math.max(state.minScale, Math.min(parseFloat(avatarZoomRange.max || nextScale), nextScale));
                const previousScale = state.scale || clampedScale;
                const imagePointX = (anchorX - state.offsetX) / previousScale;
                const imagePointY = (anchorY - state.offsetY) / previousScale;
                state.scale = clampedScale;
                state.offsetX = anchorX - imagePointX * state.scale;
                state.offsetY = anchorY - imagePointY * state.scale;
                avatarZoomRange.value = String(state.scale);
                syncZoomLabel();
                drawAvatarCanvas();
            };

            const initializeImageState = (image, fileName) => {
                state.image = image;
                state.selectedName = fileName || 'avatar.jpg';

                const size = getCanvasSize();
                state.minScale = Math.max(size / image.width, size / image.height);
                state.scale = state.minScale;
                avatarZoomRange.min = String(state.minScale);
                avatarZoomRange.max = String(Math.max(state.minScale + 2, state.minScale * 3));
                avatarZoomRange.value = String(state.scale);
                state.offsetX = (size - image.width * state.scale) / 2;
                state.offsetY = (size - image.height * state.scale) / 2;
                syncZoomLabel();
                drawAvatarCanvas();
                openModal();
            };

            const loadSelectedImage = (file) => {
                if (!file || !file.type.startsWith('image/')) {
                    resetStatus('Sélectionnez une image valide.');
                    return;
                }

                revokeImageUrl();
                state.imageUrl = URL.createObjectURL(file);
                const image = new Image();
                image.onload = () => {
                    initializeImageState(image, file.name);
                };
                image.onerror = () => {
                    resetStatus('Impossible de charger cette image.');
                };
                image.src = state.imageUrl;
            };

            const getPointerPosition = (event) => {
                const rect = avatarCropCanvas.getBoundingClientRect();
                const point = event.touches ? event.touches[0] : event;
                return {
                    x: point.clientX - rect.left,
                    y: point.clientY - rect.top
                };
            };

            const startDrag = (event) => {
                if (!state.image) {
                    return;
                }
                event.preventDefault();
                if (event.touches && event.touches.length === 2) {
                    state.isDragging = false;
                    state.isPinching = true;
                    state.pinchStartDistance = getTouchDistance(event);
                    state.pinchStartScale = state.scale;
                    const center = getTouchCenter(event);
                    state.pinchAnchorX = center.x;
                    state.pinchAnchorY = center.y;
                    return;
                }
                const point = getPointerPosition(event);
                state.isDragging = true;
                state.isPinching = false;
                state.dragStartX = point.x;
                state.dragStartY = point.y;
                state.startOffsetX = state.offsetX;
                state.startOffsetY = state.offsetY;
            };

            const moveDrag = (event) => {
                if (!state.image) {
                    return;
                }
                if (event.touches && event.touches.length === 2) {
                    event.preventDefault();
                    if (!state.isPinching) {
                        state.isDragging = false;
                        state.isPinching = true;
                        state.pinchStartDistance = getTouchDistance(event);
                        state.pinchStartScale = state.scale;
                        const center = getTouchCenter(event);
                        state.pinchAnchorX = center.x;
                        state.pinchAnchorY = center.y;
                    }
                    const pinchDistance = getTouchDistance(event);
                    if (state.pinchStartDistance > 0 && pinchDistance > 0) {
                        const ratio = pinchDistance / state.pinchStartDistance;
                        setScaleAroundPoint(state.pinchStartScale * ratio, state.pinchAnchorX, state.pinchAnchorY);
                    }
                    return;
                }
                if (!state.isDragging) {
                    return;
                }
                event.preventDefault();
                const point = getPointerPosition(event);
                state.offsetX = state.startOffsetX + (point.x - state.dragStartX);
                state.offsetY = state.startOffsetY + (point.y - state.dragStartY);
                drawAvatarCanvas();
            };

            const endDrag = () => {
                state.isDragging = false;
                state.isPinching = false;
            };

            const submitCroppedAvatar = () => {
                if (!state.image) {
                    resetStatus('Commencez par choisir une image.');
                    return;
                }

                resetStatus('Préparation de l’avatar…');
                avatarCropCanvas.toBlob((blob) => {
                    if (!blob) {
                        resetStatus('Impossible de préparer l’image recadrée.');
                        return;
                    }

                    const croppedFile = new File([blob], state.selectedName.replace(/\.[^.]+$/, '') + '-avatar.jpg', {
                        type: 'image/jpeg'
                    });
                    const transfer = new DataTransfer();
                    transfer.items.add(croppedFile);
                    avatarFileInput.files = transfer.files;

                    const previewUrl = URL.createObjectURL(blob);
                    if (profileAvatarImage) {
                        profileAvatarImage.src = previewUrl;
                    }

                    avatarUploadForm.submit();
                }, 'image/jpeg', 0.92);
            };

            avatarFileInput.addEventListener('change', () => {
                if (avatarFileInput.files && avatarFileInput.files.length > 0) {
                    resetStatus('');
                    loadSelectedImage(avatarFileInput.files[0]);
                }
            });

            avatarZoomRange.addEventListener('input', () => {
                if (!state.image) {
                    return;
                }
                const size = getCanvasSize();
                const nextScale = Math.max(state.minScale, parseFloat(avatarZoomRange.value || state.minScale));
                setScaleAroundPoint(nextScale, size / 2, size / 2);
            });

            avatarCropCanvas.addEventListener('mousedown', startDrag);
            avatarCropCanvas.addEventListener('touchstart', startDrag, { passive: false });
            window.addEventListener('mousemove', moveDrag);
            window.addEventListener('touchmove', moveDrag, { passive: false });
            window.addEventListener('mouseup', endDrag);
            window.addEventListener('touchend', endDrag);
            window.addEventListener('resize', drawAvatarCanvas);

            avatarCancelButton.addEventListener('click', () => {
                avatarFileInput.value = '';
                closeModal();
            });

            avatarChooseOtherButton.addEventListener('click', () => {
                avatarFileInput.click();
            });

            avatarSaveButton.addEventListener('click', submitCroppedAvatar);

            avatarCropModal.addEventListener('click', (event) => {
                if (event.target === avatarCropModal) {
                    closeModal();
                }
            });

            window.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && avatarCropModal.classList.contains('is-open')) {
                    closeModal();
                }
            });
        }
    </script>
</body>
</html>
