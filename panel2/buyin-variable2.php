<?php
session_start();

// Connexion à la base de données
$host = 'localhost';
$dbname = 'dbs9616600';
$username = 'root';
$password = 'Kookies7*';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// Créer la table si elle n'existe pas
$pdo->exec("
CREATE TABLE IF NOT EXISTS poker_players (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE,
    buyin DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
");

function redistribuerPrizePool($players, $ranking) {
    // Vérifier qu'il y a au moins 5 joueurs
    if (count($players) < 5) {
        throw new Exception("Au moins 5 joueurs sont requis");
    }

    // Calculer le prize pool total
    $prizePool = array_sum(array_column($players, 'buyin'));
    
    // Définir la répartition pour les 5 premiers
    $repartition = [0.37, 0.23, 0.18, 0.12, 0.10];
    
    // Trouver le buy-in maximum
    $maxBuyin = max(array_column($players, 'buyin'));
    
    // Calculer les gains pour chaque position
    $paiements = [];
    $totalDistributed = 0;
    
    for ($i = 0; $i < 5; $i++) {
        $playerName = $ranking[$i];
        $playerKey = null;
        foreach ($players as $key => $player) {
            if ($player['name'] === $playerName) {
                $playerKey = $key;
                break;
            }
        }
        
        if ($playerKey === null) {
            throw new Exception("Joueur $playerName non trouvé");
        }
        
        $buyin = $players[$playerKey]['buyin'];
        
        // Appliquer le pourcentage de répartition ajusté par le buy-in
        $paiement = $prizePool * $repartition[$i] * ($buyin / $maxBuyin);
        $totalDistributed += $paiement;
        $paiements[] = [
            'name' => $playerName,
            'rank' => $i + 1,
            'prize' => round($paiement, 2)
        ];
    }
    
    // Ajuster pour redistribuer 100% du prize pool
    if ($totalDistributed > 0) {
        $adjustmentFactor = $prizePool / $totalDistributed;
        foreach ($paiements as &$p) {
            $p['prize'] = round($p['prize'] * $adjustmentFactor, 2);
        }
    }
    
    return $paiements;
}

// Traitement du formulaire de saisie des joueurs
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['players'])) {
    $pdo->beginTransaction();
    try {
        // Préparer les requêtes
        $insertStmt = $pdo->prepare("INSERT INTO poker_players (name, buyin) VALUES (?, ?) ON DUPLICATE KEY UPDATE buyin = ?");
        $_SESSION['players'] = [];
        
        foreach ($_POST['name'] as $key => $name) {
            if (!empty($name) && isset($_POST['buyin'][$key]) && !empty($_POST['buyin'][$key])) {
                $buyin = (float)$_POST['buyin'][$key];
                $_SESSION['players'][] = [
                    'name' => $name,
                    'buyin' => $buyin
                ];
                $insertStmt->execute([$name, $buyin, $buyin]);
            }
        }
        
        $pdo->commit();
        $success = "Les joueurs ont été sauvegardés avec succès!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Erreur de sauvegarde: " . $e->getMessage();
    }
}

// Traitement du formulaire de classement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ranking'])) {
    $ranking = array_filter([
        $_POST['first'] ?? null,
        $_POST['second'] ?? null,
        $_POST['third'] ?? null,
        $_POST['fourth'] ?? null,
        $_POST['fifth'] ?? null
    ]);
    
    if (count($ranking) === 5) {
        try {
            $resultats = redistribuerPrizePool($_SESSION['players'], $ranking);
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    } else {
        $error = "Veuillez sélectionner 5 joueurs pour le classement";
    }
}

// Réinitialiser la session
if (isset($_GET['reset'])) {
    session_destroy();
    header("Location: ".$_SERVER['PHP_SELF']);
    exit;
}

