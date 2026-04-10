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

function getRepartition($playerCount) {
    switch ($playerCount) {
        case 1:
            return [1.00]; // 100% pour un joueur seul
        case 2:
            return [0.60, 0.40];
        case 3:
            return [0.50, 0.30, 0.20];
        case 4:
            return [0.40, 0.30, 0.20, 0.10];
        default:
            return [0.37, 0.23, 0.18, 0.12, 0.10];
    }
}

function redistribuerPrizePool($players, $ranking) {
    // Initialisation
    $rankedPlayers = [];
    $uniqueBuyins = [];
    $paiements = array_fill_keys(array_column($players, 'name'), 0);
    $montantsDejaDistribues = array_fill_keys(array_column($players, 'name'), 0);
    $totalBuyins = array_sum(array_column($players, 'buyin')); // Total des buy-ins
    $totalDistribue = 0; // Suivi du montant total distribué
    
    // Classer les joueurs et identifier les buy-ins uniques
    foreach ($players as $player) {
        $rank = array_search($player['name'], $ranking);
        $rankedPlayers[] = [
            'name' => $player['name'],
            'buyin' => floatval($player['buyin']),
            'rank' => $rank !== false ? $rank + 1 : count($ranking) + 1
        ];
        if (!in_array($player['buyin'], $uniqueBuyins)) {
            $uniqueBuyins[] = floatval($player['buyin']);
        }
    }
    
    // Trier les buy-ins par ordre croissant
    sort($uniqueBuyins);
    
    // Pour chaque niveau de buy-in
    foreach ($uniqueBuyins as $currentBuyin) {
        // Identifier les joueurs qualifiés pour ce niveau
        $qualifiedPlayers = array_filter($rankedPlayers, function($player) use ($currentBuyin) {
            return $player['buyin'] >= $currentBuyin;
        });
        
        if (empty($qualifiedPlayers)) continue;
        
        // Calculer le prize pool disponible pour ce niveau
        $levelPrizePool = 0;
        foreach ($qualifiedPlayers as $player) {
            $montantPrecedent = $montantsDejaDistribues[$player['name']];
            $montantDisponible = $currentBuyin - $montantPrecedent;
            if ($montantDisponible > 0) {
                $levelPrizePool += $montantDisponible;
            }
        }
        
        // Vérifier que le prize pool du niveau ne dépasse pas le reste disponible
        $resteDisponible = $totalBuyins - $totalDistribue;
        if ($levelPrizePool > $resteDisponible) {
            $levelPrizePool = $resteDisponible;
        }
        
        if ($levelPrizePool <= 0) continue;
        
        // Compter les joueurs uniques à ce niveau exact de buy-in
        $exactBuyinPlayers = array_filter($rankedPlayers, function($player) use ($currentBuyin) {
            return $player['buyin'] === $currentBuyin;
        });
        
        // Si un seul joueur a misé exactement ce montant
        if (count($exactBuyinPlayers) === 1) {
            $player = reset($exactBuyinPlayers);
            $gain = min($currentBuyin, $resteDisponible);
            $paiements[$player['name']] += $gain;
            $montantsDejaDistribues[$player['name']] = $currentBuyin;
            $totalDistribue += $gain;
            continue;
        }
        
        // Distribution normale
        usort($qualifiedPlayers, function($a, $b) {
            return $a['rank'] <=> $b['rank'];
        });
        
        $playersCount = min(count($qualifiedPlayers), 5);
        $repartition = getRepartition($playersCount);
        $topPlayers = array_slice($qualifiedPlayers, 0, $playersCount);
        
        foreach ($topPlayers as $index => $player) {
            $gain = round($levelPrizePool * $repartition[$index], 2);
            
            // Vérifier que le gain ne dépasse pas le reste disponible
            if ($totalDistribue + $gain > $totalBuyins) {
                $gain = $totalBuyins - $totalDistribue;
            }
            
            if ($gain > 0) {
                $paiements[$player['name']] += $gain;
                $montantsDejaDistribues[$player['name']] = $currentBuyin;
                $totalDistribue += $gain;
            }
            
            // Arrêter si tout est distribué
            if ($totalDistribue >= $totalBuyins) {
                break 2;
            }
        }
    }
    
    // Préparer le résultat final
    $resultats = [];
    foreach ($rankedPlayers as $player) {
        $resultats[] = [
            'name' => $player['name'],
            'rank' => $player['rank'],
            'prize' => $paiements[$player['name']]
        ];
    }
    
    // Trier par classement
    usort($resultats, function($a, $b) {
        return $a['rank'] <=> $b['rank'];
    });
    
    return $resultats;
}

