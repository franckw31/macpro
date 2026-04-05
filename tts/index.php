<?php
session_start();
include(dirname(__DIR__) . '/panel/include/config.php'); // Provides $con

// Fetch last 20 generated files
$rows = [];
mysqli_query($con, "CREATE TABLE IF NOT EXISTS tts_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  text VARCHAR(500) NOT NULL,
  lang VARCHAR(20) NOT NULL,
  filename VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL,
  ip VARCHAR(64) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$res = mysqli_query($con, "SELECT id, text, lang, filename, created_at FROM tts_requests ORDER BY id DESC LIMIT 20");
if ($res) { while($r = mysqli_fetch_assoc($res)) { $rows[] = $r; } }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Générateur MP3 (TTS)</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="../panel/vendor/bootstrap/css/bootstrap.min.css">
  <style>
    body { padding: 20px; }
    .container { max-width: 900px; }
    .help { color:#666; font-size: 0.9em; }
  </style>
</head>
<body>
<div class="container">
  <h1 class="mb-4">Créer un fichier audio MP3 à partir d'une phrase</h1>

  <?php if (!empty($_SESSION['tts_msg'])): ?>
    <div class="alert alert-info"><?php echo htmlspecialchars($_SESSION['tts_msg']); unset($_SESSION['tts_msg']); ?></div>
  <?php endif; ?>

  <div class="card mb-4">
    <div class="card-body">
      <form id="ttsForm" method="post" action="generate.php" onsubmit="return validateForm();">
        <div class="form-group mb-3">
          <label for="text">Phrase à convertir</label>
          <textarea id="text" name="text" class="form-control" rows="3" maxlength="500" placeholder="Entrez votre phrase" required></textarea>
          <small class="help">500 caractères max.</small>
        </div>
        <div class="form-group mb-3">
          <label for="lang">Langue</label>
          <select id="lang" name="lang" class="form-control" required>
            <option value="fr-fr">Français (France)</option>
            <option value="fr-ca">Français (Canada)</option>
            <option value="fr-ch">Français (Suisse)</option>
            <option value="en-us">Anglais (US)</option>
            <option value="en-gb">Anglais (UK)</option>
            <option value="en-au">Anglais (Australie)</option>
            <option value="es-es">Espagnol (Espagne)</option>
            <option value="es-mx">Espagnol (Mexique)</option>
            <option value="de-de">Allemand</option>
            <option value="it-it">Italien</option>
            <option value="pt-pt">Portugais (Portugal)</option>
            <option value="pt-br">Portugais (Brésil)</option>
            <option value="nl-nl">Néerlandais</option>
          </select>
        </div>
        <div class="form-group mb-3">
          <label for="voice">Voix</label>
          <select id="voice" name="voice" class="form-control">
            <option value="">(voix par défaut)</option>
            <optgroup label="Français">
              <option value="Bette">Bette (fr)</option>
              <option value="Iva">Iva (fr)</option>
              <option value="Axel">Axel (fr)</option>
              <option value="Chantal">Chantal (fr-CA)</option>
            </optgroup>
            <optgroup label="Anglais">
              <option value="Linda">Linda (en-US)</option>
              <option value="Amy">Amy (en-US)</option>
              <option value="Mary">Mary (en-US)</option>
              <option value="John">John (en-US)</option>
              <option value="Mike">Mike (en-US)</option>
              <option value="Alice">Alice (en-GB)</option>
              <option value="Nancy">Nancy (en-GB)</option>
              <option value="Lily">Lily (en-GB)</option>
              <option value="Harry">Harry (en-GB)</option>
              <option value="Peter">Peter (en-GB)</option>
              <option value="Lisa">Lisa (en-AU)</option>
              <option value="Olivia">Olivia (en-AU)</option>
              <option value="Jack">Jack (en-AU)</option>
            </optgroup>
            <optgroup label="Espagnol">
              <option value="Conchita">Conchita (es-ES)</option>
              <option value="Lucia">Lucia (es-ES)</option>
              <option value="Enrique">Enrique (es-ES)</option>
              <option value="Mia">Mia (es-MX)</option>
            </optgroup>
            <optgroup label="Allemand">
              <option value="Lena">Lena (de-DE)</option>
              <option value="Marlene">Marlene (de-DE)</option>
              <option value="Hans">Hans (de-DE)</option>
            </optgroup>
            <optgroup label="Italien">
              <option value="Giorgio">Giorgio (it-IT)</option>
              <option value="Carla">Carla (it-IT)</option>
            </optgroup>
            <optgroup label="Portugais">
              <option value="Ines">Ines (pt-PT)</option>
              <option value="Cristiano">Cristiano (pt-PT)</option>
              <option value="Camila">Camila (pt-BR)</option>
              <option value="Vitoria">Vitoria (pt-BR)</option>
              <option value="Ricardo">Ricardo (pt-BR)</option>
            </optgroup>
            <optgroup label="Néerlandais">
              <option value="Lotte">Lotte (nl-NL)</option>
              <option value="Bram">Bram (nl-NL)</option>
            </optgroup>
          </select>
          <small class="help">Laissez vide pour la voix par défaut de la langue.</small>
        </div>
        <div class="form-group mb-3">
          <label for="speed">Vitesse</label>
          <select id="speed" name="speed" class="form-control">
            <option value="-1">Par défaut (léger)</option>
            <option value="-5">Très lent (-5)</option>
            <option value="-2">Lent (-2)</option>
            <option value="0">Normal (0)</option>
            <option value="2">Rapide (+2)</option>
            <option value="5">Très rapide (+5)</option>
            <option value="10">Max (+10)</option>
          </select>
          <small class="help">Plus la valeur est élevée, plus la voix parle vite (plage -10 à +10 VoiceRSS).</small>
        </div>
        <button type="submit" class="btn btn-primary">Générer MP3</button>
      </form>
      <p class="help mt-2">Le fichier sera enregistré côté serveur et listé ci-dessous.</p>
    </div>
  </div>

  <h3>Derniers fichiers générés</h3>
  <div class="table-responsive">
    <table class="table table-striped">
      <thead>
        <tr>
          <th>ID</th>
          <th>Texte</th>
          <th>Langue</th>
          <th>Fichier</th>
          <th>Date</th>
        </tr>
      </thead>
      <tbody>
      <?php if (count($rows) === 0): ?>
        <tr><td colspan="5" class="text-muted">Aucun fichier pour l'instant.</td></tr>
      <?php else: foreach($rows as $it): ?>
        <tr>
          <td><?php echo intval($it['id']); ?></td>
          <td><?php echo htmlspecialchars($it['text']); ?></td>
          <td><?php echo htmlspecialchars($it['lang']); ?></td>
          <td>
            <a class="btn btn-sm btn-success" href="audio/<?php echo rawurlencode($it['filename']); ?>" target="_blank">Télécharger</a>
            <audio controls style="vertical-align: middle;">
              <source src="audio/<?php echo htmlspecialchars($it['filename']); ?>" type="audio/mpeg">
            </audio>
          </td>
          <td><?php echo htmlspecialchars($it['created_at']); ?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<script>
function validateForm() {
  const text = document.getElementById('text').value.trim();
  if (!text) { alert('Veuillez saisir une phrase.'); return false; }
  if (text.length > 500) { alert('Texte trop long (max 500).'); return false; }
  return true;
}
</script>
</body>
</html>