// Mode modification des joueurs
if (isset($_GET['modify'])) {
    $modify_mode = true;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Redistribution Prize Pool Poker</title>
    <style>
        .container { max-width: 800px; margin: 20px auto; padding: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; }
        input, select { width: 100%; padding: 8px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .player-row { display: flex; margin-bottom: 10px; }
        .player-row input { margin-right: 10px; }
        .player-row button { margin-left: auto; }
        .actions { margin: 20px 0; display: flex; gap: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <?php if (!isset($_SESSION['players']) || isset($modify_mode)): ?>
            <!-- Formulaire de saisie/modification des joueurs -->
            <h2><?= isset($modify_mode) ? 'Modification' : 'Saisie' ?> des joueurs et des buy-ins</h2>
            <form method="POST" id="players-form">
                <div class="form-group">
                    <label>Ajouter un joueur existant:</label>
                    <select id="saved-player">
                        <option value="">Sélectionnez un joueur sauvegardé</option>
                <?php 
                $savedPlayers = $pdo->query("SELECT name, buyin FROM poker_players ORDER BY name");
                if ($savedPlayers && $savedPlayers->rowCount() > 0) {
                    foreach ($savedPlayers as $player): ?>
                        <option value="<?= $player['name'] ?>" data-buyin="<?= $player['buyin'] ?>">
                            <?= $player['name'] ?> (<?= $player['buyin'] ?>€)
                        </option>
                    <?php endforeach; 
                } else { ?>
                    <option value="">Aucun joueur sauvegardé</option>
                <?php } ?>
                    </select>
                    <button type="button" id="add-saved-player">Ajouter ce joueur</button>
                    <?php if ($savedPlayers && $savedPlayers->rowCount() === 0): ?>
                        <p style="color: #666; margin-top: 5px;">
                            Astuce : Enregistrez d'abord des joueurs pour les retrouver ici
                        </p>
                    <?php endif; ?>
                </div>
                
                <div id="players-container">
                    <?php if (!empty($_SESSION['players'])): ?>
                        <?php foreach ($_SESSION['players'] as $index => $player): ?>
                            <div class="form-group player-row">
                                <input type="text" name="name[]" placeholder="Nom du joueur" value="<?= $player['name'] ?>" required>
                                <input type="number" name="buyin[]" placeholder="Montant du buy-in (€)" step="0.01" min="1" value="<?= $player['buyin'] ?>" required>
                                <button type="button" class="remove-player">Supprimer</button>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="form-group player-row">
                            <input type="text" name="name[]" placeholder="Nom du joueur" required>
                            <input type="number" name="buyin[]" placeholder="Montant du buy-in (€)" step="0.01" min="1" required>
                            <button type="button" class="remove-player" style="display:none;">Supprimer</button>
                        </div>
                    <?php endif; ?>
                </div>
                <button type="button" id="add-player">Ajouter un nouveau joueur</button>
                <div class="actions">
                <?php if (isset($success)): ?>
                    <div style="color: green; margin: 15px 0;"><?= $success ?></div>
                <?php endif; ?>
                <?php if (isset($error)): ?>
                    <div style="color: red; margin: 15px 0;"><?= $error ?></div>
                <?php endif; ?>
                
                <button type="submit" name="players"><?= isset($modify_mode) ? 'Enregistrer modifications' : 'Enregistrer les joueurs' ?></button>
                    <?php if (isset($modify_mode)): ?>
                        <button type="button" id="cancel-modify">Annuler</button>
                    <?php endif; ?>
                </div>
            </form>
            
            <script>
                // Ajouter un nouveau joueur
                document.getElementById('add-player').addEventListener('click', function() {
                    const container = document.getElementById('players-container');
                    const newRow = document.createElement('div');
                    newRow.className = 'form-group player-row';
                    newRow.innerHTML = `
                        <input type="text" name="name[]" placeholder="Nom du joueur" required>
                        <input type="number" name="buyin[]" placeholder="Montant du buy-in (€)" step="0.01" min="1" required>
                        <button type="button" class="remove-player">Supprimer</button>
                    `;
                    container.appendChild(newRow);
                    
                    // Ajouter l'événement de suppression
                    newRow.querySelector('.remove-player').addEventListener('click', function() {
                        container.removeChild(newRow);
                    });
                });

                // Ajouter un joueur sauvegardé
                document.getElementById('add-saved-player').addEventListener('click', function() {
                    const select = document.getElementById('saved-player');
                    const selectedOption = select.options[select.selectedIndex];
                    
                    if (!selectedOption.value) return;
                    
                    const name = selectedOption.value;
                    const buyin = selectedOption.getAttribute('data-buyin');
                    
                    const container = document.getElementById('players-container');
                    const newRow = document.createElement('div');
                    newRow.className = 'form-group player-row';
                    newRow.innerHTML = `
                        <input type="text" name="name[]" placeholder="Nom du joueur" value="${name}" required>
                        <input type="number" name="buyin[]" placeholder="Montant du buy-in (€)" step="0.01" min="1" value="${buyin}" required>
                        <button type="button" class="remove-player">Supprimer</button>
                    `;
                    container.appendChild(newRow);
                    
                    // Ajouter l'événement de suppression
                    newRow.querySelector('.remove-player').addEventListener('click', function() {
                        container.removeChild(newRow);
                    });

                    // Réinitialiser la sélection
                    select.selectedIndex = 0;
                });

                // Gestion des boutons de suppression
                document.addEventListener('click', function(e) {
                    if (e.target && e.target.classList.contains('remove-player')) {
                        e.target.parentElement.remove();
                    }
                });
                
                <?php if (isset($modify_mode)): ?>
                    document.getElementById('cancel-modify').addEventListener('click', function() {
                        window.location.href = window.location.href.split('?')[0];
                    });
                <?php endif; ?>
            </script>
            
        <?php elseif (!isset($resultats)): ?>
            <!-- Formulaire de saisie du classement -->
            <h2>Saisie du classement final</h2>
            
            <h3>Joueurs inscrits</h3>
            <table>
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Buy-in</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($_SESSION['players'] as $player): ?>
                        <tr>
                            <td><?= $player['name'] ?></td>
                            <td><?= $player['buyin'] ?>€</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div class="actions">
                <button type="button" id="modify-players">Modifier la liste</button>
            </div>
            
            <form method="POST">
                <div class="form-group">
                    <label>1er:</label>
                    <select name="first" required>
                        <option value="">Sélectionnez un joueur</option>
                        <?php foreach ($_SESSION['players'] as $player): ?>
                            <option value="<?= $player['name'] ?>"><?= $player['name'] ?> (<?= $player['buyin'] ?>€)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>2ème:</label>
                    <select name="second" required>
                        <option value="">Sélectionnez un joueur</option>
                        <?php foreach ($_SESSION['players'] as $player): ?>
                            <option value="<?= $player['name'] ?>"><?= $player['name'] ?> (<?= $player['buyin'] ?>€)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>3ème:</label>
                    <select name="third" required>
                        <option value="">Sélectionnez un joueur</option>
                        <?php foreach ($_SESSION['players'] as $player): ?>
                            <option value="<?= $player['name'] ?>"><?= $player['name'] ?> (<?= $player['buyin'] ?>€)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>4ème:</label>
                    <select name="fourth" required>
                        <option value="">Sélectionnez un joueur</option>
                        <?php foreach ($_SESSION['players'] as $player): ?>
                            <option value="<?= $player['name'] ?>"><?= $player['name'] ?> (<?= $player['buyin'] ?>€)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>5ème:</label>
                    <select name="fifth" required>
                        <option value="">Sélectionnez un joueur</option>
                        <?php foreach ($_SESSION['players'] as $player): ?>
                            <option value="<?= $player['name'] ?>"><?= $player['name'] ?> (<?= $player['buyin'] ?>€)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <?php if (isset($error)): ?>
                    <div style="color: red; margin: 15px 0;"><?= $error ?></div>
                <?php endif; ?>
                
                <button type="submit" name="ranking">Calculer la redistribution</button>
            </form>
            
            <script>
                document.getElementById('modify-players').addEventListener('click', function() {
                    window.location.href = '?modify=1';
                });
            </script>
            
        <?php else: ?>
            <!-- Affichage des résultats -->
            <h2>Résultats de la redistribution</h2>
            <p>Prize pool total: <b><?= array_sum(array_column($_SESSION['players'], 'buyin')) ?>€</b></p>
            
            <table>
                <thead>
                    <tr>
                        <th>Position</th>
                        <th>Joueur</th>
                        <th>Buy-in</th>
                        <th>Gains</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($resultats as $result): 
                        // Trouver le buy-in du joueur
                        $playerBuyin = '';
                        foreach ($_SESSION['players'] as $player) {
                            if ($player['name'] === $result['name']) {
                                $playerBuyin = $player['buyin'] . '€';
                                break;
                            }
                        }
                    ?>
                        <tr>
                            <td><?= $result['rank'] ?>er</td>
                            <td><?= $result['name'] ?></td>
                            <td><?= $playerBuyin ?></td>
                            <td><b><?= $result['prize'] ?>€</b></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div style="margin-top: 20px;">
                <a href="?reset=1">Recommencer</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
