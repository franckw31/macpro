<?php
session_start();
// Activer l'affichage des erreurs pour débogage temporaire
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include('include/config.php');

if (strlen($_SESSION['id']) == 0) {
	header('location:logout.php');
	exit;
}

// Vérifier et créer la colonne a_paye si elle n'existe pas
$checkColumnQuery = "SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'participation' AND COLUMN_NAME = 'a_paye' AND TABLE_SCHEMA = DATABASE()";
$columnResult = mysqli_query($con, $checkColumnQuery);
if (!$columnResult || mysqli_num_rows($columnResult) === 0) {
	// La colonne n'existe pas, la créer
	mysqli_query($con, "ALTER TABLE `participation` ADD COLUMN `a_paye` INT DEFAULT 0");
}

// Vérifier et créer la colonne jetons_activite si elle n'existe pas
$checkJetonsActivityQuery = "SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'activite' AND COLUMN_NAME = 'jetons_activite' AND TABLE_SCHEMA = DATABASE()";
$jetonsActivityResult = mysqli_query($con, $checkJetonsActivityQuery);
if (!$jetonsActivityResult || mysqli_num_rows($jetonsActivityResult) === 0) {
	// La colonne n'existe pas, la créer
	mysqli_query($con, "ALTER TABLE `activite` ADD COLUMN `jetons_activite` INT DEFAULT 0");
}

$selectedActivityId = isset($_GET['id_activite']) ? intval($_GET['id_activite']) : 0;
// Par défaut, on est en placement manuel ; on accepte maintenant trois modes : manual, auto, sequential
if (isset($_GET['mode']) && in_array($_GET['mode'], ['auto', 'manual', 'sequential'], true)) {
	$mode = $_GET['mode'];
} else {
	$mode = 'manual';
}
// Option d'équilibrage automatique du remplissage (0 = non par défaut)
$autoBalance = isset($_GET['equilibrage']) ? intval($_GET['equilibrage']) : 0;
$activities = [];
$players = [];
$seatingTables = [];
$flatSeating = [];
$autoChanges = [];
$suggestionFromTable = 0; // table source pour la suggestion d'équilibrage
$suggestionToTable   = 0; // table destination pour la suggestion d'équilibrage
$maxTables = null; // nombre de tables configuré dans activite
$arrivalOrderMap = []; // ordre d'arrivée séquentiel (id-participation => rang)

// Récupère la liste des activités pour le sélecteur
$sqlActivities = mysqli_query(
	$con,
	"SELECT `id-activite`, `titre-activite`, `date_depart` FROM `activite` ORDER BY `date_depart` DESC"
);
if ($sqlActivities) {
	while ($row = mysqli_fetch_assoc($sqlActivities)) {
		$activities[] = $row;
	}
}

