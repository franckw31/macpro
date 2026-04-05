<?php
session_start();
include(dirname(__DIR__) . '/panel/include/config.php'); // $con
// Optional local config to set VOICERSS_API_KEY constant
@include(__DIR__ . '/config.local.php');

// Configuration: définir votre clé API VoiceRSS ici (https://www.voicerss.org/)
// Vous pouvez aussi la placer dans une variable d'environnement VOICERSS_API_KEY.
$VOICERSS_API_KEY = getenv('VOICERSS_API_KEY') ?: 'a3b1d56ec51c458c98cdbfc95f9c8328';
if (defined('VOICERSS_API_KEY')) { $VOICERSS_API_KEY = VOICERSS_API_KEY; }

function clean_text($t) {
  $t = trim($t);
  $t = preg_replace('/\s+/', ' ', $t);
  return $t;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: index.php');
  exit;
}

$text = isset($_POST['text']) ? clean_text($_POST['text']) : '';
$lang = isset($_POST['lang']) ? strtolower(trim($_POST['lang'])) : 'fr-fr';
$voice = isset($_POST['voice']) ? trim($_POST['voice']) : '';
$speed = isset($_POST['speed']) ? trim($_POST['speed']) : '';

if ($text === '' || strlen($text) > 500) {
  $_SESSION['tts_msg'] = 'Texte vide ou trop long.';
  header('Location: index.php');
  exit;
}

// Clamp speed to VoiceRSS allowed range (-10 to 10)
if (!is_numeric($speed)) { $speed = 0; }
$speed = (int)$speed;
if ($speed < -10) { $speed = -10; }
if ($speed > 10) { $speed = 10; }

// Validation basique de la langue
$allowed_langs = [
  'fr-fr','fr-ca','fr-ch',
  'en-us','en-gb','en-au',
  'es-es','es-mx',
  'de-de','it-it',
  'pt-pt','pt-br',
  'nl-nl'
];
if (!in_array($lang, $allowed_langs, true)) { $lang = 'fr-fr'; }

// Voices allowed per language (VoiceRSS names)
$voices_by_lang = [
  'fr-fr' => ['Bette','Iva','Axel'],
  'fr-ca' => ['Chantal'],
  'fr-ch' => ['Bette','Iva','Axel'],
  'en-us' => ['Linda','Amy','Mary','John','Mike'],
  'en-gb' => ['Alice','Nancy','Lily','Harry','Peter'],
  'en-au' => ['Lisa','Olivia','Jack'],
  'es-es' => ['Conchita','Lucia','Enrique'],
  'es-mx' => ['Mia'],
  'de-de' => ['Lena','Marlene','Hans'],
  'it-it' => ['Giorgio','Carla'],
  'pt-pt' => ['Ines','Cristiano'],
  'pt-br' => ['Camila','Vitoria','Ricardo'],
  'nl-nl' => ['Lotte','Bram'],
];

$selected_voice = '';
if (!empty($voice) && isset($voices_by_lang[$lang])) {
  // Match case-insensitively
  foreach ($voices_by_lang[$lang] as $v) {
    if (strcasecmp($v, $voice) === 0) { $selected_voice = $v; break; }
  }
}

// Default voice fallback per language
if ($selected_voice === '' && isset($voices_by_lang[$lang])) {
  $selected_voice = $voices_by_lang[$lang][0];
}

// Préparer nom de fichier
$stamp = date('Ymd_His');
$rand = bin2hex(random_bytes(4));
$filename = "tts_{$lang}_{$stamp}_{$rand}.mp3";
$savePath = __DIR__ . '/audio/' . $filename;

// Vérifier dossier audio
if (!is_dir(__DIR__ . '/audio')) { mkdir(__DIR__ . '/audio', 0775, true); }

// Si pas de clé API, informer.
if (!$VOICERSS_API_KEY) {
  $_SESSION['tts_msg'] = 'Aucune clé API VoiceRSS détectée. Merci de configurer VOICERSS_API_KEY.';
  header('Location: index.php');
  exit;
}

// Appel VoiceRSS (format MP3)
// Doc: https://www.voicerss.org/api/
// Example: https://api.voicerss.org/?key=YOUR_KEY&hl=fr-fr&src=Bonjour&f=mp3&c=UTF-8
$apiParams = [
  'key' => $VOICERSS_API_KEY,
  'hl'  => $lang,
  'src' => $text,
  'f'   => 'mp3',
  'r'   => (string)$speed,
  'c'   => 'UTF-8'
];
if ($selected_voice) { $apiParams['v'] = $selected_voice; }

$apiUrl = 'https://api.voicerss.org/?' . http_build_query($apiParams);

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 20);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

if ($response === false || $httpCode !== 200) {
  $_SESSION['tts_msg'] = 'Erreur TTS: ' . ($err ?: ('HTTP ' . $httpCode));
  header('Location: index.php');
  exit;
}

// VoiceRSS renvoie du binaire MP3 sur succès, ou du texte en cas d'erreur.
// Heuristique: si commence par 'ERROR', c'est un message d'erreur.
if (strncmp($response, 'ERROR', 5) === 0) {
  $_SESSION['tts_msg'] = 'Erreur TTS: ' . htmlspecialchars($response);
  header('Location: index.php');
  exit;
}

// Sauver le MP3
file_put_contents($savePath, $response);

// Enregistrer en base
mysqli_query($con, "CREATE TABLE IF NOT EXISTS tts_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  text VARCHAR(500) NOT NULL,
  lang VARCHAR(20) NOT NULL,
  filename VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL,
  ip VARCHAR(64) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$ip = $_SERVER['REMOTE_ADDR'] ?? null;
$stmt = mysqli_prepare($con, "INSERT INTO tts_requests (text, lang, filename, created_at, ip) VALUES (?, ?, ?, NOW(), ?)");
if ($stmt) {
  mysqli_stmt_bind_param($stmt, 'ssss', $text, $lang, $filename, $ip);
  mysqli_stmt_execute($stmt);
  mysqli_stmt_close($stmt);
}

$_SESSION['tts_msg'] = 'Fichier généré: ' . $filename;
header('Location: index.php');
exit;
