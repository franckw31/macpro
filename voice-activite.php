<?php
/**
 * voice-activite.php
 * Reconnaissance vocale → mise à jour de la table `activite`
 * Exemple : modifier le buyin, le titre, les places, etc.
 */

$pdo = new PDO(
    'mysql:host=localhost;dbname=dbs9616600;charset=utf8mb4',
    'root',
    'Kookies7*',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

$message  = '';
$msgType  = '';
$activite = null;

// ─── Colonnes modifiables via la voix ───────────────────────────────────────
$colonnesAutorisees = [
    'buyin'          => ['label' => 'Buy-in',      'type' => 'number'],
    'bounty'         => ['label' => 'Bounty',       'type' => 'number'],
    'rake'           => ['label' => 'Rake',         'type' => 'number'],
    'places'         => ['label' => 'Places',       'type' => 'number'],
    'jetons'         => ['label' => 'Jetons',       'type' => 'number'],
    'recave_montant' => ['label' => 'Recave €',     'type' => 'number'],
    'recave_jetons'  => ['label' => 'Recave jetons','type' => 'number'],
    'titre-activite' => ['label' => 'Titre',        'type' => 'text'],
    'ville'          => ['label' => 'Ville',        'type' => 'text'],
];

// ─── Mots-clés vocaux → colonnes ────────────────────────────────────────────
// Permet de dire "buyin 50", "places 20", "titre Poker Night", etc.
$motsCles = [
    'buyin'          => 'buyin',
    'buy-in'         => 'buyin',
    'buy in'         => 'buyin',
    'bounty'         => 'bounty',
    'rake'           => 'rake',
    'places'         => 'places',
    'place'          => 'places',
    'jetons'         => 'jetons',
    'jeton'          => 'jetons',
    'recave montant' => 'recave_montant',
    'recave jetons'  => 'recave_jetons',
    'titre'          => 'titre-activite',
    'title'          => 'titre-activite',
    'ville'          => 'ville',
    'city'           => 'ville',
];

// ─── Traitement POST (AJAX ou formulaire) ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $action = $_POST['action'] ?? '';

    // --- Interpréter la commande vocale ---
    if ($action === 'parse_voice') {
        $texte = strtolower(trim($_POST['texte'] ?? ''));

        $colonne = null;
        $valeur  = null;

        foreach ($motsCles as $mot => $col) {
            if (strpos($texte, $mot) !== false) {
                $colonne = $col;
                // Extraire la valeur après le mot-clé
                $partie = trim(substr($texte, strpos($texte, $mot) + strlen($mot)));
                // Prendre le premier mot/nombre
                preg_match('/^[\w\-\.]+/u', $partie, $m);
                $valeur = $m[0] ?? null;
                break;
            }
        }

        echo json_encode([
            'colonne' => $colonne,
            'valeur'  => $valeur,
            'texte'   => $texte,
        ]);
        exit;
    }

    // --- Mettre à jour la BDD ---
    if ($action === 'update') {
        $idActivite = (int)($_POST['id_activite'] ?? 0);
        $colonne    = $_POST['colonne']  ?? '';
        $valeur     = $_POST['valeur']   ?? '';

        if (!$idActivite) {
            echo json_encode(['success' => false, 'error' => 'ID activité manquant']);
            exit;
        }
        if (!array_key_exists($colonne, $colonnesAutorisees)) {
            echo json_encode(['success' => false, 'error' => 'Colonne non autorisée : ' . htmlspecialchars($colonne)]);
            exit;
        }

        $type = $colonnesAutorisees[$colonne]['type'];
        if ($type === 'number') {
            if (!is_numeric($valeur)) {
                echo json_encode(['success' => false, 'error' => 'La valeur doit être un nombre']);
                exit;
            }
            $valeur = (float)$valeur;
        } else {
            $valeur = trim($valeur);
            if ($valeur === '') {
                echo json_encode(['success' => false, 'error' => 'Valeur vide']);
                exit;
            }
        }

        $stmt = $pdo->prepare("UPDATE activite SET `$colonne` = :val WHERE `id-activite` = :id");
        $stmt->execute([':val' => $valeur, ':id' => $idActivite]);

        echo json_encode([
            'success'  => true,
            'message'  => "✅ " . $colonnesAutorisees[$colonne]['label'] . " mis à jour → $valeur",
            'colonne'  => $colonnesAutorisees[$colonne]['label'],
            'valeur'   => $valeur,
        ]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Action inconnue']);
    exit;
}

// ─── Chargement de la liste des activités ───────────────────────────────────
$activites = $pdo->query("
    SELECT `id-activite`, `titre-activite`, date_depart, buyin, places, ville
    FROM activite
    ORDER BY date_depart DESC
    LIMIT 20
")->fetchAll();

$idSelectionne = (int)($_GET['id'] ?? ($activites[0]['id-activite'] ?? 0));

if ($idSelectionne) {
    $stmt = $pdo->prepare("SELECT * FROM activite WHERE `id-activite` = ?");
    $stmt->execute([$idSelectionne]);
    $activite = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>🎙️ Contrôle vocal – Activité</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: 'Segoe UI', Arial, sans-serif;
    background: #0f1117;
    color: #e0e0e0;
    min-height: 100vh;
    padding: 20px;
  }
  h1 { text-align: center; font-size: 1.8rem; margin-bottom: 6px; color: #fff; }
  .subtitle { text-align: center; color: #888; font-size: .9rem; margin-bottom: 30px; }

  .container { max-width: 860px; margin: 0 auto; display: grid; gap: 20px; }

  /* Sélecteur activité */
  .card {
    background: #1a1d27;
    border-radius: 14px;
    padding: 20px 24px;
    border: 1px solid #2a2d3a;
  }
  .card h2 { font-size: 1rem; color: #aaa; margin-bottom: 12px; text-transform: uppercase; letter-spacing: .05em; }

  select, input[type=text], input[type=number] {
    width: 100%; padding: 10px 14px; border-radius: 8px;
    border: 1px solid #333; background: #0f1117; color: #fff;
    font-size: .95rem; margin-top: 6px;
  }
  select:focus, input:focus { outline: none; border-color: #6c63ff; }

  /* Fiche activité */
  .activite-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: 12px;
    margin-top: 10px;
  }
  .field {
    background: #0f1117;
    border-radius: 10px;
    padding: 12px 14px;
    border: 1px solid #2a2d3a;
    transition: border-color .2s;
  }
  .field.highlighted { border-color: #6c63ff; background: #1a1730; }
  .field label { display: block; font-size: .75rem; color: #888; margin-bottom: 4px; }
  .field .value { font-size: 1.1rem; font-weight: 600; color: #fff; }
  .field .col-name { font-size: .7rem; color: #555; margin-top: 2px; }

  /* Micro */
  .mic-section { text-align: center; }
  #micBtn {
    width: 100px; height: 100px; border-radius: 50%;
    background: linear-gradient(135deg, #6c63ff, #a855f7);
    border: none; cursor: pointer; font-size: 2.5rem;
    display: inline-flex; align-items: center; justify-content: center;
    transition: transform .1s, box-shadow .2s;
    box-shadow: 0 0 0 0 rgba(108,99,255,.5);
  }
  #micBtn:hover { transform: scale(1.05); }
  #micBtn.listening {
    animation: pulse 1.2s infinite;
    background: linear-gradient(135deg, #ef4444, #f97316);
  }
  @keyframes pulse {
    0%   { box-shadow: 0 0 0 0 rgba(239,68,68,.6); }
    70%  { box-shadow: 0 0 0 18px rgba(239,68,68,0); }
    100% { box-shadow: 0 0 0 0 rgba(239,68,68,0); }
  }
  #micBtn:disabled { opacity: .4; cursor: not-allowed; }
  #micStatus { margin-top: 12px; font-size: .9rem; color: #888; }

  /* Transcript */
  #transcript {
    background: #0f1117; border: 1px dashed #333;
    border-radius: 10px; padding: 14px 18px;
    min-height: 52px; font-size: 1.05rem; color: #ccc;
    font-style: italic; margin-top: 12px;
  }

  /* Commande parsée */
  #parsed {
    display: none;
    background: #1a2a1a; border: 1px solid #2d5a2d;
    border-radius: 10px; padding: 14px 18px; margin-top: 10px;
  }
  #parsed .parsed-col { color: #4ade80; font-weight: 700; }
  #parsed .parsed-val { color: #facc15; font-weight: 700; }

  /* Toast */
  #toast {
    position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%);
    background: #1a1d27; border: 1px solid #333; border-radius: 10px;
    padding: 14px 24px; font-size: .95rem;
    display: none; z-index: 999; min-width: 260px; text-align: center;
  }
  #toast.success { border-color: #4ade80; color: #4ade80; }
  #toast.error   { border-color: #f87171; color: #f87171; }

  /* Aide commandes */
  .commands-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 8px;
    margin-top: 10px;
  }
  .cmd {
    background: #0f1117; border-radius: 8px; padding: 10px 14px;
    border: 1px solid #2a2d3a; font-size: .85rem;
  }
  .cmd .phrase { color: #a78bfa; font-weight: 600; }
  .cmd .desc   { color: #888; font-size: .78rem; margin-top: 2px; }

  /* Boutons */
  .btn {
    padding: 10px 22px; border-radius: 8px; border: none;
    cursor: pointer; font-size: .95rem; font-weight: 600;
    transition: opacity .15s;
  }
  .btn-primary  { background: #6c63ff; color: #fff; }
  .btn-success  { background: #16a34a; color: #fff; }
  .btn-secondary{ background: #2a2d3a; color: #ccc; }
  .btn:hover    { opacity: .85; }
  .btn-row      { display: flex; gap: 10px; margin-top: 14px; flex-wrap: wrap; }

  /* Formulaire manuel */
  .manual-form { display: grid; grid-template-columns: 1fr 1fr auto; gap: 10px; align-items: end; margin-top: 10px; }
  .manual-form label { font-size: .8rem; color: #888; display: block; }

  @media(max-width: 600px) {
    .manual-form { grid-template-columns: 1fr; }
    .activite-grid { grid-template-columns: 1fr 1fr; }
  }
</style>
</head>
<body>

<h1>🎙️ Contrôle vocal – Activité</h1>
<p class="subtitle">Dites une commande pour modifier un champ de la table <code>activite</code></p>

<div class="container">

  <!-- Sélection activité -->
  <div class="card">
    <h2>🏆 Sélectionner une activité</h2>
    <select id="selectActivite" onchange="changerActivite(this.value)">
      <?php foreach ($activites as $a): ?>
        <option value="<?= $a['id-activite'] ?>"
          <?= $a['id-activite'] == $idSelectionne ? 'selected' : '' ?>>
          #<?= $a['id-activite'] ?> –
          <?= htmlspecialchars($a['titre-activite']) ?>
          (<?= date('d/m/Y', strtotime($a['date_depart'])) ?>)
          – <?= $a['ville'] ?>
          – Buy-in: <?= $a['buyin'] ?>€
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <!-- Fiche activité -->
  <?php if ($activite): ?>
  <div class="card" id="ficheCard">
    <h2>📋 Fiche – <?= htmlspecialchars($activite['titre-activite']) ?></h2>
    <div class="activite-grid" id="activiteGrid">
      <?php foreach ($colonnesAutorisees as $col => $info): ?>
      <div class="field" id="field-<?= str_replace(['-',' '], '_', $col) ?>">
        <label><?= $info['label'] ?></label>
        <div class="value" id="val-<?= str_replace(['-',' '], '_', $col) ?>">
          <?= htmlspecialchars($activite[$col] ?? '–') ?>
        </div>
        <div class="col-name"><?= $col ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Micro -->
  <div class="card mic-section">
    <h2>🎤 Reconnaissance vocale</h2>
    <button id="micBtn" onclick="toggleMic()" title="Cliquez pour parler">🎙️</button>
    <div id="micStatus">Cliquez sur le micro pour commencer</div>
    <div id="transcript">En attente de votre commande…</div>
    <div id="parsed">
      Champ détecté : <span class="parsed-col" id="parsedCol">–</span>
      &nbsp;→&nbsp; Nouvelle valeur : <span class="parsed-val" id="parsedVal">–</span>
      <div class="btn-row">
        <button class="btn btn-success" onclick="confirmerMiseAJour()">✅ Confirmer</button>
        <button class="btn btn-secondary" onclick="annulerParsed()">❌ Annuler</button>
      </div>
    </div>
  </div>

  <!-- Formulaire manuel (secours) -->
  <div class="card">
    <h2>⌨️ Modification manuelle (secours)</h2>
    <p style="font-size:.85rem;color:#666;margin-bottom:8px;">
      Si la reconnaissance vocale n'est pas disponible, utilisez ce formulaire.
    </p>
    <div class="manual-form">
      <div>
        <label>Champ à modifier</label>
        <select id="manualCol">
          <?php foreach ($colonnesAutorisees as $col => $info): ?>
          <option value="<?= $col ?>"><?= $info['label'] ?> (<?= $col ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Nouvelle valeur</label>
        <input type="text" id="manualVal" placeholder="ex: 50">
      </div>
      <div>
        <button class="btn btn-primary" onclick="miseAJourManuelle()">Mettre à jour</button>
      </div>
    </div>
  </div>

  <!-- Aide -->
  <div class="card">
    <h2>💬 Exemples de commandes vocales</h2>
    <div class="commands-grid">
      <div class="cmd"><div class="phrase">"buyin 50"</div><div class="desc">Définir le buy-in à 50 €</div></div>
      <div class="cmd"><div class="phrase">"places 20"</div><div class="desc">Passer les places à 20</div></div>
      <div class="cmd"><div class="phrase">"jetons 5000"</div><div class="desc">Mettre les jetons à 5000</div></div>
      <div class="cmd"><div class="phrase">"bounty 10"</div><div class="desc">Ajouter un bounty de 10 €</div></div>
      <div class="cmd"><div class="phrase">"rake 5"</div><div class="desc">Mettre le rake à 5 %</div></div>
      <div class="cmd"><div class="phrase">"recave montant 20"</div><div class="desc">Recave à 20 €</div></div>
      <div class="cmd"><div class="phrase">"recave jetons 2000"</div><div class="desc">Recave à 2000 jetons</div></div>
      <div class="cmd"><div class="phrase">"titre Poker Night"</div><div class="desc">Renommer l'activité</div></div>
      <div class="cmd"><div class="phrase">"ville Paris"</div><div class="desc">Changer la ville</div></div>
    </div>
  </div>

</div><!-- /container -->

<div id="toast"></div>

<script>
// ─── État global ─────────────────────────────────────────────────────────────
let recognition   = null;
let isListening   = false;
let currentId     = <?= $idSelectionne ?>;
let pendingUpdate = null;   // { colonne, valeur }

// ─── Changer d'activité ──────────────────────────────────────────────────────
function changerActivite(id) {
  window.location.href = '?id=' + id;
}

// ─── Web Speech API ──────────────────────────────────────────────────────────
function initRecognition() {
  const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
  if (!SpeechRecognition) {
    document.getElementById('micStatus').textContent = '⚠️ Reconnaissance vocale non supportée (Chrome conseillé)';
    document.getElementById('micBtn').disabled = true;
    return false;
  }

  recognition = new SpeechRecognition();
  recognition.lang        = 'fr-FR';
  recognition.continuous  = false;
  recognition.interimResults = true;

  recognition.onstart = () => {
    isListening = true;
    document.getElementById('micBtn').classList.add('listening');
    document.getElementById('micStatus').textContent = '🔴 Écoute en cours… Parlez maintenant';
    document.getElementById('transcript').textContent = '…';
  };

  recognition.onresult = (e) => {
    let interim = '', final = '';
    for (let i = e.resultIndex; i < e.results.length; i++) {
      const t = e.results[i][0].transcript;
      if (e.results[i].isFinal) final += t;
      else interim += t;
    }
    document.getElementById('transcript').textContent = final || interim;
    if (final) parseVoiceCommand(final);
  };

  recognition.onerror = (e) => {
    console.error('Speech error', e.error);
    setMicIdle();
    const msgs = {
      'not-allowed' : '🚫 Microphone refusé – Autorisez l\'accès',
      'no-speech'   : '🔇 Aucun son détecté',
      'network'     : '🌐 Erreur réseau',
    };
    document.getElementById('micStatus').textContent = msgs[e.error] || ('Erreur : ' + e.error);
  };

  recognition.onend = () => { setMicIdle(); };
  return true;
}

function toggleMic() {
  if (!recognition && !initRecognition()) return;
  if (isListening) {
    recognition.stop();
  } else {
    annulerParsed();
    document.getElementById('transcript').textContent = '…';
    recognition.start();
  }
}

function setMicIdle() {
  isListening = false;
  document.getElementById('micBtn').classList.remove('listening');
  document.getElementById('micStatus').textContent = 'Cliquez sur le micro pour recommencer';
}

// ─── Envoi au serveur pour parsing ───────────────────────────────────────────
function parseVoiceCommand(texte) {
  const fd = new FormData();
  fd.append('action', 'parse_voice');
  fd.append('texte', texte);

  fetch('voice-activite.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      if (data.colonne && data.valeur) {
        pendingUpdate = { colonne: data.colonne, valeur: data.valeur };
        document.getElementById('parsedCol').textContent = data.colonne;
        document.getElementById('parsedVal').textContent = data.valeur;
        document.getElementById('parsed').style.display = 'block';
        // Surligner le champ
        highlightField(data.colonne);
      } else {
        showToast('⚠️ Commande non reconnue : "' + texte + '"', 'error');
      }
    })
    .catch(() => showToast('Erreur de communication', 'error'));
}

// ─── Confirmer la mise à jour ─────────────────────────────────────────────────
function confirmerMiseAJour() {
  if (!pendingUpdate) return;
  envoyerMiseAJour(pendingUpdate.colonne, pendingUpdate.valeur);
  annulerParsed();
}

function annulerParsed() {
  pendingUpdate = null;
  document.getElementById('parsed').style.display = 'none';
  document.querySelectorAll('.field').forEach(f => f.classList.remove('highlighted'));
}

// ─── Formulaire manuel ────────────────────────────────────────────────────────
function miseAJourManuelle() {
  const col = document.getElementById('manualCol').value;
  const val = document.getElementById('manualVal').value.trim();
  if (!val) { showToast('Entrez une valeur', 'error'); return; }
  envoyerMiseAJour(col, val);
}

// ─── Appel AJAX → UPDATE MySQL ────────────────────────────────────────────────
function envoyerMiseAJour(colonne, valeur) {
  const fd = new FormData();
  fd.append('action',      'update');
  fd.append('id_activite', currentId);
  fd.append('colonne',     colonne);
  fd.append('valeur',      valeur);

  fetch('voice-activite.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        showToast(data.message, 'success');
        // Mettre à jour l'affichage sans recharger
        const safeCol = colonne.replace(/[-\s]/g, '_');
        const el = document.getElementById('val-' + safeCol);
        if (el) {
          el.textContent = valeur;
          el.closest('.field').classList.add('highlighted');
          setTimeout(() => el.closest('.field').classList.remove('highlighted'), 2500);
        }
      } else {
        showToast('❌ ' + (data.error || 'Erreur inconnue'), 'error');
      }
    })
    .catch(() => showToast('Erreur réseau', 'error'));
}

// ─── Surligner un champ ───────────────────────────────────────────────────────
function highlightField(colonne) {
  document.querySelectorAll('.field').forEach(f => f.classList.remove('highlighted'));
  const safeCol = colonne.replace(/[-\s]/g, '_');
  const el = document.getElementById('field-' + safeCol);
  if (el) el.classList.add('highlighted');
}

// ─── Toast ────────────────────────────────────────────────────────────────────
function showToast(msg, type = 'success') {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.className = type;
  t.style.display = 'block';
  setTimeout(() => { t.style.display = 'none'; }, 3500);
}

// ─── Init ─────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  initRecognition();
});
</script>
</body>
</html>