// Récupère la config de l'activité sélectionnée (nb de tables) puis tous les joueurs
if ($selectedActivityId > 0) {
	// Config activité
	$sqlActConf = mysqli_query(
		$con,
		"SELECT `id-activite`, `titre-activite`, `nb-tables` AS nb_tables, `type` FROM `activite` WHERE `id-activite` = " . $selectedActivityId . " LIMIT 1"
	);
	if ($sqlActConf && mysqli_num_rows($sqlActConf) === 1) {
		$actConf = mysqli_fetch_assoc($sqlActConf);
		$activityType = isset($actConf['type']) ? intval($actConf['type']) : 0;
		$maxTables = isset($actConf['nb_tables']) ? (int)$actConf['nb_tables'] : null;
		if ($maxTables !== null && $maxTables <= 0) {
			$maxTables = null; // valeur non exploitable, on repassera en mode auto
		}
	}

	// En mode manuel, on part d'une situation vierge :
	// (anciennement on remettait à 0 les id-table et id-siege ici ;
	//  ce n'est plus le cas pour ne pas perdre les placements existants
	//  lorsqu'on change de mode ou d'option d'équilibrage)

	$sqlPlayers = mysqli_query(
		$con,
		"SELECT p.`id-participation`, m.`id-membre`, m.pseudo, p.`ordre`, p.`option`, p.`id-table`, p.`id-siege`, p.`a_paye`, p.`jetons`, p.`jetons_total`, p.`jetons_bonus_ins`, p.`jetons_bonus_arrivee`, m.`jetons_1`, m.`jetons_2`
		 FROM participation p
		 JOIN membres m ON p.`id-membre` = m.`id-membre`
		 WHERE p.`id-activite` = " . $selectedActivityId . "
		   AND (p.`option` IS NULL OR p.`option` <> 'Annule')
		 ORDER BY p.`ordre` ASC, p.`id-participation` ASC"
	);

	if ($sqlPlayers) {
		while ($row = mysqli_fetch_assoc($sqlPlayers)) {
			$players[] = $row;
		}
	}

	// Attribution automatique du bonus d'arrivée à l'organisateur (id-membre = 2)
	// et marquage comme payé si nécessaire
	$organizerMemberIdAuto = 2;
	if ($selectedActivityId > 0) {
		foreach ($players as $pAuto) {
			if (isset($pAuto['id-membre']) && (int)$pAuto['id-membre'] === $organizerMemberIdAuto) {
				$pidAuto = (int)$pAuto['id-participation'];
				// Ne rien faire si le participant est éliminé
				if (isset($pAuto['option']) && strcasecmp($pAuto['option'], 'Elimine') === 0) {
					break;
				}
				// Récupérer date de l'activité et valeur actuelle
				$q = mysqli_query($con, "SELECT a.`date_depart`, p.`jetons_bonus_arrivee`, p.`a_paye`, COALESCE(p.jetons,0) AS jetons, COALESCE(p.jetons_bonus_ins,0) AS bonus_ins FROM participation p JOIN activite a ON p.`id-activite` = a.`id-activite` WHERE p.`id-participation` = " . $pidAuto . " LIMIT 1");
				if ($q && $rowAuto = mysqli_fetch_assoc($q)) {
					$assignBonus = 0;
					// Si la date de départ est dans le futur, on donne le bonus arrivee (5000)
					if (!empty($rowAuto['date_depart']) && strtotime($rowAuto['date_depart']) > time()) {
						$assignBonus = 5000;
					}
					$currentBonus = intval($rowAuto['jetons_bonus_arrivee']);
					$currentPaid = intval($rowAuto['a_paye']);
					if ($assignBonus > 0 && $currentBonus === 0) {
						// Appliquer le bonus et marquer payé
						$jetonsBase = intval($rowAuto['jetons']);
						$bonusIns = intval($rowAuto['bonus_ins']);
						$newTotal = $jetonsBase + $bonusIns + $assignBonus;
						mysqli_query($con, "UPDATE participation SET jetons_bonus_arrivee = " . $assignBonus . ", jetons_total = " . $newTotal . ", a_paye = 1 WHERE `id-participation` = " . $pidAuto);
					} elseif ($currentPaid !== 1) {
						// S'assurer qu'il est marqué payé même si bonus déjà présent
						mysqli_query($con, "UPDATE participation SET a_paye = 1 WHERE `id-participation` = " . $pidAuto);
					}
				}
				break; // on ne traite qu'un seul organisateur
			}
		}
	}

	// Traiter la libération de tous les sièges IMMÉDIATEMENT après charger les joueurs
	if ($selectedActivityId > 0 && isset($_GET['release_all_seats']) && $_GET['release_all_seats'] === '1') {
		file_put_contents('/tmp/release_all_seats_log.txt', date('Y-m-d H:i:s') . ' - release_all_seats détecté' . PHP_EOL, FILE_APPEND);
		file_put_contents('/tmp/release_all_seats_log.txt', 'selectedActivityId: ' . $selectedActivityId . PHP_EOL, FILE_APPEND);
		file_put_contents('/tmp/release_all_seats_log.txt', 'totalPlayers: ' . count($players) . PHP_EOL, FILE_APPEND);
		
		// Recherche de l'id-participation de l'organisateur (id-membre = 2)
		$organizerMemberId = 2;
		$organizerParticipationId = null;
		foreach ($players as $pOrg) {
			if (isset($pOrg['id-membre']) && (int)$pOrg['id-membre'] === $organizerMemberId) {
				// On ignore l'organisateur s'il est marqué éliminé
				if (!isset($pOrg['option']) || strcasecmp($pOrg['option'], 'Elimine') !== 0) {
					$organizerParticipationId = (int)$pOrg['id-participation'];
				}
				break;
			}
		}
		file_put_contents('/tmp/release_all_seats_log.txt', 'Organisateur ID: ' . var_export($organizerParticipationId, true) . PHP_EOL, FILE_APPEND);

		// Libère les sièges de tous les joueurs sauf l'organisateur
		$liberesCount = 0;
		foreach ($players as &$p) {
			if (!isset($p['id-participation'])) continue;
			$pid = (int)$p['id-participation'];
			if ($pid <= 0) continue;

			// On ignore l'organisateur
			if ($organizerParticipationId !== null && $pid === $organizerParticipationId) {
				continue;
			}

			// On ignore les joueurs éliminés
			if (isset($p['option']) && strcasecmp($p['option'], 'Elimine') === 0) {
				continue;
			}

			// Mise à jour en base
			$result = mysqli_query(
				$con,
				"UPDATE `participation` SET `id-table` = 0, `id-siege` = 0 WHERE `id-participation` = " . $pid
			);
			
			if ($result) {
				$liberesCount++;
				file_put_contents('/tmp/release_all_seats_log.txt', 'Liberé pid: ' . $pid . PHP_EOL, FILE_APPEND);
			} else {
				file_put_contents('/tmp/release_all_seats_log.txt', 'Erreur UPDATE pid ' . $pid . ': ' . mysqli_error($con) . PHP_EOL, FILE_APPEND);
			}

			// Mise à jour dans $players
			$p['id-table'] = 0;
			$p['id-siege'] = 0;
		}
		unset($p);
		
		file_put_contents('/tmp/release_all_seats_log.txt', 'Total libérés: ' . $liberesCount . PHP_EOL, FILE_APPEND);

		// Redirection pour éviter la réaffectation des sièges par le code séquentiel
		$redirectUrl = 'tables.php?id_activite=' . $selectedActivityId . '&mode=' . htmlspecialchars($mode, ENT_QUOTES, 'UTF-8') . '&equilibrage=' . (int)$autoBalance;
		file_put_contents('/tmp/release_all_seats_log.txt', 'Redirection vers: ' . $redirectUrl . PHP_EOL, FILE_APPEND);
		header('Location: ' . $redirectUrl);
		exit;
	}

	// Répartition sur des tables de 9 joueurs maximum (exceptionnellement)
	$tableSize = 9;
	$totalPlayers = count($players);
	if ($totalPlayers > 0) {
		$hasRealSequentialOrder = false; // indique si un véritable ordre séquentiel (au-delà de l'organisateur seul) est défini
		// Initialisation des rangs globaux et valeurs par défaut
		foreach ($players as $idx => $p) {
			$players[$idx]['global_rank'] = $idx + 1;
			$players[$idx]['table_no']    = '-';
			$players[$idx]['seat_no']     = '-';
			$players[$idx]['ideal_table'] = null;
			$players[$idx]['ideal_seat']  = null;
		}

		// Index des joueurs "actifs" (non éliminés) pour le placement sur les sièges
		$activeIndexes = [];
		$maxAssignedTable = 0; // plus grand numéro de table déjà utilisé en base
		foreach ($players as $idx => $p) {
			if (isset($p['option']) && strcasecmp($p['option'], 'Elimine') === 0) {
				// Les joueurs éliminés restent visibles dans le tableau récapitulatif
				// mais ne doivent plus être placés sur les tables
				continue;
			}
			$activeIndexes[] = $idx;
			// Suivi de la table max déjà affectée pour ne jamais la faire disparaître
			if (isset($p['id-table'])) {
				$mt = (int)$p['id-table'];
				if ($mt > $maxAssignedTable) {
					$maxAssignedTable = $mt;
				}
			}
		}
		$activeCount = count($activeIndexes);

		// Gestion de l'ordre d'arrivée en mode séquentiel :
		// on mémorise dans la session, par activité, la liste des id-participation
		// dans l'ordre réel d'arrivée renseigné par l'utilisateur.
		$sequentialOrder = [];
		if ($mode === 'sequential') {
			if (!isset($_SESSION['sequential_order']) || !is_array($_SESSION['sequential_order'])) {
				$_SESSION['sequential_order'] = [];
			}
			if (!isset($_SESSION['sequential_order'][$selectedActivityId]) || !is_array($_SESSION['sequential_order'][$selectedActivityId])) {
				$_SESSION['sequential_order'][$selectedActivityId] = [];
			}

			// Reset éventuel de l'ordre d'arrivée
			if (isset($_GET['seq_reset']) && $_GET['seq_reset'] === '1') {
				$_SESSION['sequential_order'][$selectedActivityId] = [];
			}

			// Ajout d'une arrivée (id-participation)
			if (isset($_GET['arrival_pid'])) {
				$arrivalPid = (int) $_GET['arrival_pid'];
				if ($arrivalPid > 0 && !in_array($arrivalPid, $_SESSION['sequential_order'][$selectedActivityId], true)) {
					$_SESSION['sequential_order'][$selectedActivityId][] = $arrivalPid;
					// Enregistrer l'heure d'arrivée en base pour ce participant
					if (isset($con) && $con) {
						// Met à jour heure_arrivee; si l'arrivée est avant la date de l'activité,
						// attribue 5000 jetons en bonus_arrivee et recalcule jetons_total.
						$updateSql = "UPDATE participation p
							JOIN activite a ON p.`id-activite` = a.`id-activite`
							SET p.`heure_arrivee` = NOW(),
							    p.`jetons_bonus_arrivee` = CASE WHEN NOW() < a.`date_depart` THEN 5000 ELSE p.`jetons_bonus_arrivee` END,
							    p.`jetons_total` = p.`jetons` + p.`jetons_bonus_ins` + CASE WHEN NOW() < a.`date_depart` THEN 5000 ELSE p.`jetons_bonus_arrivee` END
							WHERE p.`id-participation` = " . (int)$arrivalPid;
						mysqli_query($con, $updateSql);
						// Redirection pour afficher les données à jour sans le param arrival_pid
						$redirectUrl = 'tables.php?id_activite=' . $selectedActivityId . '&mode=' . htmlspecialchars($mode, ENT_QUOTES, 'UTF-8') . '&equilibrage=' . (int)$autoBalance;
						header('Location: ' . $redirectUrl);
						exit;
					}
				}
			}

			// Annule la dernière arrivée enregistrée
			if (isset($_GET['seq_undo']) && $_GET['seq_undo'] === '1') {
				if (!empty($_SESSION['sequential_order'][$selectedActivityId])) {
					// Nombre d'entrées avant suppression (pour distinguer le cas où
					// seule l'entrée de l'organisateur est présente sur ce poste).
					$beforeCount = count($_SESSION['sequential_order'][$selectedActivityId]);
					$removedPid = array_pop($_SESSION['sequential_order'][$selectedActivityId]);
					$removedPid = (int) $removedPid;

					if ($removedPid > 0) {
						// Recherche de l'id-participation de l'organisateur (id-membre = 2)
						// pour ne jamais lui libérer automatiquement sa place via ce bouton.
						$organizerMemberIdUndo = 2;
						$organizerParticipationIdSequentialUndo = null;
						foreach ($players as $pOrgUndo) {
							if (isset($pOrgUndo['id-membre']) && (int)$pOrgUndo['id-membre'] === $organizerMemberIdUndo) {
								// On ignore l'organisateur s'il est marqué éliminé
								if (!isset($pOrgUndo['option']) || strcasecmp($pOrgUndo['option'], 'Elimine') !== 0) {
									$organizerParticipationIdSequentialUndo = (int)$pOrgUndo['id-participation'];
								}
								break;
							}
						}

						// On libère le siège uniquement si :
						// - le joueur supprimé n'est pas l'organisateur, OU
						// - il n'y a pas d'organisateur actif pour cette activité.
						// Cela évite qu'un poste "spectateur" efface par erreur
						// la place par défaut de l'organisateur.
						if ($organizerParticipationIdSequentialUndo === null || $removedPid !== $organizerParticipationIdSequentialUndo) {
							// Mise à jour immédiate en base : libère table et siège
							mysqli_query(
								$con,
								"UPDATE `participation` SET `id-table` = 0, `id-siege` = 0 WHERE `id-participation` = " . $removedPid
							);

							// Et on met aussi à jour la copie locale dans $players
							foreach ($players as &$plUndo) {
								if ((int)$plUndo['id-participation'] === $removedPid) {
									$plUndo['id-table'] = 0;
									$plUndo['id-siege'] = 0;
									break;
								}
							}
							unset($plUndo);
						}
					}
				}
			}

			$sequentialOrder = $_SESSION['sequential_order'][$selectedActivityId];

			// S'assure que l'organisateur (id-membre = 2) est toujours en première position
			// dans l'ordre d'arrivée en mode séquentiel (rang 1, donc table 1 / siège 1).
			$organizerMemberId = 2;
			$organizerParticipationIdSequential = null;
			foreach ($players as $pOrg) {
				if (isset($pOrg['id-membre']) && (int)$pOrg['id-membre'] === $organizerMemberId) {
					// On ignore l'organisateur s'il est marqué éliminé
					if (!isset($pOrg['option']) || strcasecmp($pOrg['option'], 'Elimine') !== 0) {
						$organizerParticipationIdSequential = (int)$pOrg['id-participation'];
					}
					break;
				}
			}
			if ($organizerParticipationIdSequential !== null && $organizerParticipationIdSequential > 0) {
				// Si l'organisateur n'est pas encore dans la liste, on l'ajoute en tête.
				if (!in_array($organizerParticipationIdSequential, $sequentialOrder, true)) {
					array_unshift($sequentialOrder, $organizerParticipationIdSequential);
				} else {
					// S'il est présent mais pas en première position, on le remonte en tête.
					$keyOrg = array_search($organizerParticipationIdSequential, $sequentialOrder, true);
					if ($keyOrg !== false && $keyOrg !== 0) {
						unset($sequentialOrder[$keyOrg]);
						array_unshift($sequentialOrder, $organizerParticipationIdSequential);
						$sequentialOrder = array_values($sequentialOrder);
					}
				}
				// On persiste la version éventuellement corrigée dans la session
				$_SESSION['sequential_order'][$selectedActivityId] = $sequentialOrder;
			}

			// Prépare une map id-participation => rang d'arrivée pour l'affichage du tableau
			foreach ($sequentialOrder as $pos => $pidSequential) {
				$pidSequential = (int) $pidSequential;
				if ($pidSequential > 0) {
					$arrivalOrderMap[$pidSequential] = $pos + 1;
				}
			}

			// Un "vrai" ordre séquentiel n'est considéré actif que si au moins
			// un joueur autre que l'organisateur figure dans la liste.
			$hasRealSequentialOrder = false;
			if (!empty($sequentialOrder)) {
				foreach ($sequentialOrder as $pidSequentialFlag) {
					$pidSequentialFlag = (int) $pidSequentialFlag;
					if ($pidSequentialFlag <= 0) {
						continue;
					}
					if ($organizerParticipationIdSequential !== null && $pidSequentialFlag === $organizerParticipationIdSequential && count($sequentialOrder) === 1) {
						// Cas où seule l'entrée de l'organisateur est présente :
						// on ne déclenche pas le placement séquentiel complet.
						continue;
					}
					$hasRealSequentialOrder = true;
					break;
				}
			}
		}

		// Gestion du statut "A Payé" : persisté en base de données (participation.a_paye)
		$paidStatus = [];
		if (!isset($_SESSION['paid_status']) || !is_array($_SESSION['paid_status'])) {
			$_SESSION['paid_status'] = [];
		}
		if (!isset($_SESSION['paid_status'][$selectedActivityId]) || !is_array($_SESSION['paid_status'][$selectedActivityId])) {
			$_SESSION['paid_status'][$selectedActivityId] = [];
		}

		// Charge les statuts "A Payé" depuis la base de données pour initialiser la session
		foreach ($players as $p) {
			if (!isset($p['id-participation'])) continue;
			$pid = (int)$p['id-participation'];
			if ($pid > 0) {
				$isPaid = isset($p['a_paye']) ? (int)$p['a_paye'] : 0;
				if ($isPaid === 1) {
					$_SESSION['paid_status'][$selectedActivityId][$pid] = 1;
				}
			}
		}

		// Traite le changement du statut "A Payé" pour un joueur
		if (isset($_GET['paid_pid'])) {
			$paidPid = (int) $_GET['paid_pid'];
			if ($paidPid > 0) {
				// Toggle du statut payé pour ce joueur
				$newPaidStatus = 0;
				if (isset($_SESSION['paid_status'][$selectedActivityId][$paidPid]) && $_SESSION['paid_status'][$selectedActivityId][$paidPid]) {
					unset($_SESSION['paid_status'][$selectedActivityId][$paidPid]);
					$newPaidStatus = 0;
				} else {
					$_SESSION['paid_status'][$selectedActivityId][$paidPid] = 1;
					$newPaidStatus = 1;
				}
				// Mise à jour en base de données
				mysqli_query(
					$con,
					"UPDATE `participation` SET `a_paye` = " . $newPaidStatus . " WHERE `id-participation` = " . $paidPid
				);
				// Redirection pour rafraîchir la page
				header("Location: " . "tables.php?id_activite=" . $selectedActivityId . "&mode=" . $mode . "&equilibrage=" . $autoBalance);
				exit;
			}
		}

		// Endpoint pour le polling des changements de paiement
		if (isset($_GET['check_payments']) && $_GET['check_payments'] == 1) {
			// Récupérer les statuts actuels de paiement depuis la base de données
			$currentPaidStatuses = [];
			$sqlCurrentPayments = mysqli_query(
				$con,
				"SELECT `id-participation`, `a_paye` FROM `participation` WHERE `id-activite` = " . $selectedActivityId
			);
			if ($sqlCurrentPayments) {
				while ($row = mysqli_fetch_assoc($sqlCurrentPayments)) {
					$pid = (int)$row['id-participation'];
					$isPaid = (int)$row['a_paye'];
					if ($isPaid === 1) {
						$currentPaidStatuses[$pid] = 1;
					}
				}
			}
			// Renvoyer un hash simple pour comparer
			header('Content-Type: text/plain');
			echo md5(json_encode($currentPaidStatuses));
			exit;
		}
		
		// Charger les statuts "A Payé" directement depuis la base de données pour avoir les dernières données
		// (important pour la synchronisation multi-écran)
		$paidStatus = [];
		$sqlLoadPayments = mysqli_query(
			$con,
			"SELECT `id-participation`, `a_paye` FROM `participation` WHERE `id-activite` = " . $selectedActivityId
		);
		if ($sqlLoadPayments) {
			while ($row = mysqli_fetch_assoc($sqlLoadPayments)) {
				$pid = (int)$row['id-participation'];
				$isPaid = (int)$row['a_paye'];
				if ($isPaid === 1) {
					$paidStatus[$pid] = 1;
				}
			}
		}

		// 1) Si mode auto + équilibrage strict : calcul d'une répartition IDEALE, sans toucher aux sièges réels
		if ($mode === 'auto' && $autoBalance === 1) {
			// - même nombre de joueurs par table à 1 près
			// - entre 5 et $tableSize (8) joueurs par table quand c'est mathématiquement possible
			// - si nb-tables est configuré dans activite, on part de ce nombre comme maximum
			if ($activeCount > 0) {
				// Contraintes générales d'équilibrage
				$minPlayersPerTable = 5;
				$maxPlayersPerTable = $tableSize; // 8

				// On mélange l'ordre des joueurs actifs pour que le placement
				// sur les tables ne suive aucun classement particulier
				$shuffledActive = $activeIndexes;
				shuffle($shuffledActive);

				// Nombre max de tables autorisé :
				// - si nb-tables est défini, on ne dépasse pas cette valeur
				// - sinon, on part sur le nombre de tables nécessaires pour 8 joueurs max
				if ($maxTables !== null && $maxTables > 0) {
					$maxTablesAllowed = $maxTables;
				} else {
					$maxTablesAllowed = (int) ceil($activeCount / $tableSize);
				}
				if ($maxTablesAllowed < 1) {
					$maxTablesAllowed = 1;
				}

				// Nombre max de tables possibles en respectant le minimum de joueurs
				$maxPossibleByMin = (int) floor($activeCount / $minPlayersPerTable);
				if ($maxPossibleByMin < 1) {
					$maxPossibleByMin = 1;
				}

				$maxCandidateTables = min($maxTablesAllowed, $maxPossibleByMin);
				$chosenTables = null;

				// Recherche d'un nombre de tables k satisfaisant simultanément :
				// - au moins 5 joueurs par table
				// - au plus 8 joueurs par table (écart max de 1)
				for ($k = $maxCandidateTables; $k >= 1; $k--) {
					$base  = intdiv($activeCount, $k);
					$extra = $activeCount % $k;
					if ($base < $minPlayersPerTable) {
						continue;
					}
					$maxCount = $base + ($extra > 0 ? 1 : 0);
					if ($maxCount > $maxPlayersPerTable) {
						continue;
					}
					$chosenTables = $k;
					break;
				}

				// Si aucune configuration ne permet de respecter simultanément min=5 et max=8,
				// on choisit le meilleur compromis possible en respectant la capacité max.
				if ($chosenTables === null) {
					$tablesCountIdeal = min(
						$maxTablesAllowed,
						max(1, (int) ceil($activeCount / $maxPlayersPerTable))
					);
				} else {
					$tablesCountIdeal = $chosenTables;
				}

				// Répartition équilibrée sur tablesCountIdeal tables (écart max de 1 joueur)
				$maxCapacity     = $tablesCountIdeal * $tableSize;
				$assignableCount = min($activeCount, $maxCapacity);
				$basePerTable    = intdiv($assignableCount, $tablesCountIdeal);
				$extra           = $assignableCount % $tablesCountIdeal;
				$currentIdx      = 0;

				for ($t = 0; $t < $tablesCountIdeal; $t++) {
					$playersOnThisTable = $basePerTable + ($t < $extra ? 1 : 0);
					for ($s = 0; $s < $playersOnThisTable; $s++) {
						$pos = $currentIdx++;
						if ($pos >= $assignableCount) {
							break 2;
						}
						$playerIdx = $shuffledActive[$pos];
						$players[$playerIdx]['ideal_table'] = $t + 1;
						$players[$playerIdx]['ideal_seat']  = $s + 1;
					}
				}
			}
		}

		// 2) Construction de l'affichage des sièges :
		//    - en mode auto SANS équilibrage => on complète automatiquement les places libres
		//    - en mode sequential (avec ordre réel) => on attribue les sièges séquentiellement table par table
		//    - sinon (manuel OU auto+équilibrage, ou séquentiel sans ordre réel) => on affiche strictement les sièges enregistrés en base
		
		// Détermine quel champ de jetons utiliser selon le type d'activité
		// Type 1 : participation.jetons_total (jetons + bonus_ins + bonus_arrivee) | Type 2 : membres.jetons_1 | Type 3 : membres.jetons_2
		if ($activityType === 1) {
			$jetonsField = 'jetons_total';
		} elseif ($activityType === 3) {
			$jetonsField = 'jetons_2';
		} else {
			$jetonsField = 'jetons_1';
		}
		
		if ($mode === 'auto' && $autoBalance === 0) {
			// Auto sans équilibrage strict : on complète les tables existantes sans toucher aux sièges déjà affectés
			if ($maxTables !== null && $maxTables > 0) {
				$tablesCount = $maxTables;
			} else {
				$tablesCount = (int) ceil(($activeCount > 0 ? $activeCount : 0) / $tableSize);
			}
			// Respecte toujours au moins le nombre de tables déjà utilisées, mais aussi le config si défini
			$tablesCount = max($tablesCount, $maxAssignedTable);
			if ($tablesCount < 1) {
				$tablesCount = 1;
			}

			// 1) On place d'abord tous les joueurs qui ont déjà un siège enregistré en base
			$unseatedIndexes = [];
			for ($idx = 0; $idx < $totalPlayers; $idx++) {
				// On ne (re)place pas les joueurs éliminés
				if (isset($players[$idx]['option']) && strcasecmp($players[$idx]['option'], 'Elimine') === 0) {
					continue;
				}
				$tDb = isset($players[$idx]['id-table']) ? (int)$players[$idx]['id-table'] : 0;
				$sDb = isset($players[$idx]['id-siege']) ? (int)$players[$idx]['id-siege'] : 0;
				if ($tDb > 0 && $tDb <= $tablesCount && $sDb > 0 && $sDb <= $tableSize) {
					$players[$idx]['table_no'] = $tDb;
					$players[$idx]['seat_no']  = $sDb;
					$seatingTables[$tDb - 1][$sDb - 1] = $players[$idx];
				} else {
					$unseatedIndexes[] = $idx;
				}
			}

			// 2) Calcul des effectifs actuels par table, des sièges libres, et des totaux de jetons
			$perTableCounts = array_fill(0, $tablesCount, 0);
			$perTableJetons = array_fill(0, $tablesCount, 0);
			$freeSeatsByTable = [];
			for ($t = 0; $t < $tablesCount; $t++) {
				$freeSeatsByTable[$t] = [];
				for ($s = 0; $s < $tableSize; $s++) {
					if (isset($seatingTables[$t][$s]) && isset($seatingTables[$t][$s]['id-participation']) && $seatingTables[$t][$s]['id-participation']) {
						$perTableCounts[$t]++;
						if (isset($seatingTables[$t][$s][$jetonsField])) {
							$perTableJetons[$t] += (int)$seatingTables[$t][$s][$jetonsField];
						}
					} else {
						$freeSeatsByTable[$t][] = $s;
					}
				}
			}

			// 3) On complète les places libres avec les joueurs non encore assis
			//    en essayant de respecter le plus possible la contrainte
			//    "au moins 5 joueurs par table" et au plus 8.
			$idxCursor = 0;
			$unseatedTotal = count($unseatedIndexes);
			if ($unseatedTotal > 0) {
				// Tri les joueurs par nombre de jetons décroissant
				// Cela garantit que les gros joueurs sont placés en premier et ne se retrouvent pas ensemble
				usort($unseatedIndexes, function($idxA, $idxB) use ($players, $jetonsField) {
					$jetonsA = isset($players[$idxA][$jetonsField]) ? (int)$players[$idxA][$jetonsField] : 0;
					$jetonsB = isset($players[$idxB][$jetonsField]) ? (int)$players[$idxB][$jetonsField] : 0;
					return $jetonsB <=> $jetonsA; // Décroissant : plus gros en premier
				});

				$minPlayersPerTable = 5;
				$maxPlayersPerTable = $tableSize; // 8

				// Nombre de tables déjà utilisées (au moins un joueur dessus)
				$currentlyUsedTables = 0;
				for ($t = 0; $t < $tablesCount; $t++) {
					if ($perTableCounts[$t] > 0) {
						$currentlyUsedTables = $t + 1;
					}
				}
				// Ne jamais descendre en dessous du plus grand numéro de table déjà utilisé
				$currentlyUsedTables = max($currentlyUsedTables, $maxAssignedTable);

				// Nombre max de tables autorisé par la config/capacité
				if ($maxTables !== null && $maxTables > 0) {
					$maxTablesAllowed = $maxTables;
				} else {
					$maxTablesAllowed = (int) ceil(($activeCount > 0 ? $activeCount : 0) / $tableSize);
				}
				if ($maxTablesAllowed < 1) {
					$maxTablesAllowed = 1;
				}
				// On ne dépasse pas le nombre de tables effectivement créées
				$maxTablesAllowed = min($maxTablesAllowed, $tablesCount);

				// Nombre max de tables possibles en respectant (en moyenne) le minimum de joueurs
				$maxPossibleByMin = (int) floor($activeCount / $minPlayersPerTable);
				if ($maxPossibleByMin < 1) {
					$maxPossibleByMin = 1;
				}

				$maxCandidateTables = min($maxTablesAllowed, $maxPossibleByMin);
				// On ne descend pas en dessous des tables déjà utilisées
				$minTablesNeeded = max(1, min($tablesCount, $currentlyUsedTables));
				if ($maxCandidateTables < $minTablesNeeded) {
					$effectiveTables = $minTablesNeeded;
				} else {
					$effectiveTables = $maxCandidateTables;
				}

				// Répartition basée sur l'équilibre des jetons : on place toujours le prochain joueur
				// sur la table (parmi les effectiveTables premières) qui a
				// actuellement la plus petite somme de jetons et encore des sièges libres.
				while ($idxCursor < $unseatedTotal) {
					$bestTable = null;
					$bestJetons = null;
					for ($t = 0; $t < $effectiveTables; $t++) {
						if (empty($freeSeatsByTable[$t])) {
							continue;
						}
						if ($bestTable === null || $perTableJetons[$t] < $bestJetons) {
							$bestTable = $t;
							$bestJetons = $perTableJetons[$t];
						}
					}
					if ($bestTable === null) {
						// Plus de siège libre disponible
						break;
					}
					$seatIndex = array_shift($freeSeatsByTable[$bestTable]);
					$playerIdx = $unseatedIndexes[$idxCursor++];
					$players[$playerIdx]['table_no'] = $bestTable + 1;
					$players[$playerIdx]['seat_no']  = $seatIndex + 1;
					$seatingTables[$bestTable][$seatIndex] = $players[$playerIdx];
					$perTableCounts[$bestTable]++;
					// Important: mettre à jour les jetons pour cette table
					if (isset($players[$playerIdx][$jetonsField])) {
						$perTableJetons[$bestTable] += (int)$players[$playerIdx][$jetonsField];
					}
				}
			}
		} elseif ($mode === 'sequential' && $hasRealSequentialOrder) {
			// Placement séquentiel "tour de table" basé sur l'ordre réel d'arrivée :
			// l'utilisateur renseigne l'ordre d'arrivée, et on affecte ensuite
			// les sièges 1..N sur les tables 1..N, puis les sièges 2..N, etc.
			if ($maxTables !== null && $maxTables > 0) {
				$tablesCount = $maxTables;
			} else {
				$tablesCount = ($activeCount > 0) ? (int) ceil($activeCount / $tableSize) : 1;
			}
			if ($tablesCount < 1) {
				$tablesCount = 1;
			}

			// Construire la séquence à partir de l'ordre d'arrivée enregistré en session,
			// puis ajouter à la fin les joueurs actifs sans ordre d'arrivée.
			$sequenceIndexes = [];
			$usedIndexes = [];
			$organizerMemberId = 2;

			// 1) Joueurs pour lesquels un id-participation figure dans $sequentialOrder
			if (!empty($sequentialOrder)) {
				foreach ($sequentialOrder as $pidSequential) {
					$pidSequential = (int) $pidSequential;
					if ($pidSequential <= 0) continue;
					foreach ($activeIndexes as $idx) {
						if ((int)$players[$idx]['id-participation'] === $pidSequential) {
							if (!in_array($idx, $usedIndexes, true)) {
								$sequenceIndexes[] = $idx;
								$usedIndexes[] = $idx;
							}
							break;
						}
					}
				}
			}

			// 3) Met l'organisateur (id-membre = 2) en tête de séquence s'il est présent
			foreach ($sequenceIndexes as $k => $idx) {
				if (isset($players[$idx]['id-membre']) && (int)$players[$idx]['id-membre'] === $organizerMemberId) {
					unset($sequenceIndexes[$k]);
					array_unshift($sequenceIndexes, $idx);
					$sequenceIndexes = array_values($sequenceIndexes);
					break;
				}
			}

			// Réinitialise les affectations visuelles (hors joueurs éliminés)
			foreach ($players as $i => $p) {
				if (isset($p['option']) && strcasecmp($p['option'], 'Elimine') === 0) {
					continue;
				}
				$players[$i]['table_no'] = '-';
				$players[$i]['seat_no']  = '-';
			}

			$seatingTables = [];
			$maxAssignable = $tablesCount * $tableSize;
			$pos = 0;
			foreach ($sequenceIndexes as $idx) {
				if ($pos >= $maxAssignable) {
					break; // plus de capacité disponible
				}
				$seatNo  = intdiv($pos, $tablesCount) + 1; // 1,1,1.. puis 2,2,2..
				if ($seatNo > $tableSize) {
					break;
				}
				$tableNo = ($pos % $tablesCount) + 1;       // 1,2,3..N puis 1,2,3..N

				$players[$idx]['table_no'] = $tableNo;
				$players[$idx]['seat_no']  = $seatNo;
				$seatingTables[$tableNo - 1][$seatNo - 1] = $players[$idx];
				$pos++;
			}
		} else {
			// Mode manuel OU auto avec équilibrage strict :
			// on respecte les positions existantes (id-table / id-siege) sans rien ajouter
			if ($maxTables !== null && $maxTables > 0) {
				$tablesCount = $maxTables;
			} else {
				$tablesCount = (int) ceil(($activeCount > 0 ? $activeCount : 0) / $tableSize);
				if ($tablesCount < 1) {
					$tablesCount = 1;
				}
			}
			// Et on garde au moins toutes les tables déjà utilisées, mais aussi le config si défini
			$tablesCount = max($tablesCount, $maxAssignedTable);

			foreach ($players as $idx => $p) {
				if (isset($p['option']) && strcasecmp($p['option'], 'Elimine') === 0) {
					continue;
				}
				$t = (int)$p['id-table'];
				$s = (int)$p['id-siege'];
				if ($t > 0 && $t <= $tablesCount && $s > 0 && $s <= $tableSize) {
					$players[$idx]['table_no'] = $t;
					$players[$idx]['seat_no']  = $s;
					$seatingTables[$t - 1][$s - 1] = $players[$idx];
				}
			}
		}

		// Construction de la liste plate pour l'affichage du tableau récapitulatif
		foreach ($players as $p) {
			$flatSeating[] = $p;
		}
	}

	// S'assure que toutes les tables à afficher existent dans $seatingTables
	if (isset($tablesCount) && $tablesCount > 0) {
		for ($t = 0; $t < $tablesCount; $t++) {
			if (!isset($seatingTables[$t])) {
				$seatingTables[$t] = [];
			}
		}
	}

	// Force l'organisateur (id-membre = 2) à la table 1 siège 1
	// sauf en mode séquentiel, où l'ordre d'arrivée pilote déjà la séquence
	if (!empty($players) && $mode !== 'sequential') {
		$organizerMemberId        = 2;
		$organizerParticipationId = null;
		$organizerIndex           = null;

		foreach ($players as $idx => $p) {
			if (isset($p['id-membre']) && (int)$p['id-membre'] === $organizerMemberId) {
				$organizerParticipationId = (int)$p['id-participation'];
				$organizerIndex           = $idx;
				break;
			}
		}

		if ($organizerParticipationId !== null && $organizerIndex !== null) {
			if (!isset($tablesCount) || $tablesCount < 1) {
				$tablesCount = 1;
			}
			if (!isset($seatingTables[0])) {
				$seatingTables[0] = [];
			}

			// Si un autre joueur occupait déjà la place 1 de la table 1, on le libère
			$previousOccupantId = null;
			if (isset($seatingTables[0][0]) && isset($seatingTables[0][0]['id-participation']) &&
				(int)$seatingTables[0][0]['id-participation'] !== $organizerParticipationId) {
				$previousOccupantId = (int)$seatingTables[0][0]['id-participation'];
			}

			// Nettoie toute autre occurrence éventuelle de l'organisateur sur d'autres tables
			for ($t = 0; $t < $tablesCount; $t++) {
				if (!isset($seatingTables[$t])) continue;
				for ($s = 0; $s < $tableSize; $s++) {
					if (!isset($seatingTables[$t][$s])) continue;
					if ((int)$seatingTables[$t][$s]['id-participation'] === $organizerParticipationId && !($t === 0 && $s === 0)) {
						unset($seatingTables[$t][$s]);
					}
				}
			}

			$players[$organizerIndex]['table_no'] = 1;
			$players[$organizerIndex]['seat_no']  = 1;
			$seatingTables[0][0]                  = $players[$organizerIndex];

			// Met à jour aussi la liste plate déjà construite
			foreach ($flatSeating as &$fs) {
				if ((int)$fs['id-participation'] === $organizerParticipationId) {
					$fs['table_no'] = 1;
					$fs['seat_no']  = 1;
				} elseif ($previousOccupantId !== null && (int)$fs['id-participation'] === $previousOccupantId) {
					$fs['table_no'] = '-';
					$fs['seat_no']  = '-';
				}
			}
			unset($fs);

			// Libère également l'ancien occupant dans $players si nécessaire
			if ($previousOccupantId !== null) {
				foreach ($players as &$pp) {
					if ((int)$pp['id-participation'] === $previousOccupantId) {
						$pp['table_no'] = '-';
						$pp['seat_no']  = '-';
						break;
					}
				}
				unset($pp);
			}

			// Persiste ce placement par défaut en base
			mysqli_query(
				$con,
				"UPDATE `participation` SET `id-table` = 1, `id-siege` = 1 WHERE `id-participation` = " . $organizerParticipationId
			);
		}
	}

	// Synchronise la base avec les places effectivement calculées/affichées :
	// - en mode séquentiel pour libérer les places non utilisées
	// - en mode auto SANS équilibrage pour sauvegarder le remplissage aléatoire automatique
	// En mode manuel, c'est le drag & drop qui met à jour la BD
	if (($mode === 'sequential' && $hasRealSequentialOrder) || ($mode === 'auto' && $autoBalance === 0)) {
		foreach ($players as $p) {
			if (!isset($p['id-participation'])) continue;
			$pid = (int)$p['id-participation'];
			if ($pid <= 0) continue;
			// On ne modifie pas les joueurs éliminés
			if (isset($p['option']) && strcasecmp($p['option'], 'Elimine') === 0) {
				continue;
			}
			$newTable = (isset($p['table_no']) && $p['table_no'] !== '-' && (int)$p['table_no'] > 0) ? (int)$p['table_no'] : 0;
			$newSeat  = (isset($p['seat_no']) && $p['seat_no'] !== '-' && (int)$p['seat_no'] > 0) ? (int)$p['seat_no'] : 0;
			$origTable = isset($p['id-table']) ? (int)$p['id-table'] : 0;
			$origSeat  = isset($p['id-siege']) ? (int)$p['id-siege'] : 0;
			// Si aucune place n'est attribuée visuellement mais qu'une place existe en base,
			// on libère explicitement cette place (id-table/id-siege = 0).
			if ($newTable <= 0 || $newSeat <= 0) {
				if ($origTable > 0 || $origSeat > 0) {
					mysqli_query(
						$con,
						"UPDATE `participation` SET `id-table` = 0, `id-siege` = 0 WHERE `id-participation` = " . $pid
					);
				}
				continue; // pas de place attribuée visuellement
			}
			if ($origTable === $newTable && $origSeat === $newSeat) {
				continue; // déjà en base
			}
			mysqli_query(
				$con,
				"UPDATE `participation` SET `id-table` = " . $newTable . ", `id-siege` = " . $newSeat .
				" WHERE `id-participation` = " . $pid
			);
		}
	}

	// Trie le tableau récapitulatif par ordre alphabétique de pseudo
	if (!empty($flatSeating)) {
		usort($flatSeating, function($a, $b) {
			$pa = isset($a['pseudo']) ? mb_strtolower($a['pseudo'], 'UTF-8') : '';
			$pb = isset($b['pseudo']) ? mb_strtolower($b['pseudo'], 'UTF-8') : '';
			return $pa <=> $pb;
		});
	}

	// Recharger les données de placement actuelles depuis la BD pour s'assurer que le tableau
	// et les tables visuelles affichent toujours les données les plus à jour (important pour la synchronisation multi-écran)
	$sqlRefreshSeating = mysqli_query(
		$con,
		"SELECT `id-participation`, `id-table`, `id-siege` FROM `participation` WHERE `id-activite` = " . $selectedActivityId
	);
	if ($sqlRefreshSeating) {
		$seatingMap = [];
		while ($row = mysqli_fetch_assoc($sqlRefreshSeating)) {
			$pid = (int)$row['id-participation'];
			$seatingMap[$pid] = [
				'id-table' => (int)$row['id-table'],
				'id-siege' => (int)$row['id-siege']
			];
		}
		
		// Mettre à jour les données de placement dans $players
		foreach ($players as &$player) {
			$pid = isset($player['id-participation']) ? (int)$player['id-participation'] : 0;
			if ($pid > 0 && isset($seatingMap[$pid])) {
				$player['id-table'] = $seatingMap[$pid]['id-table'];
				$player['id-siege'] = $seatingMap[$pid]['id-siege'];
				// IMPORTANT: Mettre à jour aussi table_no et seat_no pour éviter les valeurs stales
				$player['table_no'] = $seatingMap[$pid]['id-table'] > 0 ? $seatingMap[$pid]['id-table'] : '-';
				$player['seat_no'] = $seatingMap[$pid]['id-siege'] > 0 ? $seatingMap[$pid]['id-siege'] : '-';
			}
		}
		unset($player);
		
		// Mettre à jour les données de placement dans $flatSeating
		foreach ($flatSeating as &$player) {
			$pid = isset($player['id-participation']) ? (int)$player['id-participation'] : 0;
			if ($pid > 0 && isset($seatingMap[$pid])) {
				$player['id-table'] = $seatingMap[$pid]['id-table'];
				$player['id-siege'] = $seatingMap[$pid]['id-siege'];
				// Mettre à jour le format approprié pour l'affichage
				$player['table_no'] = $seatingMap[$pid]['id-table'] > 0 ? $seatingMap[$pid]['id-table'] : '-';
				$player['seat_no'] = $seatingMap[$pid]['id-siege'] > 0 ? $seatingMap[$pid]['id-siege'] : '-';
			}
		}
		unset($player);
		
		// Reconstruire $seatingTables avec les données rechargeées
		$seatingTables = [];
		for ($i = 0; $i < 9; $i++) {
			$seatingTables[$i] = [];
		}
		foreach ($players as $playerData) {
			if (isset($playerData['option']) && strcasecmp($playerData['option'], 'Elimine') === 0) {
				continue;
			}
			$t = (int)$playerData['id-table'];
			$s = (int)$playerData['id-siege'];
			if ($t > 0 && $t <= 9 && $s > 0 && $s <= $tableSize) {
				$seatingTables[$t - 1][$s - 1] = $playerData;
			}
		}
	}

	// Si l'équilibrage auto est actif, prépare une suggestion de déplacement entre tables
	if ($mode === 'auto' && $autoBalance === 1 && $totalPlayers > 0) {
		// 1) Vérifie si la répartition ACTUELLE est déjà équilibrée
		//    (même nombre de joueurs par table à 1 près, et entre 5 et $tableSize joueurs).
		$currentCounts = [];
		foreach ($players as $p) {
			if (isset($p['option']) && strcasecmp($p['option'], 'Elimine') === 0) {
				continue;
			}
			$ct = isset($p['id-table']) ? (int)$p['id-table'] : 0;
			if ($ct > 0) {
				if (!isset($currentCounts[$ct])) {
					$currentCounts[$ct] = 0;
				}
				$currentCounts[$ct]++;
			}
		}

		$alreadyBalanced = false;
		if (!empty($currentCounts)) {
			$values = array_values($currentCounts);
			$minCur = min($values);
			$maxCur = max($values);
			$minPlayersPerTable = 5;
			$maxPlayersPerTable = $tableSize;

			// Tables considérées comme "équilibrées" si :
			// - tous les effectifs sont entre 5 et $tableSize
			// - et la différence max-min est au plus 1
			$allWithinBounds = true;
			foreach ($values as $v) {
				if ($v < $minPlayersPerTable || $v > $maxPlayersPerTable) {
					$allWithinBounds = false;
					break;
				}
			}
			if ($allWithinBounds && ($maxCur - $minCur) <= 1) {
				$alreadyBalanced = true;
			}
		}

		// 2) Si les tables sont déjà équilibrées, on considère que la disposition
		//    actuelle (manuelle) est prioritaire : aucune suggestion de déplacement.
		//    Sinon, on se limite à indiquer UN mouvement prioritaire :
		//    - cas principal : prendre un joueur sur une table au-dessus du minimum
		//      et l'envoyer vers une table en dessous de 5 joueurs
		//    - cas secondaire : si toutes les tables sont à 5+ joueurs mais que
		//      l'écart entre la table la plus chargée et la moins chargée est > 1,
		//      on suggère aussi un déplacement de la table la plus pleine vers la
		//      table la moins pleine.
		if (!$alreadyBalanced) {
			$activePlayersCount = isset($activeCount) ? (int)$activeCount : 0;

			// Cas ultra-fin de tournoi :
			// si moins de 10 joueurs actifs au total et qu'il reste
			// des joueurs sur une autre table que la table 1,
			// on cherche à regrouper tout le monde sur la table 1.
			if ($activePlayersCount > 0 && $activePlayersCount < 10) {
				$fromTable = null;
				foreach ($currentCounts as $tableNo => $cnt) {
					$tableNo = (int)$tableNo;
					if ($tableNo === 1) continue;
					if ($cnt <= 0) continue;
					// On privilégie la table au numéro le plus élevé
					if ($fromTable === null || $tableNo > $fromTable) {
						$fromTable = $tableNo;
					}
				}
				if ($fromTable !== null) {
					$suggestionFromTable = (int)$fromTable;
					$suggestionToTable   = 1;
				}
			}
			// Cas spécial de fin de tournoi (entre 10 et 14 joueurs) :
			// si moins de 15 joueurs au total et qu'il reste encore
			// des joueurs sur la table 2 (verte), on suggère en
			// priorité de déplacer un joueur de la table 2 vers
			// une autre table.
			elseif ($activePlayersCount > 0 && $activePlayersCount < 15 && isset($currentCounts[2]) && $currentCounts[2] > 0) {
				$fromTable = 2;
				$toTable   = null;
				$bestCount = PHP_INT_MAX;
				foreach ($currentCounts as $tableNo => $cnt) {
					$tableNo = (int)$tableNo;
					if ($tableNo === $fromTable) continue;
					if ($cnt <= 0) continue;
					if ($cnt < $bestCount) {
						$bestCount = $cnt;
						$toTable   = $tableNo;
					}
				}
				if ($toTable === null) {
					// Par sécurité, si aucune autre table trouvée, on cible la table 1
					$toTable = 1;
				}
				$suggestionFromTable = (int)$fromTable;
				$suggestionToTable   = (int)$toTable;
			} else {
				$minPlayersPerTable = 5;
				$maxPlayersPerTable = $tableSize;

				// Repère les tables en dessous du minimum et celles au-dessus
				$underMinTables = [];
				$overMinTables  = [];
				foreach ($currentCounts as $tableNo => $cnt) {
					if ($cnt < $minPlayersPerTable) {
						$underMinTables[] = (int)$tableNo;
					} elseif ($cnt > $minPlayersPerTable) {
						$overMinTables[] = (int)$tableNo;
					}
				}

				// Si pas de table en dessous de 5 ou pas de table en surplus,
				// on ne propose rien dans ce cas, MAIS on pourra éventuellement
				// proposer un mouvement si l'écart global max/min est > 1.
				if (!empty($underMinTables) && !empty($overMinTables)) {
					// Table destination : celle avec le plus petit effectif parmi les tables < 5
					$bestUnderTable = null;
					$bestUnderCount = PHP_INT_MAX;
					foreach ($underMinTables as $tNo) {
						$cnt = isset($currentCounts[$tNo]) ? $currentCounts[$tNo] : 0;
						if ($cnt < $bestUnderCount) {
							$bestUnderCount = $cnt;
							$bestUnderTable = $tNo;
						}
					}

					// Table source : celle avec le plus grand effectif parmi les tables > 5
					$bestOverTable = null;
					$bestOverCount = -1;
					foreach ($overMinTables as $tNo) {
						$cnt = isset($currentCounts[$tNo]) ? $currentCounts[$tNo] : 0;
						if ($cnt > $bestOverCount) {
							$bestOverCount = $cnt;
							$bestOverTable = $tNo;
						}
					}

					if ($bestUnderTable !== null && $bestOverTable !== null) {
						$suggestionFromTable = (int)$bestOverTable;
						$suggestionToTable   = (int)$bestUnderTable;
					}
				} elseif (!empty($currentCounts) && ($maxCur - $minCur) > 1) {
					// Cas où toutes les tables ont au moins 5 joueurs mais où
					// l'écart max/min reste > 1 : on suggère un mouvement de la
					// table la plus pleine vers la table la moins pleine.
					$bestUnderTable = null; // ici: table la moins fournie (minCur)
					$bestOverTable  = null; // ici: table la plus fournie (maxCur)
					foreach ($currentCounts as $tableNo => $cnt) {
						if ($cnt === $minCur && $bestUnderTable === null) {
							$bestUnderTable = (int)$tableNo;
						}
						if ($cnt === $maxCur && $bestOverTable === null) {
							$bestOverTable = (int)$tableNo;
						}
					}
					if ($bestUnderTable !== null && $bestOverTable !== null && $bestUnderTable !== $bestOverTable) {
						$suggestionFromTable = $bestOverTable;
						$suggestionToTable   = $bestUnderTable;
					}
				}
			}
		}
	}
}
?>
<?php
// Assure l'existence d'une table de backup pour pouvoir annuler l'opération
$createBackupTableSql = "CREATE TABLE IF NOT EXISTS `participation_jetons_backup` (
	`id` INT AUTO_INCREMENT PRIMARY KEY,
	`id_participation` INT NOT NULL,
	`activity_id` INT NOT NULL,
	`jetons` INT DEFAULT 0,
	`jetons_bonus_ins` INT DEFAULT 0,
	`jetons_bonus_arrivee` INT DEFAULT 0,
	`jetons_total` INT DEFAULT 0,
	`created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
	KEY (`activity_id`),
	KEY (`id_participation`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
mysqli_query($con, $createBackupTableSql);
// S'assurer que les colonnes de bonus existent (pour les anciens schémas)
$colCheck = mysqli_query($con, "SHOW COLUMNS FROM participation_jetons_backup LIKE 'jetons_bonus_ins'");
if ($colCheck && mysqli_num_rows($colCheck) === 0) {
	mysqli_query($con, "ALTER TABLE participation_jetons_backup ADD COLUMN `jetons_bonus_ins` INT DEFAULT 0");
}
$colCheck2 = mysqli_query($con, "SHOW COLUMNS FROM participation_jetons_backup LIKE 'jetons_bonus_arrivee'");
if ($colCheck2 && mysqli_num_rows($colCheck2) === 0) {
	mysqli_query($con, "ALTER TABLE participation_jetons_backup ADD COLUMN `jetons_bonus_arrivee` INT DEFAULT 0");
}

// Handler pour affecter un même nombre de jetons à tous les joueurs de l'activité
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	// Annuler la dernière assignation
	if (isset($_POST['undo_assign_jetons']) && $selectedActivityId > 0) {
		// Récupérer le timestamp le plus récent pour cette activité
		$res = mysqli_query($con, "SELECT MAX(created_at) AS last_ts FROM participation_jetons_backup WHERE activity_id = " . intval($selectedActivityId));
		$row = $res ? mysqli_fetch_assoc($res) : null;
		$lastTs = $row && $row['last_ts'] ? $row['last_ts'] : null;
		if ($lastTs) {
			// Restaurer les valeurs depuis la sauvegarde (incluant les bonus)
			$restoreQ = "SELECT id_participation, jetons, jetons_bonus_ins, jetons_bonus_arrivee, jetons_total FROM participation_jetons_backup WHERE activity_id = " . intval($selectedActivityId) . " AND created_at = '" . mysqli_real_escape_string($con, $lastTs) . "'";
			$r2 = mysqli_query($con, $restoreQ);
			while ($rrow = mysqli_fetch_assoc($r2)) {
				$pid = intval($rrow['id_participation']);
				$jet = intval($rrow['jetons']);
				$bonusIns = intval($rrow['jetons_bonus_ins']);
				$bonusArr = intval($rrow['jetons_bonus_arrivee']);
				$jtot = intval($rrow['jetons_total']);
				mysqli_query($con, "UPDATE participation SET jetons = " . $jet . ", jetons_bonus_ins = " . $bonusIns . ", jetons_bonus_arrivee = " . $bonusArr . ", jetons_total = " . $jtot . " WHERE `id-participation` = " . $pid);
			}
			// Supprimer les entrées restaurées pour éviter double-undo
			mysqli_query($con, "DELETE FROM participation_jetons_backup WHERE activity_id = " . intval($selectedActivityId) . " AND created_at = '" . mysqli_real_escape_string($con, $lastTs) . "'");
			// Recalcul / mise à jour de la moyenne stockée
			mysqli_query($con, "UPDATE activite a SET jetons_activite = (SELECT FLOOR(AVG(COALESCE(p.jetons,0))) FROM participation p WHERE p.`id-activite` = " . intval($selectedActivityId) . ") WHERE a.`id-activite` = " . intval($selectedActivityId));
		}

		$redirectUrl = 'tables.php?id_activite=' . $selectedActivityId . '&mode=' . htmlspecialchars($mode, ENT_QUOTES, 'UTF-8') . '&equilibrage=' . (int)$autoBalance;
		header('Location: ' . $redirectUrl);
		exit;
	}

	// Assignation globale — créer une sauvegarde avant
	if ($selectedActivityId > 0) {
		// Action: assigner jetons sans modifier le bonus arrivée
		if (isset($_POST['assign_all_jetons_no_arrive']) && isset($_POST['assign_all_jetons'])) {
			$assignVal = intval($_POST['assign_all_jetons']);
			$assignVal = max(0, $assignVal);

			// Sauvegarde des valeurs actuelles pour cette activité (incluant les bonus)
			$backupInsert = "INSERT INTO participation_jetons_backup (id_participation, activity_id, jetons, jetons_bonus_ins, jetons_bonus_arrivee, jetons_total, created_at) 
				SELECT p.`id-participation`, p.`id-activite`, COALESCE(p.jetons,0), COALESCE(p.jetons_bonus_ins,0), COALESCE(p.jetons_bonus_arrivee,0), COALESCE(p.jetons_total,0), NOW()
				FROM participation p
				WHERE p.`id-activite` = " . intval($selectedActivityId);
			mysqli_query($con, $backupInsert);

			// Mise à jour des jetons et recalcul jetons_total sans toucher jetons_bonus_arrivee
			$bonusInsVal = 5000;
			$sql = "UPDATE `participation` SET `jetons` = " . intval($assignVal) . ", `jetons_bonus_ins` = " . $bonusInsVal . ", `jetons_total` = " . (intval($assignVal) . " + " . $bonusInsVal . " + COALESCE(`jetons_bonus_arrivee`,0)") . " WHERE `id-activite` = " . $selectedActivityId;
			mysqli_query($con, $sql);

			// Met à jour la valeur moyenne stockée dans `activite.jetons_activite`
			mysqli_query($con, "UPDATE `activite` SET `jetons_activite` = " . intval($assignVal) . " WHERE `id-activite` = " . $selectedActivityId);

			// Redirection pour éviter la double soumission
			$redirectUrl = 'tables.php?id_activite=' . $selectedActivityId . '&mode=' . htmlspecialchars($mode, ENT_QUOTES, 'UTF-8') . '&equilibrage=' . (int)$autoBalance;
		header('Location: ' . $redirectUrl);
		exit;
	}
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
	<title>Admin | Tables de poker</title>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimum-scale=1.0, maximum-scale=1.0">
	<?php if ($selectedActivityId > 0): ?>
		<script>
			// Rafraîchissement conditionnel : on interroge périodiquement le serveur
			// pour savoir si l'état des tables a changé pour cette activité.
			(function() {
				var lastVersion = null;
				var activityId = <?php echo (int)$selectedActivityId; ?>;
				function checkUpdate() {
					var xhr = new XMLHttpRequest();
					xhr.open('GET', 'tables_ping.php?id_activite=' + activityId, true);
					xhr.onreadystatechange = function () {
						if (xhr.readyState === 4 && xhr.status === 200) {
							try {
								var resp = JSON.parse(xhr.responseText);
								if (!resp || typeof resp.version === 'undefined') return;
								if (lastVersion === null) {
									lastVersion = resp.version;
								} else if (resp.version !== lastVersion) {
									window.location.reload();
								}
							} catch (e) {
								// en cas d'erreur de parsing, on ignore simplement
							}
						}
					};
					xhr.send();
				}
				// Première vérification immédiate, puis polling régulier
				checkUpdate();
				setInterval(checkUpdate, 1000); // toutes les 1 seconde pour un rafraîchissement quasi immédiat
			})();
		</script>
	<?php endif; ?>
	<link href="http://fonts.googleapis.com/css?family=Lato:300,400,400italic,600,700|Raleway:300,400,500,600,700|Crete+Round:400italic" rel="stylesheet" type="text/css" />
	<link rel="stylesheet" href="vendor/bootstrap/css/bootstrap.min.css">
	<link rel="stylesheet" href="vendor/fontawesome/css/font-awesome.min.css">
	<link rel="stylesheet" href="vendor/themify-icons/themify-icons.min.css">
	<link href="vendor/animate.css/animate.min.css" rel="stylesheet" media="screen">
	<link href="vendor/perfect-scrollbar/perfect-scrollbar.min.css" rel="stylesheet" media="screen">
	<link href="vendor/switchery/switchery.min.css" rel="stylesheet" media="screen">
	<link href="vendor/bootstrap-touchspin/jquery.bootstrap-touchspin.min.css" rel="stylesheet" media="screen">
	<link href="vendor/select2/select2.min.css" rel="stylesheet" media="screen">
	<link href="vendor/bootstrap-datepicker/bootstrap-datepicker3.standalone.min.css" rel="stylesheet" media="screen">
	<link href="vendor/bootstrap-timepicker/bootstrap-timepicker.min.css" rel="stylesheet" media="screen">
	<link rel="stylesheet" href="assets/css/styles.css?v=<?php echo time(); ?>">
	<link rel="stylesheet" href="assets/css/plugins.css?v=<?php echo time(); ?>">
	<link rel="stylesheet" href="assets/css/themes/theme-1.css?v=<?php echo time(); ?>" id="skin_color" />
	<!-- Même fond moderne que le dashboard (pattern cartes répété) -->
	<link rel="stylesheet" href="assets/css/modern-dashboard.css?v=<?php echo time(); ?>">
	<link rel="stylesheet" href="assets/css/card-bg.css?v=<?php echo time(); ?>">
	<style>
		/* Réduction de l'espace vertical autour du titre de page */
		#page-title {
			margin-top: 0;
			margin-bottom: 0;
			padding-top: 0;
			padding-bottom: 0;
		}

		#page-title .mainTitle {
			margin-top: 4px;
			margin-bottom: 4px;
		}

		/* Sélection visuelle d'un siège en mode tactile/clic */
		.seat.seat-selected {
			outline: 3px solid #00d2ff;
			outline-offset: 2px;
		}

		/* Surbrillance des suggestions d'équilibrage auto */
		.seat.suggest-from {
			outline: 3px solid #ff9800;
			outline-offset: 2px;
		}

		.seat.suggest-to {
			outline: 3px solid #4caf50;
			outline-offset: 2px;
		}

		/* Suppression du fond blanc derrière les tables sur cette page */
		.container-fullw.bg-white,
		.panel.panel-white,
		.panel-white > .panel-body {
			background: transparent !important;
			box-shadow: none;
			border: none;
		}

		.poker-wrapper {
			display: flex;
			flex-direction: column;
			align-items: center;
			justify-content: flex-start;
			padding: 20px 10px 40px;
		}

		.poker-table-container {
			position: relative;
			width: 520px;
			height: 520px;
			max-width: 100%;
			margin: 8px auto;
		}

		.poker-table-center {
			position: absolute;
			top: 50%;
			left: 50%;
			width: 320px;
			height: 220px;
			max-width: 70%;
			max-height: 60%;
			transform: translate(-50%, -50%);
			border-radius: 50% / 60%;
			/* Fond par défaut : bordeaux */
			background: radial-gradient(circle at 30% 30%, #8b1a2b, #4c0812);
			box-shadow: 0 0 0 8px #5e3b1f, 0 12px 30px rgba(0, 0, 0, 0.4);
			border: 3px solid rgba(255, 255, 255, 0.15);
			display: flex;
			align-items: center;
			justify-content: center;
			color: #f5f5f5;
			font-size: 20px;
			font-weight: 600;
			text-shadow: 0 2px 4px rgba(0, 0, 0, 0.6);
		}

		/* Variantes de couleur selon le rôle de la table */
		.poker-table-center.table-bordeaux {
			background: radial-gradient(circle at 30% 30%, #8b1a2b, #4c0812);
		}

		.poker-table-center.table-vert {
			background: radial-gradient(circle at 30% 30%, #3fa868, #136537);
			/* Table 2 (verte) ronde lorsqu'elle est utilisée */
			width: 260px;
			height: 260px;
			border-radius: 50%;
		}

		.poker-table-center.table-noir {
			background: radial-gradient(circle at 30% 30%, #333333, #000000);
		}

		.poker-table-center span {
			letter-spacing: 0.08em;
			text-transform: uppercase;
		}

		.seat {
			position: absolute;
			width: 110px;
			height: 85px;
			background: rgba(255, 255, 255, 0.96);
			border-radius: 10px;
			box-shadow: 0 4px 10px rgba(0, 0, 0, 0.35);
			display: flex;
			flex-direction: column;
			align-items: center;
			justify-content: center;
			padding: 4px 6px;
			box-sizing: border-box;
			font-size: 12px;
			text-align: center;
		}

		.seat .seat-rank {
			font-size: 11px;
			font-weight: 600;
			color: #888;
			margin-bottom: 2px;
		}

		.seat .seat-name {
			font-size: 16px;
			font-weight: 700;
			color: #222;
			white-space: nowrap;
			overflow: visible;
			text-overflow: ellipsis;
			max-width: 100%;
		}

		.seat.empty {
			background: rgba(255, 255, 255, 0.85);
			color: #aaa;
			font-style: italic;
		}

		/* Siège occupé : le faire ressortir visuellement */
		.seat:not(.empty) {
			border: 7px solid #ffd54f;
			box-shadow: 0 0 25px rgba(255, 213, 79, 1), 0 0 40px rgba(255, 213, 79, 0.8), inset 0 0 15px rgba(255, 213, 79, 0.4);
		}

		/* Disposition symétrique sur un cercle pour 9 joueurs maximum */
 		.seat-1 { /* haut centre - 0° */
			top: 12%;
			left: 50%;
			transform: translate(-50%, -50%);
		}

		.seat-2 { /* haut droite - 40° */
			top: 19%;
			left: 75%;
			transform: translate(-50%, -50%);
		}

		.seat-3 { /* droite - 80° */
			top: 40%;
			left: 94%;
			transform: translate(-50%, -50%);
		}

		.seat-4 { /* bas droite - 120° */
			top: 78%;
			left: 88%;
			transform: translate(-50%, -50%);
		}

		.seat-5 { /* bas centre-droite - 160° */
			top: 88%;
			left: 64%;
			transform: translate(-50%, -50%);
		}

		.seat-6 { /* bas centre-gauche - 200° */
			top: 88%;
			left: 36%;
			transform: translate(-50%, -50%);
		}

		.seat-7 { /* bas gauche - 240° */
			top: 78%;
			left: 12%;
			transform: translate(-50%, -50%);
		}

		.seat-8 { /* gauche - 280° */
			top: 40%;
			left: 6%;
			transform: translate(-50%, -50%);
		}

		.seat-9 { /* haut gauche - 320° */
			top: 19%;
			left: 25%;
			transform: translate(-50%, -50%);
		}

		@media (max-width: 600px) {
			.poker-table-container {
				width: 100%;
				height: 430px;
			}

			.poker-table-center {
				width: 260px;
				height: 180px;
			}

			.seat {
				width: 90px;
				height: 54px;
				font-size: 11px;
			}
		}

		.players-list {
			max-width: 900px;
			width: 100%;
			margin-top: 20px;
			background: rgba(255, 255, 255, 0.95);
			border-radius: 8px;
			padding: 10px 12px;
			box-shadow: 0 4px 12px rgba(0, 0, 0, 0.18);
		}

		.players-list table {
			font-size: 12px;
			background-color: #ffffff;
		}

		.players-list tr.row-selected {
			background-color: rgba(0, 210, 255, 0.15) !important;
			font-weight: 600;
		}

		/* Disposition des tables sur une même ligne avec positionnement fixe */
		.tables-row {
			display: flex;
			flex-wrap: wrap;
			justify-content: space-around;
			align-items: flex-start;
			gap: 10px 20px; /* 10px vertical, 20px horizontal */
			width: 100%;
		}

		/* Positionner les tables : bordeaux à gauche, noir à droite */
		.poker-table-container.table-bordeaux {
			order: 1;
			flex: 0 0 auto;
		}

		.poker-table-container.table-vert {
			order: 2;
			flex: 0 0 auto;
		}

		.poker-table-container.table-noir {
			order: 3;
			flex: 0 0 auto;
		}
	</style>
</head>
<body>
<div id="app">
	<?php include('include/sidebar.php'); ?>
	<div class="app-content">
		<?php include('include/header.php'); ?>
		<div class="main-content">
			<div class="wrap-content container" id="container">
				<section id="page-title">
					<div class="row">
						<div class="col-sm-12 text-center">
							<h1 class="mainTitle">Placement des Joueurs</h1>
						</div>
						<ol class="breadcrumb"></ol>
					</div>
				</section>
				<div class="container-fluid container-fullw bg-white">
					<div class="row">
						<div class="col-md-12">
							<div class="panel panel-white">
								<div class="panel-body">
									<div class="poker-wrapper">
										<form method="get" class="form-inline" style="margin-bottom: 15px;">
											<div class="form-group">
												<label for="id_activite" style="margin-right: 8px;">Activité :</label>
												<select name="id_activite" id="id_activite" class="form-control">
													<option value="0">-- Sélectionner --</option>
													<?php foreach ($activities as $act): ?>
														<?php
															$id = (int)$act['id-activite'];
															$selected = ($id === $selectedActivityId) ? 'selected' : '';
															$labelDate = '';
															if (!empty($act['date_depart'])) {
																$ts = strtotime($act['date_depart']);
																if ($ts) {
																	$labelDate = date('d/m/Y', $ts) . ' - ';
																}
															}
														?>
														<option value="<?php echo $id; ?>" <?php echo $selected; ?> >
															<?php echo htmlspecialchars($labelDate . $act['titre-activite']); ?>
														</option>
													<?php endforeach; ?>
													</select>
											</div>
											<div class="form-group" style="margin-left: 15px;">
												<label style="margin-right: 8px;">Placement :</label>
												<label class="radio-inline">
													<input type="radio" name="mode" value="auto" <?php echo $mode === 'auto' ? 'checked' : ''; ?>> Auto
												</label>
												<label class="radio-inline" style="margin-left: 8px;">
													<input type="radio" name="mode" value="manual" <?php echo $mode === 'manual' ? 'checked' : ''; ?>> Manuel
												</label>
												<label class="radio-inline" style="margin-left: 8px;">
													<input type="radio" name="mode" value="sequential" <?php echo $mode === 'sequential' ? 'checked' : ''; ?>> Séquentiel
												</label>
												<label class="radio-inline" style="margin-left: 15px;">
													Équilibrage auto&nbsp;:
												</label>
												<label class="radio-inline">
													<input type="radio" name="equilibrage" value="1" <?php echo ($autoBalance === 1) ? 'checked' : ''; ?>> Oui
												</label>
												<label class="radio-inline" style="margin-left: 4px;">
													<input type="radio" name="equilibrage" value="0" <?php echo ($autoBalance === 0) ? 'checked' : ''; ?>> Non
												</label>
											</div>
											<button type="submit" class="btn btn-primary" style="margin-left: 8px;">Ok</button>
										</form>

										<?php if ($mode === 'auto' && $autoBalance === 1): ?>
											<div class="alert alert-info" id="auto-suggestion-alert" style="max-width:900px;margin:5px auto 10px;">
												<strong>Équilibrage auto actif :</strong> les tables idéales sont calculées en tâche de fond, mais aucun déplacement n'est appliqué automatiquement.
												<?php if ($suggestionFromTable > 0 && $suggestionToTable > 0): ?>
													<br>
													<span id="auto-suggestion-text">
														La future grosse blinde de la table <?php echo (int)$suggestionFromTable; ?> se déplace vers la table <?php echo (int)$suggestionToTable; ?>.
													</span>
													<button type="button" id="reset-auto-suggestion" class="btn btn-default btn-sm" style="margin-left:8px;">Reset</button>
													<small class="text-muted" style="margin-left:6px;">Cliquer sur Reset quand vous avez déplacé le joueur.</small>
												<?php else: ?>
													<br>Aucun changement de place n'est nécessaire pour le moment.
												<?php endif; ?>
											</div>
										<?php endif; ?>

										<?php if ($selectedActivityId > 0): ?>
											<?php if (empty($flatSeating)): ?>
												<p>Aucun joueur trouvé pour cette activité.</p>
											<?php else: ?>
												<?php $tableSize = 9; ?>
												<div class="tables-row">
												<?php
													$renderTablesCount = count($seatingTables);
													
													// Premier pass : compter les tables qui seront affichées
													$visibleTableCount = 0;
													$visibleTables = [];
													foreach ($seatingTables as $tableIndex => $tablePlayers):
														$hasPlayer = false;
														for ($iCheck = 0; $iCheck < $tableSize; $iCheck++) {
															if (isset($tablePlayers[$iCheck]) && isset($tablePlayers[$iCheck]['id-participation']) && $tablePlayers[$iCheck]['id-participation']) {
																$hasPlayer = true;
																break;
															}
														}
														$shouldDisplay = true;
														if (!$hasPlayer) {
															$tableNumber    = $tableIndex + 1;
															$activePlayers  = isset($activeCount) ? (int)$activeCount : 0;
															if ($activePlayers > 0) {
																if ($activePlayers < 10) {
																	if ($tableNumber !== 1) {
																		$shouldDisplay = false;
																	}
																} elseif ($activePlayers <= 14) {
																	if ($tableNumber === 2) {
																		$shouldDisplay = false;
																	}
																}
															}
															if ($shouldDisplay && !($maxTables !== null && $maxTables > 0 && $tableNumber <= $maxTables)) {
																$shouldDisplay = false;
															}
														}
														if ($shouldDisplay) {
															$visibleTables[] = $tableIndex;
															$visibleTableCount++;
														}
													endforeach;
													
													// Deuxième pass : afficher les tables avec les bonnes couleurs
													$displayIndex = 0;
													foreach ($visibleTables as $visibleIndex):
														$displayIndex++;
														$tableIndex = $visibleIndex;
														$tablePlayers = $seatingTables[$tableIndex];
														$tableNumber = $tableIndex + 1;
														
														// Assigner les couleurs selon le nombre total de tables visibles
														// Si 2 tables: bordeaux + noir (pas de vert)
														// Si 3+ tables: bordeaux + vert + noir
														$colorClass = 'table-bordeaux';
														if ($visibleTableCount === 2) {
															// Avec 2 tables: 1ère=bordeaux, 2ème=noir
															if ($displayIndex === 2) {
																$colorClass = 'table-noir';
															}
														} else {
															// Avec 3+ tables: utiliser le numéro de table
															if ($tableNumber === 2) {
																$colorClass = 'table-vert';
															} elseif ($tableNumber === 3) {
																$colorClass = 'table-noir';
															}
														}
													?>
													<div class="poker-table-container <?php echo $colorClass; ?>">
														<div class="poker-table-center <?php echo $colorClass; ?>" data-table="<?php echo $tableIndex + 1; ?>" style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 5px;">
															<span style="font-size: 1.1em;">Table <?php echo $tableIndex + 1; ?></span>
															<?php
																// Calcul du total de jetons pour cette table
																// Utilise jetons_total (avec bonus) si activityType=1, jetons_2 si activityType=3, sinon jetons_1
																$tableJetonsField = ($activityType === 1) ? 'jetons_total' : (($activityType === 3) ? 'jetons_2' : 'jetons_1');
																$tableJetonsTotal = 0;
																$tablePlayersCount = 0;
																foreach ($tablePlayers as $player) {
																	if (isset($player[$tableJetonsField]) && isset($player['id-participation']) && $player['id-participation']) {
																		$tableJetonsTotal += (int)$player[$tableJetonsField];
																		$tablePlayersCount++;
																	}
																}
																$tableJetonsAverage = $tablePlayersCount > 0 ? $tableJetonsTotal / $tablePlayersCount : 0;
																if ($tableJetonsTotal > 0) {
																	echo '<div style="font-size: 0.9em; color: #fff; font-weight: bold; text-align: center;">' . number_format($tableJetonsTotal, 0, ',', ' ') . '</div>';
																	echo '<div style="font-size: 0.8em; color: #ccc; text-align: center; margin-top: 3px;">Moy : ' . number_format($tableJetonsAverage, 0, ',', ' ') . '</div>';
																}
															?>
														</div>
														<?php for ($i = 0; $i < $tableSize; $i++): ?>
															<?php
																$player = isset($tablePlayers[$i]) ? $tablePlayers[$i] : null;
																$seatClass = 'seat seat-' . ($i + 1);
																// Affiche l'id-siege (numéro de siège logique) au-dessus du pseudo
																$seatLabel = ($player && isset($player['seat_no']) && $player['seat_no'] !== '-' && (int)$player['seat_no'] > 0)
																	? (int)$player['seat_no']
																	: 'Libre';
																$participationId = $player ? (int)$player['id-participation'] : '';
																$draggableAttr = $player ? 'draggable="true"' : '';
															?>
															<div class="<?php echo $seatClass; ?><?php echo $player ? '' : ' empty'; ?>"
															     data-table="<?php echo $tableIndex + 1; ?>"
															     data-seat="<?php echo $i + 1; ?>"
															     data-participation-id="<?php echo $participationId; ?>"
															     <?php echo $draggableAttr; ?>>
																<div class="seat-rank"><?php echo $seatLabel; ?></div>
																<div class="seat-name">
																	<?php echo $player ? htmlspecialchars($player['pseudo']) : 'Libre'; ?>
																	<?php if ($player): ?>
																		<?php 
																			$jetonsToShow = 0;
																			if (isset($activityType)) {
																				if ($activityType == 1 && isset($player['jetons_total']) && (int)$player['jetons_total'] != 0) {
																					$jetonsToShow = (int)$player['jetons_total'];
																				} elseif ($activityType == 2 && isset($player['jetons_1']) && (int)$player['jetons_1'] != 0) {
																					$jetonsToShow = (int)$player['jetons_1'];
																				} elseif ($activityType == 3 && isset($player['jetons_2']) && (int)$player['jetons_2'] != 0) {
																					$jetonsToShow = (int)$player['jetons_2'];
																				}
																			}
																			if ($jetonsToShow > 0) {
																				echo '<div style="font-size: 0.85em; color: #666; margin-top: 2px; font-weight: bold;">' . number_format($jetonsToShow, 0, ',', ' ') . '</div>';
																			}
																		?>
																	<?php endif; ?>
																</div>
															</div>
														<?php endfor; ?>
													</div>
												<?php endforeach; ?>
												</div>

												<?php 
													// Calcul des statistiques de jetons pour les joueurs affichés
													$jetonsList = [];
													foreach ($flatSeating as $player) {
														if (isset($player[$jetonsField])) {
															$jetonsList[] = (int)$player[$jetonsField];
														}
													}
													
													$minJetons = count($jetonsList) > 0 ? min($jetonsList) : 0;
													$maxJetons = count($jetonsList) > 0 ? max($jetonsList) : 0;
													$avgJetons = count($jetonsList) > 0 ? array_sum($jetonsList) / count($jetonsList) : 0;
													
													// Sauvegarde la moyenne dans activite.jetons_activite
													mysqli_query($con, "UPDATE `activite` SET `jetons_activite` = " . (int)$avgJetons . " WHERE `id-activite` = " . $selectedActivityId);
												?>

												<div class="alert alert-success" style="max-width:900px;margin:10px auto 0;">
													<strong>Statistiques Jetons :</strong>
													Minimum : <strong><?php echo number_format($minJetons, 0, ',', ' '); ?></strong> | 
													Maximum : <strong><?php echo number_format($maxJetons, 0, ',', ' '); ?></strong> | 
													Moyenne : <strong><?php echo number_format($avgJetons, 0, ',', ' '); ?></strong>
												</div>

												<div class="players-list">
													<!-- Formulaire : affecter le même nombre de jetons à tous les joueurs -->
													<form method="post" class="form-inline assign-jetons-form" style="max-width:900px;margin:10px auto;display:flex;gap:8px;align-items:center;">
														<div class="input-group">
															<input id="assign_all_jetons_input" type="number" name="assign_all_jetons" class="form-control" placeholder="Nombre de jetons" required min="0" style="width:180px;">
															<button type="submit" class="btn btn-primary">Affecter à tous</button>
															<button type="submit" name="assign_all_jetons_no_arrive" value="1" class="btn btn-warning">Affecter (sans bonus arrivée)</button>
															<button type="submit" name="undo_assign_jetons" value="1" class="btn btn-secondary" onclick="return confirmUndo();">Annuler dernier</button>
														</div>
														<span style="color:#ccc;font-size:0.9em;margin-left:8px;">(Met à jour `jetons` et recalcul `jetons_total`)</span>
													</form>
													<script>
													// Confirmation avant assignation globale
													document.querySelector('.assign-jetons-form').addEventListener('submit', function(e) {
														// If undo button triggered, skip assign confirmation
														if (document.activeElement && document.activeElement.name === 'undo_assign_jetons') return;
														var v = document.getElementById('assign_all_jetons_input').value;
														if (!v || parseInt(v,10) < 0) { e.preventDefault(); alert('Veuillez saisir un nombre de jetons valide.'); return; }
														// If the "without arrive" button triggered, show a variant message
														if (document.activeElement && document.activeElement.name === 'assign_all_jetons_no_arrive') {
															if (!confirm('Affecter ' + v + ' jetons à TOUS les participants en laissant le bonus arrivée inchangé ?')) { e.preventDefault(); }
														} else {
															if (!confirm('Affecter ' + v + ' jetons à TOUS les participants ? (les deux bonus seront réglés à 5000)')) { e.preventDefault(); }
														}
													});

													function confirmUndo() {
														return confirm('Annuler la dernière affectation de jetons pour cette activité ?');
													}
													</script>
													<table class="table table-condensed">
														<thead>
															<tr>
																<th>#</th>
																<th>Pseudo</th>
																<th>Bonus Ins</th>
																<th>Bonus Arrivée</th>
																<th>Statut</th>
																<th>Table</th>
																<th>Siège</th>
																<?php if ($mode === 'sequential'): ?>
																	<th>Arrivée</th>
																	<th></th>
																<?php endif; ?>
																<th>A Payé</th>
															</tr>
														</thead>
														<tbody>
															<?php foreach ($flatSeating as $player): ?>
																<?php $pid = (int)$player['id-participation']; ?>
																<tr data-participation-id="<?php echo $pid; ?>">
																	<td><?php echo $player['global_rank']; ?></td>
																	<td>
																		<?php echo htmlspecialchars($player['pseudo']); ?>
																		<?php 
																			$jetonsToShow = 0;
																			if (isset($activityType)) {
																				if ($activityType == 1 && isset($player['jetons_total']) && (int)$player['jetons_total'] != 0) {
																					$jetonsToShow = (int)$player['jetons_total'];
																				} elseif ($activityType == 2 && isset($player['jetons_1']) && (int)$player['jetons_1'] != 0) {
																					$jetonsToShow = (int)$player['jetons_1'];
																				} elseif ($activityType == 3 && isset($player['jetons_2']) && (int)$player['jetons_2'] != 0) {
																					$jetonsToShow = (int)$player['jetons_2'];
																				}
																			}
																			if ($jetonsToShow > 0) {
																				echo '<div style="font-size: 0.9em; color: #666; margin-top: 2px;">' . number_format($jetonsToShow, 0, ',', ' ') . '</div>';
																			}
																		?>
																	</td>
																	<td><?php echo isset($player['jetons_bonus_ins']) && (int)$player['jetons_bonus_ins'] > 0 ? number_format((int)$player['jetons_bonus_ins'], 0, ',', ' ') : '-'; ?></td>
																	<td><?php echo isset($player['jetons_bonus_arrivee']) && (int)$player['jetons_bonus_arrivee'] > 0 ? number_format((int)$player['jetons_bonus_arrivee'], 0, ',', ' ') : '-'; ?></td>
																	<td><?php echo htmlspecialchars($player['option']); ?></td>
																	<td class="col-table"><?php echo $player['table_no']; ?></td>
																	<td class="col-seat"><?php echo $player['seat_no']; ?></td>
																	<?php if ($mode === 'sequential'): ?>
																		<td>
																			<?php
																				$arrRank = isset($arrivalOrderMap[$pid]) ? (int)$arrivalOrderMap[$pid] : 0;
																				echo $arrRank > 0 ? $arrRank : '-';
																			?>
																		</td>
																		<td>
																			<?php if (!isset($arrivalOrderMap[$pid])): ?>
																				<a href="tables.php?id_activite=<?php echo $selectedActivityId; ?>&amp;mode=sequential&amp;equilibrage=<?php echo (int)$autoBalance; ?>&amp;arrival_pid=<?php echo $pid; ?>" class="btn btn-xs btn-primary">Arrivé</a>
																			<?php else: ?>
																				<span class="text-muted">OK</span>
																			<?php endif; ?>
																		</td>
																	<?php endif; ?>
																	<td>
																		<input type="radio"
																		       name="paid_<?php echo $pid; ?>"
																		       value="1"
																		       <?php echo (isset($paidStatus[$pid]) && $paidStatus[$pid]) ? 'checked' : ''; ?>
																		       onclick="window.location='tables.php?id_activite=<?php echo $selectedActivityId; ?>&mode=<?php echo htmlspecialchars($mode, ENT_QUOTES, 'UTF-8'); ?>&equilibrage=<?php echo (int)$autoBalance; ?>&paid_pid=<?php echo $pid; ?>';" />
																	</td>
																</tr>
															<?php endforeach; ?>
														</tbody>
													</table>
												</div>

												<?php if ($mode === 'sequential' && $selectedActivityId > 0): ?>
													<div class="alert alert-info" style="max-width:900px;margin:10px auto 0;">
														<strong>Mode séquentiel :</strong>
														Sélectionnez les joueurs dans leur ordre d'arrivée en cliquant sur le bouton «&nbsp;Arrivé&nbsp;» dans le tableau ci-dessus.
														<a href="tables.php?id_activite=<?php echo $selectedActivityId; ?>&amp;mode=sequential&amp;equilibrage=<?php echo (int)$autoBalance; ?>&amp;seq_reset=1" class="btn btn-default btn-sm" style="margin-left:8px;">Réinitialiser l'ordre d'arrivée</a>
														<a href="tables.php?id_activite=<?php echo $selectedActivityId; ?>&amp;mode=sequential&amp;equilibrage=<?php echo (int)$autoBalance; ?>&amp;seq_undo=1" class="btn btn-default btn-sm" style="margin-left:8px;">Annuler dernière arrivée</a>
														<a href="tables.php?id_activite=<?php echo $selectedActivityId; ?>&amp;mode=sequential&amp;equilibrage=<?php echo (int)$autoBalance; ?>&amp;release_all_seats=1" class="btn btn-default btn-sm" style="margin-left:8px;" onclick="return confirm('Êtes-vous sûr de vouloir libérer tous les sièges sauf l\'organisateur ?');">Libérer tous les sièges sauf l'organisateur</a>
													</div>
												<?php endif; ?>
											<?php endif; ?>
										<?php else: ?>
											<p>Veuillez sélectionner une activité pour afficher les joueurs sur les tables.</p>
										<?php endif; ?>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
        	<?php include('include/footer.php'); ?>
		<?php include('include/setting.php'); ?>
	</div>

<!-- Scripts communs (comme dashboard.php) -->
<script src="vendor/jquery/jquery.min.js"></script>
<script src="vendor/bootstrap/js/bootstrap.min.js"></script>
<script src="vendor/modernizr/modernizr.js"></script>
<script src="vendor/jquery-cookie/jquery.cookie.js"></script>
<script src="vendor/perfect-scrollbar/perfect-scrollbar.min.js"></script>
<script src="vendor/switchery/switchery.min.js"></script>
<script src="vendor/maskedinput/jquery.maskedinput.min.js"></script>
<script src="vendor/bootstrap-touchspin/jquery.bootstrap-touchspin.min.js"></script>
<script src="vendor/autosize/autosize.min.js"></script>
<script src="vendor/selectFx/classie.js"></script>
<script src="vendor/selectFx/selectFx.js"></script>
<script src="vendor/select2/select2.min.js"></script>
<script src="vendor/bootstrap-datepicker/bootstrap-datepicker.min.js"></script>
<script src="vendor/bootstrap-timepicker/bootstrap-timepicker.min.js"></script>
<script src="assets/js/main.js"></script>
<script src="assets/js/card-bg.js"></script>
<script src="assets/js/form-elements.js"></script>

<script>
    jQuery(document).ready(function () {
		Main.init();
		FormElements.init();

		// Initialisation du fond en pattern (même rendu que dashboard.php)
		if (window.CardBackground) {
			window.CardBackground.init({
				spacing: 60,
				rowHeight: 80,
				fontSize: 60,
				opacity: 0.18,
				alternateColors: true,
				colors: { even: 'white', odd: 'red' },
				suits: ['♠','♣','♥','♦'],
				staggerCycle: 4
			});
		}
	});
</script>

<script>
$(function() {
	// Mode de placement et option d'équilibrage transmis par le serveur
	const placementMode   = '<?php echo $mode; ?>';
	const autoBalanceFlag = <?php echo (int)$autoBalance; ?>;

	// États utilisés pour les interactions (sélection, drag & drop, tactile)
	let selectedSeat    = null;
	let selectedRowTap  = null;
	let draggedSeat     = null;
	let draggedRow      = null;
	let touchDragSeat   = null;
	let seatDropHappened = false;

	let touchStartX = 0;
	let touchStartY = 0;
	let touchMoved = false;
	const TOUCH_DRAG_THRESHOLD = 10; // px

	// Drag tactile depuis une ligne du tableau récapitulatif
	let rowTouchDrag = null;
	let rowTouchStartX = 0;
	let rowTouchStartY = 0;
	let rowTouchMoved = false;
	const ROW_TOUCH_DRAG_THRESHOLD = 10; // px

	function clearAutoSuggestions() {
		$('.seat').removeClass('suggest-from suggest-to');
	}

	function clearSeatSelection() {
		if (selectedSeat) {
			selectedSeat.classList.remove('seat-selected');
			selectedSeat = null;
		}
	}

	function clearRowSelection() {
		if (selectedRowTap) {
			selectedRowTap.classList.remove('row-selected');
			selectedRowTap = null;
		}
	}

	// Bouton de reset pour annuler le message de suggestion d'équilibrage
	$('#reset-auto-suggestion').on('click', function() {
		$('#auto-suggestion-text').text('');
		$('#auto-suggestion-alert').hide();
		clearAutoSuggestions();
	});

	// Affectation d'une ligne du tableau à un siège (réutilisé par drag & drop et tap)
	function assignRowToSeat(row, toSeat) {
		if (placementMode !== 'manual') return;
		const pid = row.dataset.participationId;
		if (!pid) return;
		const toPid = toSeat.dataset.participationId || '';
		if (toPid) return; // on ne dépose que sur un siège vide

		const pseudo = $(row).find('td').eq(1).text();

		// Un joueur ne peut pas être sur plusieurs tables : vider les autres sièges
		$('.seat[data-participation-id="' + pid + '"]').each(function() {
			const s = this;
			if (s === toSeat) return;
			s.dataset.participationId = '';
			$(s).find('.seat-name').text('Libre');
			$(s).find('.seat-rank').text('Libre');
			s.classList.add('empty');
			s.removeAttribute('draggable');
			$.post('update_seat.php', {
				id_participation: pid,
				table_no: 0,
				seat_no: 0
			});
		});

		// Affectation du joueur sur ce siège
		toSeat.dataset.participationId = pid;
		$(toSeat).find('.seat-name').text(pseudo || '');
		$(toSeat).find('.seat-rank').text('');
		toSeat.classList.remove('empty');
		toSeat.setAttribute('draggable', 'true');

		updateSeatOnServer(toSeat);
	}

	// Échange de deux sièges (réutilisé par drag & drop et tap)
	function swapSeats(fromSeat, toSeat) {
		if (fromSeat === toSeat) return;
		const toPid  = toSeat.dataset.participationId || '';
		const fromPid = fromSeat.dataset.participationId || '';
		const fromName  = $(fromSeat).find('.seat-name').text();
		const toName    = $(toSeat).find('.seat-name').text();

		fromSeat.dataset.participationId = toPid;
		toSeat.dataset.participationId   = fromPid;

		$(fromSeat).find('.seat-name').text(toPid ? toName : 'Libre');
		$(toSeat).find('.seat-name').text(fromPid ? fromName : 'Libre');
		$(fromSeat).find('.seat-rank').text(toPid ? '' : 'Libre');
		$(toSeat).find('.seat-rank').text(fromPid ? '' : 'Libre');

		if (fromSeat.dataset.participationId) {
			fromSeat.classList.remove('empty');
			fromSeat.setAttribute('draggable', 'true');
		} else {
			fromSeat.classList.add('empty');
			fromSeat.removeAttribute('draggable');
		}

		if (toSeat.dataset.participationId) {
			toSeat.classList.remove('empty');
			toSeat.setAttribute('draggable', 'true');
		} else {
			toSeat.classList.add('empty');
			toSeat.removeAttribute('draggable');
		}

		updateSeatOnServer(fromSeat);
		updateSeatOnServer(toSeat);
	}

	function removeSeatAndUpdate(seat) {
		const pid = seat.dataset.participationId;
		if (!pid) return;
		seat.dataset.participationId = '';
		$(seat).find('.seat-name').text('Libre');
		$(seat).find('.seat-rank').text('Libre');
		seat.classList.add('empty');
		seat.removeAttribute('draggable');
		$.post('update_seat.php', {
			id_participation: pid,
			table_no: 0,
			seat_no: 0
		}).done(function() {
			const row = document.querySelector('.players-list tr[data-participation-id="' + pid + '"]');
			if (row) {
				const tdTable = row.querySelector('.col-table');
				const tdSeat  = row.querySelector('.col-seat');
				if (tdTable) tdTable.textContent = '-';
				if (tdSeat)  tdSeat.textContent  = '-';
			}
			// En mode auto avec équilibrage actif, on recharge pour recalculer la répartition
			if (placementMode === 'auto' && autoBalanceFlag === 1) {
				window.location.reload();
			}
		});
	}

	function eliminateSeatPermanently(seat) {
		const pid = seat.dataset.participationId;
		if (!pid) return;
		seat.dataset.participationId = '';
		$(seat).find('.seat-name').text('Libre');
		$(seat).find('.seat-rank').text('Libre');
		seat.classList.add('empty');
		seat.removeAttribute('draggable');
		$.post('eliminate_player.php', {
			id_participation: pid
		}).done(function() {
			const row = document.querySelector('.players-list tr[data-participation-id="' + pid + '"]');
			if (row) {
				const tdStatus = row.querySelector('td:nth-child(3)');
				const tdTable  = row.querySelector('.col-table');
				const tdSeat   = row.querySelector('.col-seat');
				if (tdStatus) tdStatus.textContent = 'Elimine';
				if (tdTable)  tdTable.textContent  = '-';
				if (tdSeat)   tdSeat.textContent   = '-';
				row.classList.add('row-selected');
			}
			// En mode auto avec équilibrage actif, on recharge pour rééquilibrer les tables
			if (placementMode === 'auto' && autoBalanceFlag === 1) {
				window.location.reload();
			}
		});
	}

	// Gestion du drag & drop sur les sièges
	$('.seat').on('dragstart', function(e) {
		const seat = this;
		const pid = seat.dataset.participationId;
		if (!pid) {
			// Pas de joueur sur ce siège, on ne permet pas le drag
			e.preventDefault();
			return;
		}
		draggedSeat = seat;
		seatDropHappened = false;
		if (e.originalEvent && e.originalEvent.dataTransfer) {
			e.originalEvent.dataTransfer.effectAllowed = 'move';
		}
	});

	$('.seat').on('dragover', function(e) {
		if (!draggedSeat && !draggedRow) return;
		if (draggedSeat && draggedSeat === this) return;
		e.preventDefault();
		if (e.originalEvent && e.originalEvent.dataTransfer) {
			e.originalEvent.dataTransfer.dropEffect = 'move';
		}
	});

	$('.seat').on('drop', function(e) {
		e.preventDefault();
		const target = this;
		if (!draggedSeat && !draggedRow) return;
		seatDropHappened = true;

		const toSeat = target;
		const toPid  = toSeat.dataset.participationId || '';

		// Cas 1 : on vient d'une ligne du tableau (mode manuel uniquement)
		if (draggedRow) {
			assignRowToSeat(draggedRow, toSeat);
		} else if (draggedSeat) {
			// Cas 2 : échange entre deux sièges
			swapSeats(draggedSeat, toSeat);
		}
	});

	// Drop sur le centre de table : élimination définitive du joueur de cette table
	$('.poker-table-center').on('dragover', function(e) {
		if (!draggedSeat) return;
		e.preventDefault();
		if (e.originalEvent && e.originalEvent.dataTransfer) {
			e.originalEvent.dataTransfer.dropEffect = 'move';
		}
	});

	$('.poker-table-center').on('drop', function(e) {
		e.preventDefault();
		if (!draggedSeat) return;
		const center = this;
		const tableCenter = center.dataset.table;
		const fromSeat = draggedSeat;
		const fromPid  = fromSeat.dataset.participationId || '';
		if (!fromPid) return;
		// On ne valide l'élimination que si le joueur est lâché au centre de SA table
		if (tableCenter && fromSeat.dataset.table === tableCenter) {
			eliminateSeatPermanently(fromSeat);
		}
	});

	$('.seat').on('dragend', function() {
		// Si un joueur est déplacé hors de tout siège (pas de drop sur une autre case),
		// cela signifie qu'il n'est plus présent pour l'instant : on libère son siège
		if (draggedSeat && !seatDropHappened) {
			const seat = draggedSeat;
			const pid  = seat.dataset.participationId;
			if (pid) {
				// Libère visuellement le siège
				seat.dataset.participationId = '';
				$(seat).find('.seat-name').text('Libre');
				$(seat).find('.seat-rank').text('Libre');
				seat.classList.add('empty');
				seat.removeAttribute('draggable');
				// Met à jour la base : joueur temporairement non présent (id-table/id-siege à 0)
				$.post('update_seat.php', {
					id_participation: pid,
					table_no: 0,
					seat_no: 0
				}).done(function() {
					const row = document.querySelector('.players-list tr[data-participation-id="' + pid + '"]');
					if (row) {
						const tdTable = row.querySelector('.col-table');
						const tdSeat  = row.querySelector('.col-seat');
						if (tdTable) tdTable.textContent = '-';
						if (tdSeat)  tdSeat.textContent  = '-';
					}
					// En mode auto avec équilibrage actif, on recharge pour recalculer la répartition
					if (placementMode === 'auto' && autoBalanceFlag === 1) {
						window.location.reload();
					}
				});
			}
		}
		draggedSeat = null;
		draggedRow  = null;
		seatDropHappened = false;
	});

	// Interaction tactile / clic : sélection de lignes du tableau en mode manuel
	if (placementMode === 'manual') {
		$('.players-list tbody').on('click', 'tr', function(e) {
			const row = this;
			const pid = row.dataset.participationId;
			if (!pid) return;
			if (selectedRowTap === row) {
				row.classList.remove('row-selected');
				selectedRowTap = null;
			} else {
				clearRowSelection();
				row.classList.add('row-selected');
				selectedRowTap = row;
			}
		});

		// Drag tactile d'un joueur depuis le tableau vers un siège
		$('.players-list tbody').on('touchstart', 'tr', function(e) {
			if (e.touches.length !== 1) return; // ignore multi-touch
			const row = this;
			const pid = row.dataset.participationId;
			if (!pid) return;
			rowTouchDrag = row;
			rowTouchMoved = false;
			const t = e.touches[0];
			rowTouchStartX = t.clientX;
			rowTouchStartY = t.clientY;
		});
	}

	// Interaction tactile / clic : sélection et échange de sièges
	$('.seat').on('click', function(e) {
		// Si un drag & drop est en cours, on ignore le clic
		if (draggedSeat || draggedRow) return;
		const seat = this;
		const pid  = seat.dataset.participationId || '';

		// Si une ligne du tableau est sélectionnée, le clic sur un siège vide l'y place
		if (selectedRowTap && placementMode === 'manual') {
			if (!pid) {
				assignRowToSeat(selectedRowTap, seat);
				clearRowSelection();
			}
			return;
		}

		// Gestion sélection / échange / retrait de sièges
		if (pid) {
			// Siège occupé
			if (!selectedSeat) {
				selectedSeat = seat;
				seat.classList.add('seat-selected');
			} else if (selectedSeat === seat) {
				// Deuxième clic sur le même siège : retirer le joueur
				removeSeatAndUpdate(seat);
				clearSeatSelection();
			} else {
				// Échange avec un autre siège
				swapSeats(selectedSeat, seat);
				clearSeatSelection();
			}
		} else {
			// Siège vide
			if (selectedSeat) {
				// Déplacement du joueur sélectionné vers ce siège vide
				swapSeats(selectedSeat, seat);
				clearSeatSelection();
			}
		}
	});

	// Drag depuis le tableau récapitulatif (uniquement en mode manuel)
	if (placementMode === 'manual') {
		$('.players-list tbody tr').attr('draggable', 'true');
		$('.players-list tbody').on('dragstart', 'tr', function(e) {
			const row = this;
			const pid = row.dataset.participationId;
			if (!pid) {
				e.preventDefault();
				return;
			}
			draggedRow = row;
			if (e.originalEvent && e.originalEvent.dataTransfer) {
				e.originalEvent.dataTransfer.effectAllowed = 'move';
			}
		});
	}

	// Drag tactile entre sièges (smartphone / tablette)
	$('.seat').on('touchstart', function(e) {
		if (e.touches.length !== 1) return; // ignorer les gestures multi-doigts
		const seat = this;
		const pid  = seat.dataset.participationId || '';
		if (!pid) return; // pas de drag tactile depuis un siège vide
		touchDragSeat = seat;
		touchMoved = false;
		const t = e.touches[0];
		touchStartX = t.clientX;
		touchStartY = t.clientY;
	});

	// Suivi du mouvement tactile globalement (même si le doigt sort du siège d'origine)
	$(document).on('touchmove', function(e) {
		if (!touchDragSeat) return;
		if (!e.touches || e.touches.length === 0) return;
		const t = e.touches[0];
		const dx = t.clientX - touchStartX;
		const dy = t.clientY - touchStartY;
		if (Math.abs(dx) > TOUCH_DRAG_THRESHOLD || Math.abs(dy) > TOUCH_DRAG_THRESHOLD) {
			touchMoved = true;
			// Empêche le scroll pendant un drag joueur
			e.preventDefault();
		}
	});

	$(document).on('touchend', function(e) {
		if (!touchDragSeat) return;
		const originSeat = touchDragSeat;
		touchDragSeat = null;
		const touch = (e.changedTouches && e.changedTouches[0]) ? e.changedTouches[0] : null;
		touchMoved = false;
		if (!touch) return;

		const targetEl = document.elementFromPoint(touch.clientX, touch.clientY);
		if (!targetEl) {
			// Relâché en dehors : le joueur quitte temporairement la table
			removeSeatAndUpdate(originSeat);
			return;
		}

		// Si on relâche sur le centre de la même table -> élimination définitive
		const center = $(targetEl).closest('.poker-table-center')[0];
		if (center && center.dataset.table === originSeat.dataset.table) {
			eliminateSeatPermanently(originSeat);
			return;
		}

		const targetSeat = $(targetEl).closest('.seat')[0];
		if (!targetSeat) {
			// Pas de siège ni centre en dessous : le joueur est retiré temporairement
			removeSeatAndUpdate(originSeat);
			return;
		}

		// Drag tactile entre deux sièges : même logique que swapSeats
		swapSeats(originSeat, targetSeat);
	});

	// Suivi du mouvement tactile pour un drag depuis le tableau
	$(document).on('touchmove', function(e) {
		if (!rowTouchDrag) return;
		if (!e.touches || e.touches.length === 0) return;
		const t = e.touches[0];
		const dx = t.clientX - rowTouchStartX;
		const dy = t.clientY - rowTouchStartY;
		if (Math.abs(dx) > ROW_TOUCH_DRAG_THRESHOLD || Math.abs(dy) > ROW_TOUCH_DRAG_THRESHOLD) {
			rowTouchMoved = true;
			// Empêche le scroll pendant un drag joueur depuis le tableau
			e.preventDefault();
		}
	});

	$(document).on('touchend', function(e) {
		if (!rowTouchDrag) return;
		const originRow = rowTouchDrag;
		const moved = rowTouchMoved;
		rowTouchDrag = null;
		rowTouchMoved = false;
		const touch = (e.changedTouches && e.changedTouches[0]) ? e.changedTouches[0] : null;
		if (!touch) return;

		// Si pas de mouvement significatif, on laisse le clic gérer la sélection
		if (!moved) return;

		const targetEl = document.elementFromPoint(touch.clientX, touch.clientY);
		if (!targetEl) return;
		const targetSeat = $(targetEl).closest('.seat')[0];
		if (targetSeat) {
			assignRowToSeat(originRow, targetSeat);
			clearRowSelection();
		}
	});

	function updateSeatOnServer(seat) {
		const pid = seat.dataset.participationId;
		if (!pid) return;
		const tableNo = seat.dataset.table;
		let   seatNo  = seat.dataset.seat;

		// En mode manuel, le numéro de siège logique est déterminé par l'ordre
		// d'affectation sur la table : le premier joueur posé devient siège 1,
		// le suivant siège 2, etc. Un déplacement au sein de la même table garde
		// le même numéro de siège.
		if (placementMode === 'manual') {
			const row = document.querySelector('.players-list tr[data-participation-id="' + pid + '"]');
			if (row) {
				const tdTable = row.querySelector('.col-table');
				const tdSeat  = row.querySelector('.col-seat');
				const currentTable = tdTable && tdTable.textContent.trim() !== '-' && tdTable.textContent.trim() !== ''
					? parseInt(tdTable.textContent.trim(), 10)
					: 0;
				const currentSeat  = tdSeat && tdSeat.textContent.trim() !== '-' && tdSeat.textContent.trim() !== ''
					? parseInt(tdSeat.textContent.trim(), 10)
					: 0;

				const targetTable = tableNo ? parseInt(tableNo, 10) : 0;

				if (targetTable > 0) {
					if (currentTable === targetTable && currentSeat > 0) {
						// Même table : on conserve le même numéro de siège logique
						seatNo = currentSeat;
					} else {
						// Nouvelle table ou première affectation : on prend le prochain numéro libre
						let maxSeat = 0;
						const rows = document.querySelectorAll('.players-list tbody tr');
						rows.forEach(function(r) {
							const t = r.querySelector('.col-table');
							const s = r.querySelector('.col-seat');
							if (!t || !s) return;
							const tVal = t.textContent.trim();
							const sVal = s.textContent.trim();
							if (tVal === String(targetTable) && sVal !== '-' && sVal !== '') {
								const sn = parseInt(sVal, 10);
								if (!isNaN(sn) && sn > maxSeat) maxSeat = sn;
							}
						});
						seatNo = maxSeat + 1;
					}
				}
			}
		}

		$.post('update_seat.php', {
			id_participation: pid,
			table_no: tableNo,
			seat_no: seatNo
		}).done(function() {
			// Mise à jour du tableau récapitulatif (table / siège)
			const row = document.querySelector('.players-list tr[data-participation-id="' + pid + '"]');
			if (row) {
				const tdTable = row.querySelector('.col-table');
				const tdSeat  = row.querySelector('.col-seat');
				if (tdTable) tdTable.textContent = tableNo;
				if (tdSeat)  tdSeat.textContent  = seatNo;
			}
			// Mise à jour du numéro de siège affiché au-dessus du pseudo (id-siege)
			const $seatRank = $(seat).find('.seat-rank');
			if (seatNo && parseInt(seatNo, 10) > 0) {
				$seatRank.text(parseInt(seatNo, 10));
			} else {
				$seatRank.text('Libre');
			}
		});
	}

	// Polling pour synchroniser les écrans de monitoring (actualiser automatiquement si un joueur a payé)
	(function monitoringPolling() {
		const selectedActivityId = <?php echo $selectedActivityId; ?>;
		if (!selectedActivityId || selectedActivityId <= 0) return; // Ne pas activer le polling s'il n'y a pas d'activité sélectionnée

		const pollInterval = 5000; // Vérifier toutes les 5 secondes
		let lastKnownPaymentHash = '<?php echo md5(json_encode(isset($paidStatus) ? $paidStatus : [])); ?>';

		function checkForPaymentChanges() {
			fetch('tables.php?id_activite=' + selectedActivityId + '&check_payments=1', {
				method: 'GET',
				headers: {
					'X-Requested-With': 'XMLHttpRequest'
				},
				cache: 'no-store'
			})
			.then(response => response.text())
			.then(data => {
				if (data !== lastKnownPaymentHash && data.length > 0) {
					lastKnownPaymentHash = data;
					// Un changement de paiement a été détecté, actualiser la page
					location.reload();
				}
			})
			.catch(error => console.warn('Erreur polling paiements:', error));
		}

		// Démarrer le polling
		setInterval(checkForPaymentChanges, pollInterval);
	})();
});
</script>
</body>
</html>