// Traitement du formulaire de saisie des joueurs
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['players'])) {
    $pdo->beginTransaction();
    try {
        // Vérifier d'abord les joueurs existants
        $checkStmt = $pdo->prepare("SELECT name FROM poker_players WHERE name = ?");
        $insertStmt = $pdo->prepare("INSERT INTO poker_players (name, buyin) VALUES (?, ?)");
        $_SESSION['players'] = [];
        
        foreach ($_POST['name'] as $key => $name) {
            if (!empty($name) && isset($_POST['buyin'][$key]) && !empty($_POST['buyin'][$key])) {
                $buyin = (float)$_POST['buyin'][$key];
                
                // Vérifier si le joueur existe déjà
                $checkStmt->execute([$name]);
                $exists = $checkStmt->fetch();
                
                if (!$exists) {
                    // Seulement insérer si le joueur n'existe pas
                    $insertStmt->execute([$name, $buyin]);
                }
                
                $_SESSION['players'][] = [
                    'name' => $name,
                    'buyin' => $buyin
                ];
            }
        }
        
        $pdo->commit();
        $success = "Les nouveaux joueurs ont été sauvegardés avec succès!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Erreur de sauvegarde: " . $e->getMessage();
    }
}

// Traitement du formulaire de classement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ranking'])) {
    $ranks = $_POST['rank'];
    
    // Trier les joueurs par position
    $sortedPlayers = [];
    foreach ($ranks as $playerName => $position) {
        $sortedPlayers[(int)$position] = $playerName;
    }
    
    // Créer le classement des 5 premiers
    $ranking = [];
    for ($i = 1; $i <= 5; $i++) {
        if (isset($sortedPlayers[$i])) {
            $ranking[] = $sortedPlayers[$i];
        }
    }
    
    if (count($ranking) === 5) {
        try {
            $resultats = redistribuerPrizePool($_SESSION['players'], $ranking);
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    } else {
        $error = "Veuillez attribuer une position valide pour les 5 premiers joueurs";
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
                                <input type="text" name="name[]" placeholder="Nom du joueur" value="<?= $player['name'] ?>" >
                                <input type="number" name="buyin[]" placeholder="Montant du buy-in (€)" step="0.01" min="1" value="<?= $player['buyin'] ?>" >
                                <button type="button" class="remove-player">Supprimer</button>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="form-group player-row">
                            <input type="text" name="name[]" placeholder="Nom du joueur" >
                            <input type="number" name="buyin[]" placeholder="Montant du buy-in (€)" step="0.01" min="1" >
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
                <h3>Classement complet (1 = premier, 2 = deuxième, etc.)</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Joueur</th>
                            <th>Buy-in</th>
                            <th>Position</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($_SESSION['players'] as $player): ?>
                            <tr>
                                <td><?= $player['name'] ?></td>
                                <td><?= $player['buyin'] ?>€</td>
                                <td>
                                    <input type="number" name="rank[<?= $player['name'] ?>]" min="1" max="<?= count($_SESSION['players']) ?>" required>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
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
            <p>Total buy-ins (tous les joueurs): <b><?= array_sum(array_column($_SESSION['players'], 'buyin')) ?>€</b></p>
            <p>Prize pool distribué (5 premiers): <b><?= array_sum(array_column($resultats, 'prize')) ?>€</b></p>
            
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
