<?php
// DEBUG: Affichage des erreurs PHP pour diagnostic temporaire
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
// S'assurer que les dates/mois utilisent le fuseau horaire France
if (function_exists('date_default_timezone_set')) {
    date_default_timezone_set('Europe/Paris');
}
// Debugging output removed for production
include('include/config.php');

// Check if user is logged in
if (strlen($_SESSION['id']) == 0) {
    header('location:logout.php');
    exit;
}


$id = intval($_GET['id']); // get value

// Définir si l'utilisateur courant est admin ou le membre lui-même
$is_admin = (isset($_SESSION['role']) && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'superadmin')) || (isset($_SESSION['id']) && intval($_SESSION['id']) === 265);
$is_self = (isset($_SESSION['id']) && intval($_SESSION['id']) === $id);


// Ajoutez cette fonction au début du fichier, après les includes
function updateMemberBalance($membre_id, $con) {
    try {
        // Calcul du solde : somme des entrées - somme des sorties
        $query = "SELECT 
            COALESCE(SUM(CASE WHEN id_type_mvt = 1 THEN montant ELSE 0 END), 0) -
            COALESCE(SUM(CASE WHEN id_type_mvt = 2 THEN montant ELSE 0 END), 0) as balance
            FROM portefeuille 
            WHERE id_mvt_membre = ?";
            
        $stmt = mysqli_prepare($con, $query);
        mysqli_stmt_bind_param($stmt, 'i', $membre_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        $balance = $row['balance'];
        
        // Mise à jour du solde dans la table membres
        $update = mysqli_prepare($con, "UPDATE membres SET solde = ? WHERE `id-membre` = ?");
        mysqli_stmt_bind_param($update, 'di', $balance, $membre_id);
        mysqli_stmt_execute($update);
        
        return true;
    } catch (Exception $e) {
        error_log("Erreur mise à jour solde: " . $e->getMessage());
        return false;
    }
}

// Fonction pour gérer la réservation (res_4)
function getReservationStatus($membre_id, $con) {
    $stmt = mysqli_prepare($con, "SELECT MAX(IF(res_4 = 1, 1, 0)) as reservation FROM reservation WHERE id_membre = ?");
    mysqli_stmt_bind_param($stmt, 'i', $membre_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    return $row['reservation'] ? 1 : 0;
}

function updateReservationStatus($membre_id, $reservation_status, $con) {
    try {
        if ($reservation_status == 1) {
            // Ajouter la réservation
            $stmt = mysqli_prepare($con, "INSERT IGNORE INTO reservation (id_membre, res_4) VALUES (?, 1) ON DUPLICATE KEY UPDATE res_4 = 1");
        } else {
            // Retirer la réservation
            $stmt = mysqli_prepare($con, "UPDATE reservation SET res_4 = 0 WHERE id_membre = ?");
        }
        mysqli_stmt_bind_param($stmt, 'i', $membre_id);
        return mysqli_stmt_execute($stmt);
    } catch (Exception $e) {
        error_log("Erreur mise à jour réservation: " . $e->getMessage());
        return false;
    }
}

$id = intval($_GET['id']); // get value

if (isset($_POST['submit']) ) {
    
    // Charger les valeurs actuelles
    $stmt = mysqli_prepare($con, "SELECT * FROM membres WHERE `id-membre` = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $current = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    // Pour chaque champ, prendre POST sinon valeur actuelle
    $fields = [
        'pseudo', 'email', 'telephone', 'fname', 'lname', 'posting_date', 'association_date', 'rue', 'ville', 'CodeV', 'verification', 'naissance_date',
        'def_com', 'def_str', 'def_nbj', 'def_buy', 'def_rak', 'def_bou', 'def_rec', 'def_jet', 'def_recave_jetons', 'def_recave_montant', 'def_bon', 'def_add', 'def_ant', 'def_cha'
    ];
    $values = [];
    foreach ($fields as $f) {
        if (isset($_POST[$f])) {
            $v = mysqli_real_escape_string($con, $_POST[$f]);
            // Pour les dates, si vide, mettre NULL
            if (in_array($f, ['posting_date', 'association_date', 'naissance_date']) && ($v === '' || strtolower($v) === 'null')) {
                $values[$f] = null;
            } else {
                $values[$f] = $v;
            }
        } else {
            $values[$f] = $current[$f];
        }
    }
    // Gestion du mot de passe
    if ($is_admin || $is_self) {
        // Si le champ password n'est pas dans POST, on garde l'ancien mot de passe
        if (!isset($_POST['password']) || $_POST['password'] === '') {
            $values['password'] = $current['password'];
        } else {
            $values['password'] = mysqli_real_escape_string($con, $_POST['password']);
        }
    }
    try {
        // Construction dynamique de la requête et des paramètres
        $update_fields = [
            'pseudo', 'email', 'telephone', 'fname', 'lname', 'posting_date', 'association_date', 'rue'
        ];
        if ($is_admin || $is_self) {
            $update_fields[] = 'password';
        }
        $update_fields = array_merge($update_fields, [
            'ville', 'CodeV', 'verification', 'naissance_date',
            'def_com', 'def_str', 'def_nbj', 'def_buy', 'def_rak', 'def_bou', 'def_rec', 'def_jet',
            'def_recave_jetons', 'def_recave_montant', 'def_bon', 'def_add', 'def_ant', 'def_cha'
        ]);

        $set_sql = [];
        foreach ($update_fields as $f) {
            $set_sql[] = "$f = ?";
        }
        $set_sql = implode(", ", $set_sql);
        $sql = "UPDATE `membres` SET $set_sql WHERE `id-membre` = ?";
        $stmt = mysqli_prepare($con, $sql);
        if (!$stmt) {
            error_log('ERROR: prepare failed in submit: ' . mysqli_error($con));
            $_SESSION['error'] = 'Erreur préparation SQL: ' . mysqli_error($con);
            header('Location: voir-membre.php?id=' . $id);
            exit();
        }
        // Construction dynamique des types et des paramètres
        $params = [];
        $types = '';
        foreach ($update_fields as $f) {
            // Déterminer le type pour chaque champ
            if (in_array($f, ['def_str', 'def_nbj', 'def_buy', 'def_rak', 'def_bou', 'def_rec', 'def_jet', 'def_recave_jetons', 'def_recave_montant', 'def_bon', 'def_add', 'def_ant', 'def_cha', 'verification'])) {
                $types .= 'i';
                $params[] = intval($values[$f]);
            } elseif (in_array($f, ['posting_date', 'association_date', 'naissance_date'])) {
                $types .= 's';
                $params[] = ($values[$f] === null ? null : $values[$f]);
            } else {
                $types .= 's';
                $params[] = $values[$f];
            }
        }
        $types .= 'i'; // pour l'id-membre à la fin
        $params[] = $id;

        $bind_ok = mysqli_stmt_bind_param($stmt, $types, ...$params);
        if (!$bind_ok) {
            error_log('ERROR: bind_param failed in submit: ' . mysqli_error($con));
            $_SESSION['error'] = 'Erreur bind_param: ' . mysqli_error($con);
            header('Location: voir-membre.php?id=' . $id);
            exit();
        }
        if (!mysqli_stmt_execute($stmt)) {
            error_log('ERROR: execute failed in submit: ' . mysqli_stmt_error($stmt));
            $_SESSION['error'] = 'Erreur execute: ' . mysqli_stmt_error($stmt);
            header('Location: voir-membre.php?id=' . $id);
            exit();
        }
        $affected = mysqli_stmt_affected_rows($stmt);
        $_SESSION['msg'] = ($affected > 0) ? "Mise à jour effectuée avec succès" : "Aucune modification effectuée";
        
        // Gérer la mise à jour de la réservation
        if (isset($_POST['reservation'])) {
            $reservation_status = intval($_POST['reservation']);
            updateReservationStatus($id, $reservation_status, $con);
        }
        
        if (headers_sent($file, $line)) { error_log("WARNING: headers already sent in $file:$line"); }
        header('Location: voir-membre.php?id=' . $id);
        exit();
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        error_log('Exception in submit: ' . $e->getMessage());
        header('Location: voir-membre.php?id=' . $id);
        exit();
    } finally {
        if (isset($stmt)) {
            mysqli_stmt_close($stmt);
        }
    }
}


if (isset($_POST['submito'])) {
    
    // Charger les valeurs actuelles
    $stmt = mysqli_prepare($con, "SELECT * FROM membres WHERE `id-membre` = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $current = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    // Champs de l'orga
    $fields = [
        'def_com', 'def_str', 'def_nbj', 'def_buy', 'def_rak', 'def_bou', 'def_rec', 'def_jet', 'def_recave_jetons', 'def_recave_montant', 'def_bon', 'def_add', 'def_ant', 'def_rdv', 'def_sta', 'def_cha'
    ];
    $values = [];
    foreach ($fields as $f) {
        $values[$f] = isset($_POST[$f]) ? mysqli_real_escape_string($con, $_POST[$f]) : $current[$f];
    }
    try {
        $stmt = mysqli_prepare($con, "UPDATE membres SET 
            def_com = ?,
            def_str = ?,
            def_nbj = ?,
            def_buy = ?, 
            def_rak = ?,
            def_bou = ?,
            def_rec = ?,
            def_jet = ?,
            def_recave_jetons = ?,
            def_recave_montant = ?,
            def_bon = ?,
            def_add = ?,
            def_ant = ?,
            def_rdv = ?,
            def_sta = ?,
            def_cha = ?
            WHERE `id-membre` = ?");
        if (!$stmt) {
            error_log('ERROR: prepare failed in submito: ' . mysqli_error($con));
            $_SESSION['error'] = 'Erreur préparation SQL: ' . mysqli_error($con);
            header('Location: voir-membre.php?id=' . $id);
            exit();
        }
        $bind_ok = mysqli_stmt_bind_param($stmt, 'siiiiiiiiiiiisssii', 
            $values['def_com'],
            $values['def_str'],
            $values['def_nbj'],
            $values['def_buy'],
            $values['def_rak'],
            $values['def_bou'],
            $values['def_rec'],
            $values['def_jet'],
            $values['def_recave_jetons'],
            $values['def_recave_montant'],
            $values['def_bon'],
            $values['def_add'],
            $values['def_ant'],
            $values['def_rdv'],
            $values['def_sta'],
            $values['def_com'],
            $values['def_cha'],
            $id);
        if (!$bind_ok) {
            error_log('ERROR: bind_param failed in submito: ' . mysqli_error($con));
            $_SESSION['error'] = 'Erreur bind_param: ' . mysqli_error($con);
            header('Location: voir-membre.php?id=' . $id);
            exit();
        }
        if (!mysqli_stmt_execute($stmt)) {
            error_log('ERROR: execute failed in submito: ' . mysqli_stmt_error($stmt));
            throw new Exception("Execute failed: " . mysqli_stmt_error($stmt));
        }
        $affected = mysqli_stmt_affected_rows($stmt);
        $_SESSION['msg'] = ($affected > 0) ? "Mise à jour effectuée avec succès" : "Aucune modification effectuée";
        // debug block removed for production
        header('Location: voir-membre.php?id=' . $id);
        exit();
    } catch (Exception $e) {
        error_log("Error updating organization data: " . $e->getMessage());
        $_SESSION['error'] = $e->getMessage();
        header('Location: voir-membre.php?id=' . $id);
        exit();
    } finally {
        if (isset($stmt)) {
            mysqli_stmt_close($stmt);
        }
    }
}

if (isset($_POST['submit_portefeuille'])) {
    try {
        // Validation des données
        if (!isset($_POST['id_type_mvt']) || !isset($_POST['montant'])) {
            throw new Exception("Type et montant sont obligatoires");
        }

        // Sanitization et validation
        $id_type_mvt = intval($_POST['id_type_mvt']);
        $montant = floatval($_POST['montant']);
        $date_mvt = !empty($_POST['date_mvt']) ? $_POST['date_mvt'] : date('Y-m-d');
        // Nouvelle ligne pour id_participation
        $id_participation = !empty($_POST['id_participation']) ? intval($_POST['id_participation']) : null;
        
        // Préparation de la requête INSERT
        $stmt = mysqli_prepare($con, "INSERT INTO portefeuille 
            (id_mvt_membre, date_mvt, montant, id_type_mvt, id_participation) 
            VALUES (?, ?, ?, ?, ?)");

        if (!$stmt) {
            throw new Exception("Erreur de préparation de la requête: " . mysqli_error($con));
        }

        // Bind des paramètres
        mysqli_stmt_bind_param($stmt, 'isdii', 
            $id,            // id du membre
            $date_mvt,      // date du mouvement
            $montant,       // montant
            $id_type_mvt,   // type (1=Entrée, 2=Sortie)
            $id_participation // ID participation (peut être null)
        );

        // Exécution
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Erreur lors de l'ajout de la transaction: " . mysqli_stmt_error($stmt));
        }

        // Mise à jour du solde
        if (!updateMemberBalance($id, $con)) {
            throw new Exception("Erreur lors de la mise à jour du solde");
        }

        $_SESSION['msg'] = "Transaction ajoutée avec succès";
        
        // Redirection
        header("Location: voir-membre.php?id=" . $id . "#portefeuilleE");
        exit();

    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    } finally {
        if (isset($stmt)) {
            mysqli_stmt_close($stmt);
        }
    }
}

if (isset($_POST['submitdup'])) {
    try {
        mysqli_begin_transaction($con);
        
        // Récupération des infos membre
        $stmt = mysqli_prepare($con, "SELECT * FROM `membres` WHERE `id-membre` = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $member = mysqli_stmt_get_result($stmt)->fetch_array();
        
        if (!$member) throw new Exception("Membre introuvable");

        // Valeurs par défaut
        $titre = $member['def_com'] ?? 'Nouvelle activité';
        $ville = $member['ville'] ?? '';
        $lng = floatval($member['longitude'] ?? 0);
        $lat = floatval($member['latitude'] ?? 0);
        $places = intval($member['def_nbj'] ?? 20);
        $nb_tables = 2;
        $buyin = intval($member['def_buy'] ?? 10);
        $rake = intval($member['def_rak'] ?? 5);
        $bounty = intval($member['def_bou'] ?? 0);
        $jetons = intval($member['def_jet'] ?? 35000);
        $recave = intval($member['def_rec'] ?? 1);
        $recave_montant = intval($member['def_recave_montant'] ?? 0);
        $recave_jetons = intval($member['def_recave_jetons'] ?? 0);
        $addon = intval($member['def_add'] ?? 0);
        $ante = strval($member['def_ant'] ?? '0');
        $bonus = intval($member['def_bon'] ?? 5000);
        $commentaire = $member['def_com'] ?? '';
        $id_challenge = intval($member['def_cha'] ?? 4);
        $id_structure = intval($member['def_str'] ?? 4);

        // Build INSERT dynamically and include recave columns only if they exist in `activite` table
        $cols_res = mysqli_query($con, "DESCRIBE `activite`");
        $existing_cols = [];
        while ($c = mysqli_fetch_assoc($cols_res)) {
            $existing_cols[] = $c['Field'];
        }

        $insert_cols = [
            'id_challenge', 'id_structure', 'id-membre', 'titre-activite', 'date_depart', 'heure_depart', 'ville',
            'lng', 'lat', 'places', 'nb-tables', 'buyin', 'rake', 'bounty', 'jetons', 'recave'
        ];

        // Add recave amount / jetons if present under either def_ or non-def_ names
        if (in_array('def_recave_montant', $existing_cols)) {
            $insert_cols[] = 'def_recave_montant';
            $recave_montant_field = 'def_recave_montant';
        } elseif (in_array('recave_montant', $existing_cols)) {
            $insert_cols[] = 'recave_montant';
            $recave_montant_field = 'recave_montant';
        } else {
            $recave_montant_field = null;
        }
        if (in_array('def_recave_jetons', $existing_cols)) {
            $insert_cols[] = 'def_recave_jetons';
            $recave_jetons_field = 'def_recave_jetons';
        } elseif (in_array('recave_jetons', $existing_cols)) {
            $insert_cols[] = 'recave_jetons';
            $recave_jetons_field = 'recave_jetons';
        } else {
            $recave_jetons_field = null;
        }

        $insert_cols = array_merge($insert_cols, ['addon', 'ante', 'bonus', 'commentaire']);

        $cols_sql = implode(', ', array_map(function($c){ return "`".$c."`"; }, $insert_cols));
        $placeholders = [];
        foreach ($insert_cols as $c) {
            if ($c === 'date_depart' || $c === 'heure_depart') {
                $placeholders[] = 'NOW()';
            } else {
                $placeholders[] = '?';
            }
        }
        $placeholders_sql = implode(', ', $placeholders);

        $query = "INSERT INTO `activite` ($cols_sql) VALUES ($placeholders_sql)";
        $stmt = mysqli_prepare($con, $query);
        if (!$stmt) throw new Exception("Erreur préparation SQL: " . mysqli_error($con));

        // Build types string and values array dynamically
        $types = '';
        $values_for_bind = [];
        foreach ($insert_cols as $c) {
            // Skip columns that are inserted as NOW() (no placeholder, no bind)
            if ($c === 'date_depart' || $c === 'heure_depart') {
                continue;
            }

            switch ($c) {
                case 'id_challenge': case 'id_structure': case 'id-membre': case 'places': case 'nb-tables': case 'buyin': case 'rake': case 'bounty': case 'jetons': case 'recave': case 'def_recave_montant': case 'recave_montant': case 'def_recave_jetons': case 'recave_jetons': case 'addon': case 'bonus':
                    $types .= 'i';
                    break;
                case 'lng': case 'lat':
                    $types .= 'd';
                    break;
                default:
                    $types .= 's';
            }

            // Map column to variable
            if ($c === 'id_challenge') $values_for_bind[] = $id_challenge;
            elseif ($c === 'id_structure') $values_for_bind[] = $id_structure;
            elseif ($c === 'id-membre') $values_for_bind[] = $id;
            elseif ($c === 'titre-activite') $values_for_bind[] = $titre;
            elseif ($c === 'ville') $values_for_bind[] = $ville;
            elseif ($c === 'lng') $values_for_bind[] = $lng;
            elseif ($c === 'lat') $values_for_bind[] = $lat;
            elseif ($c === 'places') $values_for_bind[] = $places;
            elseif ($c === 'nb-tables') $values_for_bind[] = $nb_tables;
            elseif ($c === 'buyin') $values_for_bind[] = $buyin;
            elseif ($c === 'rake') $values_for_bind[] = $rake;
            elseif ($c === 'bounty') $values_for_bind[] = $bounty;
            elseif ($c === 'jetons') $values_for_bind[] = $jetons;
            elseif ($c === 'recave') $values_for_bind[] = $recave;
            elseif ($c === 'def_recave_montant' || $c === 'recave_montant') $values_for_bind[] = $recave_montant;
            elseif ($c === 'def_recave_jetons' || $c === 'recave_jetons') $values_for_bind[] = $recave_jetons;
            elseif ($c === 'addon') $values_for_bind[] = $addon;
            elseif ($c === 'ante') $values_for_bind[] = $ante;
            elseif ($c === 'bonus') $values_for_bind[] = $bonus;
            elseif ($c === 'commentaire') $values_for_bind[] = $commentaire;
            else $values_for_bind[] = null; // fallback
        }

        // Prepare parameters for bind_param (need references)
        $bind_params = array_merge([$types], $values_for_bind);
        $refs = [];
        foreach ($bind_params as $key => $value) {
            $refs[$key] = &$bind_params[$key];
        }
        try {
            if (!call_user_func_array(array($stmt, 'bind_param'), $refs)) {
                throw new Exception('Erreur bind_param: ' . mysqli_stmt_error($stmt));
            }
        } catch (ArgumentCountError $e) {
            throw new Exception('bind_param ArgumentCountError: mismatch between placeholders and variables');
        } catch (Throwable $e) {
            throw new Exception('bind_param failed');
        }

        if (!mysqli_stmt_execute($stmt)) throw new Exception("Erreur exécution SQL: " . mysqli_stmt_error($stmt));

        $new_id = mysqli_insert_id($con);

        // --- Création automatique du groupe de chat ---
        $months = ["", "Janvier", "Février", "Mars", "Avril", "Mai", "Juin", "Juillet", "Août", "Septembre", "Octobre", "Novembre", "Décembre"];
        $formatted_date = date('j') . ' ' . $months[intval(date('n'))];
        $organizer_name = $member['pseudo'];
        $new_group_name = $formatted_date . " " . $organizer_name;
        $creator_id = $_SESSION['id'];
        
        // 1. Récupérer le dernier groupe pour copier les membres
        $res_last_grp = mysqli_query($con, "SELECT id FROM chat_groups ORDER BY id DESC LIMIT 1");
        if ($res_last_grp && mysqli_num_rows($res_last_grp) > 0) {
            $row_last_grp = mysqli_fetch_assoc($res_last_grp);
            $last_group_id = $row_last_grp['id'];
            
            // 2. Créer le nouveau groupe
            $stmt_grp = mysqli_prepare($con, "INSERT INTO chat_groups (name, created_by) VALUES (?, ?)");
            mysqli_stmt_bind_param($stmt_grp, "si", $new_group_name, $creator_id);
            
            if (mysqli_stmt_execute($stmt_grp)) {
                $new_group_id = mysqli_insert_id($con);
                mysqli_stmt_close($stmt_grp);
                
                // 3. Copier les membres du dernier groupe
                $res_members = mysqli_query($con, "SELECT member_id FROM chat_group_members WHERE group_id = $last_group_id");
                while ($member_row = mysqli_fetch_assoc($res_members)) {
                    $m_id = $member_row['member_id'];
                    mysqli_query($con, "INSERT IGNORE INTO chat_group_members (group_id, member_id) VALUES ($new_group_id, $m_id)");
                }
                
                // S'assurer que le créateur est aussi dans le groupe s'il n'y était pas
                mysqli_query($con, "INSERT IGNORE INTO chat_group_members (group_id, member_id) VALUES ($new_group_id, $creator_id)");
            }
        }
        // --- Fin création groupe ---

        // Création participation
        // Modification : Ajout de 'nom-membre' récupéré depuis $member['pseudo']
        $nom_membre = $member['pseudo'];
        
        $stmt = mysqli_prepare($con, "INSERT INTO `participation` (`id-membre`, `id-activite`, `id-siege`, `id-table`, `nom-membre`, `option`, `ordre`, `valide`) VALUES (?, ?, 1, 1, ?, 'Inscrit', 1, 'Actif')");
        
        // Binding : 'iis' (integer, integer, string)
        mysqli_stmt_bind_param($stmt, 'iis', $id, $new_id, $nom_membre);
        
        mysqli_stmt_execute($stmt);

        // Création blindes
        $stmt = mysqli_prepare($con, "INSERT INTO `blindes-live` (`id-activite`, `ordre`, `nom`, `minutes`, `fin`, `ante`) VALUES (?, 1, 'Pause', 5, DATE_ADD(NOW(), INTERVAL 1 YEAR), 0)");
        mysqli_stmt_bind_param($stmt, 'i', $new_id);
        mysqli_stmt_execute($stmt);

        mysqli_commit($con);
        
        // Redirection JS pour éviter les erreurs de header
        echo "<script>window.location.href='voir-activite.php?uid=" . $new_id . "';</script>";
        exit();

    } catch (Exception $e) {
        mysqli_rollback($con);
        echo "<script>alert('Erreur lors de la création : " . addslashes($e->getMessage()) . "');</script>";
    }
}

if (isset($_POST['submit2'])) {
    $compet = $_POST['compet'];
    $sql2 = mysqli_query($con, "INSERT INTO `competences-individu` (`id-indiv`, `id-comp`) VALUES ('$id', '$compet')");
    $_SESSION['msg'] = "Competence added successfully !!";
}

if (isset($_POST['submit3'])) {
    $lois = $_POST['lois'];
    $sql2 = mysqli_query($con, "INSERT INTO `loisirs-individu` (`id-indiv`, `id-lois`) VALUES ('$id', '$lois')");
    $_SESSION['msg'] = "Loisir added successfully !!";
}

// Handle photo upload
if (isset($_FILES['fileToUpload']) && isset($_POST['id'])) {
    $id = intval($_POST['id']);
    // Use relative path to images directory
    $target_dir = '../images/faces/';
    
    // Ensure directory exists and is writable
    if (!file_exists($target_dir)) {
        if (!mkdir($target_dir, 0755, true)) {
            $_SESSION['error'] = "Impossible de créer le répertoire de destination: " . $target_dir;
            header("Location: voir-membre.php?id=".$id);
            exit();
        }
    } elseif (!is_writable($target_dir)) {
        $_SESSION['error'] = "Le répertoire de destination n'est pas accessible en écriture: " . $target_dir;
        header("Location: voir-membre.php?id=".$id);
        exit();
    }
    
    // Debug - log target directory and full path
    error_log("Uploading to directory: " . $target_dir);
    error_log("Full path: " . realpath($target_dir));
    error_log("Is writable: " . (is_writable($target_dir) ? 'Yes' : 'No'));
    error_log("File info: " . print_r($_FILES["fileToUpload"], true));

    $uploadOk = 1;
    $imageFileType = strtolower(pathinfo($_FILES["fileToUpload"]["name"], PATHINFO_EXTENSION));

    // Check if image file is a actual image or fake image
    $check = getimagesize($_FILES["fileToUpload"]["tmp_name"]);
    if ($check === false) {
        $_SESSION['error'] = "Le fichier n'est pas une image valide.";
        $uploadOk = 0;
    }

    // Check file size (limit to 2MB)
    if ($_FILES["fileToUpload"]["size"] > 2000000) {
        $_SESSION['error'] = "Le fichier est trop volumineux (max 2MB).";
        $uploadOk = 0;
    }

    // Allow certain file formats
    if(!in_array($imageFileType, ['jpg', 'png', 'jpeg', 'gif'])) {
        $_SESSION['error'] = "Seuls les fichiers JPG, JPEG, PNG et GIF sont autorisés.";
        $uploadOk = 0;
    }

    // Generate unique filename to prevent overwriting
    $new_filename = 'profile_' . $id . '_' . time() . '.' . $imageFileType;
    $target_file = $target_dir . $new_filename;

    if ($uploadOk) {
        // First get old photo filename to delete it later
        $stmt = mysqli_prepare($con, "SELECT photo FROM membres WHERE `id-membre` = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $old_photo);
        mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);

        error_log("Attempting to move file from: " . $_FILES["fileToUpload"]["tmp_name"] . " to: " . $target_file);
        if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $target_file)) {
            error_log("File moved successfully");
            // Delete old photo if it exists
            if (!empty($old_photo) && file_exists($target_dir . $old_photo)) {
                @unlink($target_dir . $old_photo);
            }
            // Update database
            $stmt = mysqli_prepare($con, "UPDATE membres SET photo = ? WHERE `id-membre` = ?");
            mysqli_stmt_bind_param($stmt, 'si', $new_filename, $id);
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['msg'] = "Photo de profil mise à jour avec succès.";
                // Refresh page to show new photo
                header("Location: voir-membre.php?id=".$id);
                exit();
            } else {
                $_SESSION['error'] = "Erreur lors de la mise à jour de la base de données.";
            }
            mysqli_stmt_close($stmt);
        } else {
            $_SESSION['error'] = "Erreur lors du téléchargement du fichier.";
        }
    }
}

if (isset($_POST['submit4']) && $is_admin) {
    $col = $_POST['col'];
    $sql2 = mysqli_query($con, "INSERT INTO `collections-individu` (`id-indiv`, `id_col`, `aff_rake`) VALUES ('$id', '$col', 0)");
    $_SESSION['msg'] = "Collection added successfully !!";
}

// Traiter la sauvegarde des valeurs Aff_Rake
if (isset($_POST['save_aff_rake'])) {
    try {
        // Récupérer tous les IDs de collections-individu pour cet utilisateur
        $sql_all = mysqli_query($con, "SELECT `id`, `aff_rake`, `id_col`, `date` FROM `collections-individu` WHERE `id-indiv` = '$id'");
        if (!$sql_all) {
            throw new Exception("Erreur requête SQL: " . mysqli_error($con));
        }
        
        $checked_ids = isset($_POST['aff_rake']) && is_array($_POST['aff_rake']) ? array_map('intval', $_POST['aff_rake']) : array();
        
        // Parcourir toutes les collections et mettre à jour leur statut
        while ($all_row = mysqli_fetch_array($sql_all)) {
            $col_id = $all_row['id'];
            $old_value = intval($all_row['aff_rake']);
            $new_value = in_array($col_id, $checked_ids) ? 1 : 0;
            
            // Si la valeur passe de 0 à 1, ajouter un enregistrement portefeuille
            if ($old_value == 0 && $new_value == 1) {
                // Récupérer la valeur de la collection
                $id_col = $all_row['id_col'];
                $sql_col = mysqli_query($con, "SELECT `valeur` FROM `collections` WHERE `id_collection` = '$id_col'");
                if ($sql_col && mysqli_num_rows($sql_col) > 0) {
                    $col_row = mysqli_fetch_array($sql_col);
                    $montant = $col_row['valeur'];
                    
                    // Ajouter l'enregistrement portefeuille (id_type_mvt = 6 = Crédit Tombola)
                    $date_mvt = $all_row['date'];
                    $insert_portefeuille = mysqli_query($con, "INSERT INTO `portefeuille` (`id_mvt_membre`, `id_type_mvt`, `montant`, `date_mvt`) VALUES ('$id', 6, '$montant', '$date_mvt')");
                    if (!$insert_portefeuille) {
                        throw new Exception("Erreur insertion portefeuille: " . mysqli_error($con));
                    }
                }
            }
            // Si la valeur passe de 1 à 0, supprimer l'enregistrement portefeuille correspondant
            else if ($old_value == 1 && $new_value == 0) {
                // Récupérer la valeur de la collection
                $id_col = $all_row['id_col'];
                $sql_col = mysqli_query($con, "SELECT `valeur` FROM `collections` WHERE `id_collection` = '$id_col'");
                if ($sql_col && mysqli_num_rows($sql_col) > 0) {
                    $col_row = mysqli_fetch_array($sql_col);
                    $montant = $col_row['valeur'];
                    
                    // Supprimer l'enregistrement portefeuille correspondant (Crédit Tombola avec même montant et date)
                    $date_mvt = $all_row['date'];
                    $delete_portefeuille = mysqli_query($con, "DELETE FROM `portefeuille` WHERE `id_mvt_membre` = '$id' AND `id_type_mvt` = 6 AND `montant` = '$montant' AND `date_mvt` = '$date_mvt'");
                    if (!$delete_portefeuille) {
                        throw new Exception("Erreur suppression portefeuille: " . mysqli_error($con));
                    }
                }
            }
            
            // Mettre à jour le statut aff_rake
            $update_result = mysqli_query($con, "UPDATE `collections-individu` SET `aff_rake` = $new_value WHERE `id` = '$col_id' AND `id-indiv` = '$id'");
            if (!$update_result) {
                throw new Exception("Erreur mise à jour: " . mysqli_error($con));
            }
        }
        $_SESSION['msg'] = "Paramètres Aff_Rake sauvegardés avec succès";
    } catch (Exception $e) {
        $_SESSION['error'] = "Erreur lors de la sauvegarde: " . $e->getMessage();
    }
}


if (isset($_POST['submitnotif'])) {
    try {
        // Sanitize and validate input
        $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
        $sms_notifications = isset($_POST['sms_notifications']) ? 1 : 0;
        $pref_poker = isset($_POST['pref_poker']) ? 1 : 0;
        $pref_tournoi = isset($_POST['pref_tournoi']) ? 1 : 0;
        $pref_casual = isset($_POST['pref_casual']) ? 1 : 0;
        $notif_frequency = mysqli_real_escape_string($con, $_POST['notif_frequency']);
        
        // Validate notif_frequency value
        if (!in_array($notif_frequency, ['immediate', 'daily', 'weekly'])) {
            throw new Exception("Invalid notification frequency value");
        }
        
        // Update database
        $stmt = mysqli_prepare($con, "UPDATE membres SET 
            email_notifications = ?,
            sms_notifications = ?,
            pref_poker = ?,
            pref_tournoi = ?,
            pref_casual = ?,
            notif_frequency = ?
            WHERE `id-membre` = ?");
            
        if (!$stmt) {
            throw new Exception("Prepare failed: " . mysqli_error($con));
        }
        
        mysqli_stmt_bind_param($stmt, 'iiiiiss',
            $email_notifications,
            $sms_notifications,
            $pref_poker,
            $pref_tournoi,
            $pref_casual,
            $notif_frequency,
            $id);
            
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Execute failed: " . mysqli_stmt_error($stmt));
        }
        
        $affected = mysqli_stmt_affected_rows($stmt);
        if ($affected > 0) {
            $_SESSION['msg'] = "Préférences de notification mises à jour avec succès";
        } else {
            $_SESSION['msg'] = "Aucune modification effectuée";
        }
        
    } catch (Exception $e) {
        error_log("Error updating notification preferences: " . $e->getMessage());
        $_SESSION['error'] = $e->getMessage();
    } finally {
        if (isset($stmt)) {
            mysqli_stmt_close($stmt);
        }
    }
}
$query = "SELECT * FROM `membres` WHERE `id-membre` = '" . intval($id) . "'";
// Debug: show GET and session id when requested
if (isset($_GET['debug']) && $_GET['debug'] == '1') {
    echo '<div style="background:#eef;padding:10px;border:1px solid #99c;margin-bottom:10px;">DEBUG REQUEST: GET id=' . htmlentities(isset($_GET['id']) ? $_GET['id'] : '(none)') . ' | intval(id)=' . intval($id) . ' | session_id=' . htmlentities(session_id()) . '</div>';
}
$sql = mysqli_query($con, $query);
if (!$sql) {
    $err = mysqli_error($con);
    error_log("ERROR: SELECT failed for voir-membre: $err -- Query: $query");
    $_SESSION['error'] = "Erreur SQL: " . $err;
    $member = null;
} else {
    $num = mysqli_num_rows($sql);
    error_log("DEBUG: SELECT returned $num rows for id=$id");
    if ($num > 0) {
        $member = mysqli_fetch_assoc($sql);
        // Charger l'état de la réservation
        $member['reservation'] = getReservationStatus($id, $con);
    } else {
        $member = null;
    }
}
// Sécurité affichage mot de passe : masquer sauf admin ou self
if (isset($member['password'])) {
    if (!$is_admin && !$is_self) {
        $member['password'] = '******';
    }
}
if (!$member) {
    // Provide defaults to avoid template errors
    $member = array('pseudo' => '', 'fname' => '', 'lname' => '', 'telephone' => '', 'email' => '', 'rue' => '', 'ville' => '', 'longitude' => '', 'latitude' => '', 'password' => '', 'naissance_date' => '', 'posting_date' => '', 'association_date' => '', 'CodeV' => '', 'verification' => '', 'def_com' => '', 'def_str' => '', 'def_nbj' => '', 'def_buy' => '', 'def_rak' => '', 'def_bou' => '', 'def_rec' => '', 'def_jet' => '', 'def_recave_jetons' => '', 'def_recave_montant' => '', 'def_bon' => '', 'def_add' => '', 'def_ant' => '', 'def_cha' => '', 'def_rdv' => '', 'def_sta' => '' );
    // Also show debug message on page
    if (!empty($_SESSION['error'])) {
        echo '<div class="alert alert-danger">'.htmlentities($_SESSION['error']).'</div>';
        $_SESSION['error'] = '';
    } else {
        echo '<div class="alert alert-warning">Aucun membre trouvé pour id=' . intval($id) . ' (Vérifiez l\'ID ou la base de données).</div>';
        if (isset($_GET['debug']) && $_GET['debug'] == '1') {
            echo '<pre style="background:#fff8e1;padding:10px;border:1px solid #eee;color:#000;margin-top:10px;">DEBUG INFO:\nQuery: ' . htmlentities($query) . '\nMySQL error: ' . htmlentities(mysqli_error($con)) . '\nNum rows: ' . intval($num) . '</pre>';
        }
    }
}
?>
     
<!DOCTYPE html>
<html lang="en">

<head>
    <title>Admin | Edition Membre</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
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
    <!-- <link href="https://cdn.jsdelivr.net/npm/simple-datatables@latest/dist/style.css" rel="stylesheet" /> -->
    <link href="vendor/bootstrap-timepicker/bootstrap-timepicker.min.css" rel="stylesheet" media="screen">
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/plugins.css">
    <link rel="stylesheet" href="assets/css/themes/theme-1.css" id="skin_color" />
    <script src="https://code.jquery.com/jquery-3.7.0.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <link href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css" rel="stylesheet" />
    <script type="text/javascript">
        $(document).ready(function() {
            // Configuration commune pour les tableaux
            var dtLang = {
                url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/fr-FR.json'
            };

            // Tables générales (hors Tombola)
            $('#example, #example2, #example3, #example5').DataTable({
                pageLength: 3,
                language: dtLang
            });

            // Tableau Tombola : pagination 10 lignes, sans tri côté client
            $('#example4').DataTable({
                pageLength: 8,
                ordering: false,
                language: dtLang
            });

        });
    </script>
    <link rel="stylesheet" href="css/mes-styles.css">
    <!-- <link rel="stylesheet" href="voir-membre.css"> -->
    <link rel="stylesheet" href="css/les-styles.css">
    <script type="text/javascript">
        function valid() {
            if (document.adddoc.npass.value != document.adddoc.cfpass.value) {
                alert("Password and Confirm Password Field do not match  !!");
                document.adddoc.cfpass.focus();
                return false;
            }
            return true;
        }
    </script>
    <script>
        function checkemailAvailability() {
            $("#loaderIcon").show();
            jQuery.ajax({
                url: "check_availability.php",
                data: 'emailid=' + $("#docemail").val(),
                type: "POST",
                success: function(data) {
                    $("#email-availability-status").html(data);
                    $("#loaderIcon").hide();
                },
                error: function() {}
            });
        }
    </script>
    <style>
        #portefeuilleE {
    display: none;
}
#portefeuilleE.montrer {
    display: block;
}
/* Ensure input/select/textarea values are visible even inside tables with white text color */
.table.current-user input,
.table.current-user select,
.table.current-user textarea {
    color: #000 !important;
}
    </style>
</head>

<body>
    <div id="app">
        <?php include('include/sidebar.php'); ?>
        <div class="app-content">
            <?php include('include/header.php'); ?>
            <!-- end: TOP NAVBAR -->
            <!-- <div class="calque">
                            Sections et onglets Css
                        </div> -->
            <div class="main-content">
                <div class="wrap-content container" id="container">
                    <!-- start: PAGE TITLE -->
                    <section id="page-title">
                        <div class="row">
                            <!-- <div class="col-sm-8">
                                    <h1 class="mainTitle">Admin | Membre</h1>
                                </div> -->
                            <ol class="breadcrumb">
                                <li>
                                    <span>Admin</span>
                                </li>
                                <li class="active">
                                    <span>Edition Membre</span>
                                </li>
                            </ol>
                        </div>
                    </section>
                    <!-- end: PAGE TITLE -->
                    <!-- start: BASIC EXAMPLE -->

                    <div id="conteneur">
                        <div id="contenu">
                            <div id="auCentre">
                                <div id="bMenu">
                                    <a href="#" id="css" class="btnnav" onmouseover="afficher('css')">Joueur</a>
                                    <a href="#" id="css2" class="btnnav" onmouseover="afficher('css2')">Orga.</a>
                                    <a href="#" id="css3" class="btnnav" onmouseover="afficher('css3')">Notifs</a>
                                    <a href="#" id="js" class="btnnav" onmouseover="afficher('js')">Compét.</a>
                                    <a href="#" id="php" class="btnnav" onmouseover="afficher('php')">Loisirs</a>
                                    <a href="#" id="col" class="btnnav" onmouseover="afficher('col')">Tombola</a>
                                    <a href="#" id="ks" class="btnnav" onmouseover="afficher('ks')">Activités</a>
                                    <a href="#" id="portefeuille" class="btnnav" onmouseover="afficher('portefeuille')">$$$</a>
                                    <a href="#" id="trak" class="btnnav" onmouseover="afficher('trak')">Trak 📝</a>
                                </div>
                                <div id="bSection">
                                    <div id="cssE">
                                        <script src="https://code.responsivevoice.org/responsivevoice.js?key=RTEc1M0w" onload="try{ responsiveVoice.setDefaultVoice('French Female'); }catch(e){ console.warn('responsiveVoice load onload', e); }"></script>
                                        <script>
                                            responsiveVoice.setDefaultVoice("French Female")
                                        </script>
                                        <script>
                                            /* responsiveVoice.speak("Membre", "French Female", {
                                                volume: 1
                                            }) */
                                        </script>
                                        <div class="wrap-content container" id="container">
                                            <div class="container-fluid container-fullw bg-white">
                                                <div class="col-md-12">
                                                    <div class="row margin-top-30">
                                                        <div class="panel-wwhite">
                                                            <div class="panel-body">
                                                                <?php if (!empty($_SESSION['msg'])) { echo '<div class="alert alert-success">' . htmlentities($_SESSION['msg']) . '</div>'; $_SESSION['msg'] = ''; } ?>
                                                                <div class="form-group">
                                                                    <?php
                                                                    // Use the $row loaded above by the main SELECT; do not fetch the same result set again (pointer already advanced).
                                                                    // $row already contains the member row or defaults set earlier.
                                                                    ?>
                                                                        <!-- Formulaire Photo sorti du formulaire principal et ID uniques (1) -->
                                                                        <form id="image_upload_form_1" enctype="multipart/form-data" method="post" class="change-pic" style="display:none;">
                                                                            <input type="hidden" name="id" value="<?php echo $id; ?>">
                                                                            <input type="file" name="fileToUpload" id="fileToUpload_1" accept="image/*" onchange="document.getElementById('upload-spinner_1').style.display='inline-block'; this.form.submit();">
                                                                        </form>

                                                                        <form method="post">
<?php /* FORM RENDER debug output removed for production */ ?>

                                                                        <table style="color: white;" class="table table-bordered current-user">
                                                                            <tr>
                                                                                <td rowspan="3" align="center">
                                                                                    <img src="../images/faces/<?php echo $member['photo']; ?>" width="95" height="85" style="align:center">
                                                                                    
                                                                                    <div style="margin-top:10px;">
                                                                                        <button type="button" class="btn btn-primary btn-sm" onclick="document.getElementById('fileToUpload_1').click();">
                                                                                            <i class="fa fa-camera"></i> Changer Photo
                                                                                        </button>
                                                                                        <a href="gerer-avatars.php?id=<?php echo $id; ?>" class="btn btn-secondary btn-sm" style="margin-left: 10px;">
                                                                                            <i class="fa fa-images"></i> Galerie Avatars
                                                                                        </a>
                                                                                        <span id="upload-spinner_1" style="display:none; margin-left:10px;">
                                                                                            <i class="fa fa-spinner fa-spin"></i> Uploading...
                                                                                        </span>
                                                                                    </div>
                                                                                </td>
                                                                                    <th style="color:rgb(64, 30, 235) !important;">Votre Pseudo :</th>
                                                                                    <td colspan="3"><input class="form-control" id="pseudo" name="pseudo" type="text" style="text-align:center; font-size:22px; bold" value="<?php echo $member['pseudo']; ?>"></td>

                                                                            </tr>
                                                                            <tr>
                                                                                <td style="display: none;">
                                                                                    <button type="submit" name="submit" id="submit" class="btn btn-oo btn-primary">
                                                                                        Mise à jour</button>
                                                                                </td>
                                                                                <td style="text-align:center ;">
                                                                                    <button type="submit" class="btn btn-primary-green btn-block" name="submit">OK </button>
                                                                                </td>
                                                                                <td style="text-align:center ;">
                                                                                    <button type="submit" class="btn btn-primary btn-block" name="submit">Modifier</button>
                                                                                </td>
                                                                                <td style="text-align:center ;">
                                                                                    <button type="submit" class="btn btn-primary btn-block" name="submitdup">Création Activité</button>
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <td colspan="4"></td>
                                                                            </tr>
                                                                            <tr>
                                                                                <th style="color: #ffffff !important;">Prénom</th>
                                                                                <td><input class="form-control" id="fname" name="fname" type="text" value="<?php echo $member['fname']; ?>">
                                                                                </td>
                                                                                <th style="color: #ffffff !important;">Nom</th>
                                                                                <td><input class="form-control" id="lname" name="lname" type="text" value="<?php echo $member['lname']; ?>">
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <th style="color: #ffffff !important;">Téléphone</th>
                                                                                <td><input class="form-control" id="telephone" name="telephone" type="text" value="<?php echo $member['telephone']; ?>"></td>
                                                                                <th style="color: #ffffff !important;">Email</th>
                                                                                <td><input class="form-control" id="email" name="email" type="text" value="<?php echo $member['email']; ?>"></td>
                                                                            </tr>
                                                                            <tr>
                                                                                <th style="color: #ffffff !important;">Adresse</th>
                                                                                <td><input class="form-control" id="rue" name="rue" type="text" value="<?php echo $member['rue']; ?>"></td>
                                                                                <th style="color: #ffffff !important;">Ville</th>
                                                                                <td><input class="form-control" id="ville" name="ville" type="text" value="<?php echo $member['ville']; ?>"></td>
                                                                            </tr>
                                                                            <tr>
                                                                                <th style="color: #ffffff !important;">Longitude</th>
                                                                                <td><input class="form-control" id="longitude" name="longitude" type="text" value="<?php echo $member['longitude']; ?>">
                                                                                </td>
                                                                                <th style="color: #ffffff !important;">Latitude</th>
                                                                                <td><input class="form-control" id="latitude" name="latitude" type="text" value="<?php echo $member['latitude']; ?>">
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <th style="color: #ffffff !important;">Mot de passe</th>
                                                                                <td>
                                                                                <?php if ($is_admin || $is_self): ?>
                                                                                    <input class="form-control" id="password" name="password" type="text" value="<?php echo $member['password']; ?>">
                                                                                <?php else: ?>
                                                                                    <input class="form-control" id="password" name="password" type="text" value="******" readonly disabled>
                                                                                <?php endif; ?>
                                                                                </td>
                                                                                <th style="color: #ffffff !important;">Date nais</th>
                                                                                <td><input class="form-control" id="naissance_date" name="naissance_date" type="date" value="<?php echo $member['naissance_date']; ?>">
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <th style="color: #ffffff !important;">Date inscription</th>
                                                                                <td><input class="form-control" id="posting_date" name="posting_date" type="date" value="<?php echo $member['posting_date']; ?>">
                                                                                </td>
                                                                                <th style="color: #ffffff !important;">Fin Abonnement</th>
                                                                                <td><input class="form-control" id="association_date" name="association_date" type="date" value="<?php echo $member['association_date']; ?>">
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <th style="color: #ffffff !important;">CodeV</th>
                                                                                <td><input class="form-control" id="CodeV" name="CodeV" type="text" value="<?php echo $member['CodeV']; ?>">
                                                                                </td>
                                                                                <th style="color: #ffffff !important;">Validé</th>
                                                                                <td><input class="form-control" id="verification" name="verification" type="text" value="<?php echo $member['verification']; ?>">
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <th style="color: #ffffff !important;">Réservation</th>
                                                                                <td>
                                                                                    <select class="form-control" id="reservation" name="reservation">
                                                                                        <option value="0" <?php echo (!isset($member['reservation']) || $member['reservation'] == 0) ? 'selected' : ''; ?>>Non</option>
                                                                                        <option value="1" <?php echo (isset($member['reservation']) && $member['reservation'] == 1) ? 'selected' : ''; ?>>Oui</option>
                                                                                    </select>
                                                                                </td>
                                                                                <td colspan="2"></td>
                                                                            </tr>
                                                                            <tr>
                                                                                <td colspan="4"></td>
                                                                            </tr>
                                                                            <tr>
                                                                                <!-- CORRECTION LIGNE 675 : Fusion des attributs style -->
                                                                                <td style="display:none; text-align:center;">
                                                                                    <button type="submit" class="btn btn-primary btn-block" name="submito">Mise à jour</button>
                                                                                </td>
                                                                                <!-- <td colspan="2">
                                                                                    <a href="liste-membres.php">Quitter </a>
                                                                                </td> -->
                                                                            </tr>
                                                                            </form>
                                                                        </table>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>    
                                    </div>    
                                    <div id="css2E">                                        
                                        <div class="wrap-content container" id="container9">
                                            <div class="container-fluid container-fullw bg-white">
                                                <div class="col-md-12">
                                                    <div class="row margin-top-30">
                                                        <div class="panel-wwhite">
                                                            <div class="panel-body">
                                                                <?php echo htmlentities($_SESSION['msg'] = ""); ?>
                                                                <div class="form-group">
                                                                    <?php
                                                                    $id = intval($_GET['id']);
                                                                    $sql = mysqli_query($con, "SELECT * FROM `membres` WHERE `id-membre` =  '$id'");
                                                                    while ($row = mysqli_fetch_array($sql)) {
                                                                    ?>
                                                                        <!-- Formulaire Photo sorti du formulaire principal et ID uniques (2) -->
                                                                        <form id="image_upload_form_2" enctype="multipart/form-data" method="post" class="change-pic" style="display:none;">
                                                                            <input type="hidden" name="id" value="<?php echo $id; ?>">
                                                                            <input type="file" name="fileToUpload" id="fileToUpload_2" accept="image/*" onchange="document.getElementById('upload-spinner_2').style.display='inline-block'; this.form.submit();">
                                                                        </form>

                                                                        <form method="post">
                                                                        <table style="color: white;" class="table table-bordered current-user">
                                                                            <tr>
                                                                                <td rowspan="3" align="center">
                                                                                    <img src="../images/faces/<?php echo $row['photo']; ?>" width="85" height="85" style="align:center">
                                                                                    
                                                                                    <div style="margin-top:10px;">
                                                                                        <button type="button" class="btn btn-primary btn-sm" onclick="document.getElementById('fileToUpload_2').click();">
                                                                                            <i class="fa fa-camera"></i> Changer Photo
                                                                                        </button>
                                                                                        <span id="upload-spinner_2" style="display:none; margin-left:10px;">
                                                                                            <i class="fa fa-spinner fa-spin"></i> Uploading...
                                                                                        </span>
                                                                                    </div>
                                                                                </td>
                                                                                    <th style="color:rgb(64, 30, 235) !important;">Votre Pseudo :</th>
                                                                                    <td colspan="3"><input class="form-control" id="pseudo" name="pseudo" type="text" style="text-align:center; font-size:22px; bold" value="<?php echo $member['pseudo']; ?>"></td>

                                                                            </tr>
                                                                            <tr>
                                                                                <td style="display: none; text-align: left;">
                                                                                    <button type="submit" name="submit" id="submit" class="btn btn-oo btn-primary">
                                                                                        Mise à jour</button>
                                                                                </td>
                                                                                <td style="text-align:center ;">
                                                                                    <button type="submit" class="btn btn-primary-green btn-block" name="submit">OK </button>
                                                                                </td>
                                                                                <td style="text-align:center ;">
                                                                                    <button type="submit" class="btn btn-primary btn-block" name="submit">Modifier</button>
                                                                                </td>
                                                                                <td style="text-align:center ;">
                                                                                    <button type="submit" class="btn btn-primary btn-block" name="submitdup">Création Activité</button>
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <td colspan="4"></td>
                                                                            </tr>
                                                                            <tr>
                                                                                <th style="color: #ffffff !important;">Titre Activité</th>
                                                                                <td><input class="form-control" id="def_com" name="def_com" type="text" value="<?php echo $member['def_com']; ?>">
                                                                                </td>
                                                                                <th style="color: #ffffff !important;">Nb Joueurs</th>
                                                                                <td><input class="form-control" id="def_nbj" name="def_nbj" type="text" value="<?php echo $member['def_nbj']; ?>">
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <th style="color: #ffffff !important;">Buyin</th>
                                                                                <td><input class="form-control" id="def_buy" name="def_buy" type="text" value="<?php echo $member['def_buy']; ?>">
                                                                                </td>
                                                                                <th style="color: #ffffff !important;">Rake</th>
                                                                                <td><input class="form-control" id="def_rak" name="def_rak" type="text" value="<?php echo $member['def_rak']; ?>">
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <th style="color: #ffffff !important;">Bounty</th>
                                                                                <td><input class="form-control" id="def_bou" name="def_bou" type="text" value="<?php echo $member['def_bou']; ?>">
                                                                                </td>
                                                                                <th style="color: #ffffff !important;">Recaves</th>
                                                                                <td><input class="form-control" id="def_rec" name="def_rec" type="text" value="<?php echo $member['def_rec']; ?>">
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <th style="color: #ffffff !important;">Nb Jetons</th>
                                                                                <td><input class="form-control" id="def_jet" name="def_jet" type="text" value="<?php echo $member['def_jet']; ?>">
                                                                                </td>
                                                                                <th style="color: #ffffff !important;">Bonus</th>
                                                                                <td><input class="form-control" id="def_bon" name="def_bon" type="text" value="<?php echo $member['def_bon']; ?>">
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <th style="color: #ffffff !important;">Nb Jetons Recave</th>
                                                                                <td><input class="form-control" id="def_recave_jetons" name="def_recave_jetons" type="text" value="<?php echo isset($row['def_recave_jetons']) ? $row['def_recave_jetons'] : ''; ?>">
                                                                                </td>
                                                                                <th style="color: #ffffff !important;">Montant Recave</th>
                                                                                <td><input class="form-control" id="def_recave_montant" name="def_recave_montant" type="text" value="<?php echo isset($row['def_recave_montant']) ? $row['def_recave_montant'] : ''; ?>">
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <th style="color: #ffffff !important;">Addon</th>
                                                                                <td><input class="form-control" id="def_add" name="def_add" type="text" value="<?php echo $member['def_add']; ?>">
                                                                                </td>
                                                                                <th style="color: #ffffff !important;">Ante</th>
                                                                                <td><input class="form-control" id="def_ant" name="def_ant" type="text" value="<?php echo $member['def_ant']; ?>">
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <th style="color: #ffffff !important;">Rendez-vous</th>
                                                                                <td><input class="form-control" id="def_rdv" name="def_rdv" type="text" value="<?php echo $member['def_rdv']; ?>">
                                                                                </td>
                                                                                <th style="color: #ffffff !important;">Debut</th>
                                                                                <td><input class="form-control" id="def_sta" name="def_sta" type="text" value="<?php echo $member['def_sta']; ?>">
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <th style="color: #ffffff !important;">Longitude</th>
                                                                                <td><input class="form-control" id="longitude" name="longitude" type="text" value="<?php echo $member['longitude']; ?>">
                                                                                </td>
                                                                                <th style="color: #ffffff !important;">Latitude</th>
                                                                                <td><input class="form-control" id="latitude" name="latitude" type="text" value="<?php echo $member['latitude']; ?>">
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <th style="color: #ffffff !important;">Structure</th>
                                                                                <td>
                                                                                    <select class="form-control" id="def_str" name="def_str">
                                                                                        <option value="">Choisir une structure</option>
                                                                                        <?php 
                                                                                        $q_str = mysqli_query($con, "SELECT num_structure, nom FROM structure_modele WHERE num_structure IS NOT NULL ORDER BY nom ASC");
                                                                                        while($r_str = mysqli_fetch_array($q_str)) {
                                                                                            $selected = ($r_str['num_structure'] == $member['def_str']) ? 'selected' : '';
                                                                                            echo '<option value="'.$r_str['num_structure'].'" '.$selected.'>'.htmlentities($r_str['nom']).'</option>';
                                                                                        }
                                                                                        ?>
                                                                                    </select>
                                                                                </td>
                                                                                <th style="color: #ffffff !important;">Challenge</th>
                                                                                <td><input class="form-control" id="def_cha" name="def_cha" type="text" value="<?php echo $member['def_cha']; ?>">
                                                                                </td>
                                                                            </tr>
                                                                            
                                                                            <tr>
                                                                                <td colspan="4"></td>
                                                                            </tr>
                                                                            <tr>
                                                                                <!-- CORRECTION LIGNE 806 : Fusion des attributs style -->
                                                                                <td style="display:none; text-align:center;">
                                                                                    <button type="submit" class="btn btn-primary btn-block" name="submitdup">Mise à jour</button>
                                                                                </td>
                                                                            </tr>
                                                                            </form>
                                                                        </table>
                                                                    <?php } ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div id="portefeuilleE" >
                                        <div class="wrap-content container" id="container12">
                                            <div class="container-fluid container-fullw bg-white">
                                                <div class="col-md-12">
                                                    <div class="row margin-top-30">
                                                        <div class="panel-white">
                                                            <div class="panel-body">
                                                                <?php echo htmlentities($_SESSION['msg'] = ""); ?>
                                                                <div class="form-group">
                                                                    <?php
                                                                    $id = intval($_GET['id']);
                                                                    $sql = mysqli_query($con, "SELECT pseudo FROM membres WHERE `id-membre` = '$id'");
                                                                    $row = mysqli_fetch_array($sql);
                                                                    ?>
                                                                    <!-- Garder uniquement le titre avec le pseudo -->
                                                                    <h2 class="text-center" style="color: #2e6da4; font-weight: bold;margin-bottom: 20px;">
                                                                        Portefeuille de <?php echo $row['pseudo']; ?>
                                                                    </h2>

                                                                    <!-- Supprimer ce bloc alert-info -->
                                                                    <!-- <div class="alert alert-info">... </div> -->

                                                                    <!-- Garder le reste du code... -->

                                                                    <!-- Formulaire d'ajout de transaction -->
                                                                    <form method="post">
                                                                        <table style="color: white;" class="table table-bordered current-user">
                                                                            <tr>
                                                                                <th>Opération</th>
                                                                                <td>
                                                                                    <select class="form-control" id="id_type_mvt" name="id_type_mvt" required>
                                                                                        <option value="">-- Sélectionner un Mouvement --</option>
                                                                                        <optgroup label="Débit">
                                                                                            <option value="1">Buyin</option>
                                                                                            <option value="2">Rake</option>
                                                                                            <option value="3">Gestion</option>
                                                                                        </optgroup>
                                                                                        <optgroup label="Crédit">
                                                                                            <option value="4">Gain</option>
                                                                                            <option value="6">Tombola</option>
                                                                                            <option value="5">Gestion</option>
                                                                                        </optgroup>
                                                                                    </select>
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <th>Montant</th>
                                                                                <td>
                                                                                    <input class="form-control" id="montant" name="montant" 
                                                                                           type="number" step="0.01" required>
                                                                                </td>
                                                                            </tr>
                                                                            <tr style="text-align:center ; display:none">
                                                                                <th>Date</th>
                                                                                <td >
                                                                                    <input class="form-control" id="date_mvt" name="date_mvt" type="date">
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <th>ID Participation</th>
                                                                                <td>
                                                                                    <select class="form-control" display="none" id="id_participation" name="id_participation">
                                                                                        <option value="">-- Sélectionner une participation --</option>
                                                                                        <?php
                                                                                        $sql_participations = mysqli_query($con, "SELECT p.`id-participation`, a.`titre-activite`, a.date_depart 
                                                                                            FROM participation p 
                                                                                            JOIN activite a ON p.`id-activite` = a.`id-activite`
                                                                                            WHERE p.`id-membre` = $id 
                                                                                            ORDER BY a.date_depart DESC");
                                                                                        while ($participation = mysqli_fetch_array($sql_participations)) {
                                                                                            echo '<option value="' . $participation['id-participation'] . '">' 
                                                                                                . date('d/m/Y', strtotime($participation['date_depart'])) 
                                                                                                . ' - ' . $participation['titre-activite'] 
                                                                                                . '</option>';
                                                                                        }
                                                                                        ?>
                                                                                    </select>
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <td colspan="2" style="text-align:center;">
                                                                                    <button type="submit" class="btn btn-primary btn-block" name="submit_portefeuille">
                                                                                        Ajouter Transaction
                                                                                    </button>
                                                                                </td>
                                                                            </tr>
                                                                        </table>
                                                                    </form>
                                                                </div>
                                                                <!-- Table to display existing transactions -->
                                                                <div class="table-responsive">
                                                                    <table id="transactionsTable" class="table table-striped">
                                                                        <thead>
                                                                            <tr>
                                                                                <th>Date</th>
                                                                                <th>Particip.</th>  <!-- Nouvelle colonne -->
                                                                                <th>Opération</th>
                                                                                <th>Montant</th>
                                                                                <th>Action</th>
                                                                            </tr>
                                                                        </thead>
                                                                        <tbody>
                                                                            <?php
                                                                            $sql_transactions = mysqli_query($con, "SELECT * FROM portefeuille WHERE id_mvt_membre = $id ORDER BY date_mvt ASC");
                                                                            while ($transaction = mysqli_fetch_array($sql_transactions)) {
                                                                                $type = ($transaction['id_type_mvt'] == 1) ? 'Entrée' : 'Sortie';
                                                                                $class = ($transaction['id_type_mvt'] == 1) ? 'text-success' : 'text-danger';
                                                                            ?>
                                                                                <tr>
                                                                                    <td><?php echo date('d/m/Y', strtotime($transaction['date_mvt'])); ?></td>
                                                                                    <td>
                                                                                        <?php echo $transaction['id_participation'] ?: '-'; ?>
                                                                                        <a href="voir-participation.php?id=<?php echo $transaction['id_participation']; ?>" class="btn btn-transparent btn-xs" tooltip-placement="top" tooltip="Edit"><i class="fa fa-pencil"></i></a>
                                                                                                                
                                                                                    </td>  <!-- Nouvelle colonne -->
                                                                                    <td class="<?php echo $class; ?>">
                                                                                        <?php 
                                                                                        switch($transaction['id_type_mvt']) {
                                                                                            case 1:
                                                                                                echo 'Débit Buyin';
                                                                                            break;
                                                                                            case 2:
                                                                                                echo 'Débit Rake';
                                                                                            break;
                                                                                            case 3:
                                                                                                echo 'Debit Gestion';
                                                                                            break;
                                                                                            case 4:
                                                                                                echo 'Crédit Gain';
                                                                                            break;
                                                                                            case 6:
                                                                                                echo 'Crédit Tombola';
                                                                                            break;
                                                                                            case 5:
                                                                                                echo 'Crédit Gestion';
                                                                                            break;
                                                                                            default:
                                                                                                echo 'Inconnu';
                                                                                        }
                                                                                        ?>
                                                                                    </td>
                                                                                    <td class="<?php echo $class; ?>">
                                                                                        <?php echo number_format($transaction['montant'], 2, ',', ' '); ?> €
                                                                                    </td>
                                                                                    <td>
                                                                                        <a href="modifier-transaction.php?id=<?php echo $transaction['id_mvt']; ?>" 
                                                                                           class="btn btn-primary btn-xs" title="Modifier">
                                                                                            <i class="fa fa-edit"></i>
                                                                                        </a>
                                                                                        <a href="supprimer-transaction.php?id=<?php echo $transaction['id_mvt']; ?>" 
                                                                                           onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette transaction ?')"
                                                                                           class="btn btn-danger btn-xs" title="Supprimer">
                                                                                            <i class="fa fa-trash"></i>
                                                                                        </a>
                                                                                    </td>
                                                                                </tr>
                                                                            <?php } ?>
                                                                        </tbody>
                                                                    </table>
                                                                </div> 
                                                                <div class="row">
    <div class="col-md-12 text-center">
        <?php
        // Calcul du solde directement (ne pas utiliser la colonne solde de la table membres)
        $solde_query = mysqli_query($con, "SELECT 
            COALESCE(SUM(CASE WHEN id_type_mvt = 4 THEN montant ELSE 0 END), 0) + COALESCE(SUM(CASE WHEN id_type_mvt = 6 THEN montant ELSE 0 END), 0) + COALESCE(SUM(CASE WHEN id_type_mvt = 5 THEN montant ELSE 0 END), 0) -
            COALESCE(SUM(CASE WHEN id_type_mvt = 1 THEN montant ELSE 0 END), 0) - COALESCE(SUM(CASE WHEN id_type_mvt = 2 THEN montant ELSE 0 END), 0) - COALESCE(SUM(CASE WHEN id_type_mvt = 3 THEN montant ELSE 0 END), 0)as balance
            FROM portefeuille 
            WHERE id_mvt_membre = $id");
        
        $solde_row = mysqli_fetch_assoc($solde_query);
        $solde = $solde_row['balance'];
        ?>
        <div style="background-color: #2e6da4; color: white; padding: 15px; margin: 20px 0; border-radius: 10px; font-size: 20px;">
            <strong>Solde actuel : </strong> 
            <span style="font-size: 28px; color: white; font-weight: bold;" 
                  class="<?php echo ($solde >= 0) ? 'text-success' : 'text-danger'; ?>">
                <?php echo number_format($solde, 2, ',', ' '); ?> €
            </span>
        </div>
    </div>
</div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div id="css3E">
                                        <div class="wrap-content container" id="container10">
                                            <div class="container-fluid container-fullw bg-white">
                                                <div class="col-md-12">
                                                    <div class="row margin-top-30">
                                                        <div class="panel-wwhite">
                                                            <div class="panel-body">
                                                                <?php echo htmlentities($_SESSION['msg'] = ""); ?>
                                                                <div class="form-group">
                                                                    <?php
                                                                    $id = intval($_GET['id']);
                                                                    $sql = mysqli_query($con, "SELECT * FROM `membres` WHERE `id-membre` =  '$id'");
                                                                    while ($row = mysqli_fetch_array($sql)) {
                                                                    ?>
                                                                        <!-- Formulaire Photo sorti du formulaire principal et ID uniques (3) -->
                                                                        <form id="image_upload_form_3" enctype="multipart/form-data" method="post" class="change-pic" style="display:none;">
                                                                            <input type="hidden" name="id" value="<?php echo $id; ?>">
                                                                            <input type="file" name="fileToUpload" id="fileToUpload_3" accept="image/*" onchange="document.getElementById('upload-spinner_3').style.display='inline-block'; this.form.submit();">
                                                                        </form>

                                                                        <table style="color: white;" class="table table-bordered current-user">
                                                                            <tr>
                                                                                <td rowspan="3" align="center">
                                                                                    <img src="../images/faces/<?php echo $row['photo']; ?>" width="85" height="85" style="align:center">
                                                                                    
                                                                                    <div style="margin-top:10px;">
                                                                                        <button type="button" class="btn btn-primary btn-sm" onclick="document.getElementById('fileToUpload_3').click();">
                                                                                            <i class="fa fa-camera"></i> Changer Photo
                                                                                        </button>
                                                                                        <span id="upload-spinner_3" style="display:none; margin-left:10px;">
                                                                                            <i class="fa fa-spinner fa-spin"></i> Uploading...
                                                                                        </span>
                                                                                    </div>
                                                                                </td>
                                                                                <form method="post">
                                                                                    <th style="color:rgb(64, 30, 235) !important;">Notifications :</th>
                                                                                    <td colspan="3">
                                                                                        <div class="form-check">
                                                                                            <input class="form-check-input" type="checkbox" id="email_notifications" name="email_notifications" <?php echo ($row['email_notifications'] == 1) ? 'checked' : ''; ?>>
                                                                                            <label class="form-check-label" for="email_notifications">Email Notifications</label>
                                                                                        </div>
                                                                                        <div class="form-check">
                                                                                            <input class="form-check-input" type="checkbox" id="sms_notifications" name="sms_notifications" <?php echo ($row['sms_notifications'] == 1) ? 'checked' : ''; ?>>
                                                                                            <label class="form-check-label" for="sms_notifications">SMS Notifications</label>
                                                                                        </div>
                                                                                    </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <td style="text-align:center ; display:none">
                                                                                    <button type="submit" name="submit" id="submit" class="btn btn-oo btn-primary">
                                                                                        Mise à jour</button>
                                                                                </td>
                                                                                <td style="text-align:center ;" colspan="4">
                                                                                    <button type="submit" class="btn btn-primary-green btn-block" name="submitnotif">Mettre à jour les préférences</button>
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <td colspan="4"></td>
                                                                            </tr>
                                                                            <tr>
                                                                                <th style="color: #ffffff !important;">Préférences d'activité</th>
                                                                                <td colspan="3">
                                                                                    <div class="form-check">
                                                                                        <input class="form-check-input" type="checkbox" id="pref_poker" name="pref_poker" <?php echo ($row['pref_poker'] == 1) ? 'checked' : ''; ?>>
                                                                                        <label class="form-check-label" for="pref_poker">Poker</label>
                                                                                    </div>
                                                                                    <div class="form-check">
                                                                                        <input class="form-check-input" type="checkbox" id="pref_tournoi" name="pref_tournoi" <?php echo ($row['pref_tournoi'] == 1) ? 'checked' : ''; ?>>
                                                                                        <label class="form-check-label" for="pref_tournoi">Tournois</label>
                                                                                    </div>
                                                                                    <div class="form-check">
                                                                                        <input class="form-check-input" type="checkbox" id="pref_casual" name="pref_casual" <?php echo ($row['pref_casual'] == 1) ? 'checked' : ''; ?>>
                                                                                        <label class="form-check-label" for="pref_casual">Parties Casual</label>
                                                                                    </div>
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <td colspan="4"></td>
                                                                            </tr>
                                                                            <tr>
                                                                                <th style="color: #ffffff !important;">Fréquence de notification</th>
                                                                                <td colspan="3">
                                                                                    <select class="form-control" id="notif_frequency" name="notif_frequency">
                                                                                        <option value="immediate" <?php echo ($row['notif_frequency'] == 'immediate') ? 'selected' : ''; ?>>Immédiate</option>
                                                                                        <option value="daily" <?php echo ($row['notif_frequency'] == 'daily') ? 'selected' : ''; ?>>Quotidienne</option>
                                                                                        <option value="weekly" <?php echo ($row['notif_frequency'] == 'weekly') ? 'selected' : ''; ?>>Hebdomadaire</option>
                                                                                    </select>
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <td colspan="4"></td>
                                                                            </tr>
                                                                            <tr>
                                                                                <!-- CORRECTION LIGNE 675 : Fusion des attributs style -->
                                                                                <td style="display:none; text-align:center;">
                                                                                    <button type="submit" class="btn btn-primary btn-block" name="submito">Mise à jour</button>
                                                                                </td>
                                                                                <!--<td colspan="2">
                                                                                    <a href="liste-membres.php">Quitter </a>
                                                                                </td> -->
                                                                            </tr>
                                                                            </form>
                                                                        </table>
                                                                    <?php } ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div id="jsE">
                                        <div class="row">
                                            <div class="col-md-12">
                                                <!-- <h5 class="over-title margin-bottom-15">-> <span class="text-bold">Gestion des Competences</span></h5> -->
                                                <div class="container-fluid container-fullw bg-white">
                                                    <div class="row">
                                                        <div class="col-md-12">
                                                            <div class="row margin-top-30">
                                                                <div class="col-lg-8 col-md-12">
                                                                    <div class="panel panel-wwhite">
                                                                        <!--	<div class="panel-heading">
                                                                  <h5 class="panel-title">Ajout Personne</h5>
                                                            </div> -->
                                                                        <div class="panel-body">
                                                                            <div id="layoutSidenav_content">
                                                                                <main>
                                                                                    <div class="container-fluid px-4">
                                                                                        <!--    <h1 class="mt-4">Gestion des Competences</h1> -->
                                                                                        <ol class="breadcrumb mb-4">
                                                                                            <li class="breadcrumb-item">
                                                                                                <a href="liste-membres.php">Membres</a>
                                                                                            </li>
                                                                                            <li class="breadcrumb-item active">
                                                                                                Competences
                                                                                            </li>
                                                                                        </ol>
                                                                                        <div class="card mb-4">
                                                                                            <!--   <div class="card-header">
                                                                                    <i class="fas fa-table me-1"></i>
                                                                                    Registered User Details
                                                                                </div> -->
                                                                                            <div class="card-body">
                                                                                                <!-- <table id="datatablesSimple"> -->
                                                                                                <table id="example" class="display" style="width:100%">
                                                                                                    <thead>
                                                                                                        <tr>
                                                                                                            <th>Identifiant
                                                                                                            </th>
                                                                                                            <th>Nom
                                                                                                            </th>
                                                                                                            <th>Commentaire
                                                                                                            </th>
                                                                                                            <th>Supprimer
                                                                                                            </th>
                                                                                                        </tr>
                                                                                                    </thead>
                                                                                                    <tbody>
                                                                                                        <?php $ret = mysqli_query($con, "SELECT * FROM `competences-individu` WHERE `id-indiv` = '$id'");
                                                                                                        $cnt = 1;
                                                                                                        while ($row = mysqli_fetch_array($ret)) { ?>
                                                                                                            <?php
                                                                                                            $id2 = $row['id-comp'];
                                                                                                            $sql2 = mysqli_query($con, "SELECT * FROM `competences` WHERE `id` = '$id2'");
                                                                                                            while ($row2 = mysqli_fetch_array($sql2)) { ?>
                                                                                                                <tr>
                                                                                                                    <td>
                                                                                                                        <?php echo $row2['nom']; ?>
                                                                                                                    </td>
                                                                                                                    <td>
                                                                                                                        <?php echo $row2['commentaire']; ?>
                                                                                                                    </td>
                                                                                                                    <td>
                                                                                                                        <?php echo $row['date']; ?>
                                                                                                                    </td>
                                                                                                                <?php } ?>
                                                                                                                <td>
                                                                                                                    <!--<a href="edit-competences.php?id=<?php echo $row['id']; ?>" class="btn btn-transparent btn-xs" tooltip-placement="top" tooltip="Edit"><i class="fa fa-pencil"></i></a>
                                                                                                                                                    <i class="fas fa-edit"></i></a> -->
                                                                                                                    <a href="ajout-competences.php?id=<?php echo $row['id'] ?>&del=deleteind" onClick="return confirm('Are you sure you want to delete?')" class="btn btn-transparent btn-xs tooltips" tooltip-placement="top" tooltip="Remove"><i class="fa fa-times fa fa-white"></i></a>
                                                                                                                </td>
                                                                                                                </tr>
                                                                                                            <?php $cnt = $cnt + 1;
                                                                                                            } ?>
                                                                                                    </tbody>
                                                                                                </table>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                </main>
                                                                            </div>
                                                                        </div>
                                                                        <form role="form" name="adddoc" method="post" onSubmit="return valid();">
                                                                            <div class="form-group">
                                                                                <label for="compet">
                                                                                    Ajout
                                                                                    Competence
                                                                                </label>
                                                                                <select name="compet" class="form-control" required="true">
                                                                                    <!--		<option value="compet">Select Competence</option> -->
                                                                                    <option value="compet">
                                                                                        Select
                                                                                        Competence
                                                                                    </option>
                                                                                    <?php $ret2 = mysqli_query($con, "select * from competences");
                                                                                    while ($row2 = mysqli_fetch_array($ret2)) {
                                                                                    ?>
                                                                                        <option value="<?php echo htmlentities($row2['id']); ?>">
                                                                                            <?php echo htmlentities($row2['nom']); ?>
                                                                                        </option>
                                                                                    <?php } ?>
                                                                                </select>
                                                                            </div>
                                                                            <button type="submit" name="submit2" id="submit2" class="btn btn-o btn-primary">
                                                                                Ajout Comp
                                                                            </button>
                                                                        </form>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div id="phpE">
                                        <div class="row">
                                            <div class="col-md-12">
                                                <!-- <h5 class="over-title margin-bottom-15">-> <span class="text-bold">Gestion des Competences</span></h5> -->
                                                <div class="container-fluid container-fullw bg-white">
                                                    <div class="row">
                                                        <div class="col-md-12">
                                                            <div class="row margin-top-30">
                                                                <div class="col-lg-8 col-md-12">
                                                                    <div class="panel panel-white">
                                                                        <!--	<div class="panel-heading">
                                                                  <h5 class="panel-title">Ajout Personne</h5>
                                                            </div> -->
                                                                        <div class="panel-body">
                                                                            <div id="layoutSidenav_content">
                                                                                <main>
                                                                                    <div class="container-fluid px-4">
                                                                                        <!--    <h1 class="mt-4">Gestion des Competences</h1> -->
                                                                                        <ol class="breadcrumb mb-4">
                                                                                            <li class="breadcrumb-item">
                                                                                                <a href="liste-membres.php">Membres</a>
                                                                                            </li>
                                                                                            <li class="breadcrumb-item active">
                                                                                                Loisirs
                                                                                            </li>
                                                                                        </ol>
                                                                                        <div class="card mb-4">
                                                                                            <!--   <div class="card-header">
                                                                                    <i class="fas fa-table me-1"></i>
                                                                                    Registered User Details
                                                                                </div> -->
                                                                                            <div class="card-body">
                                                                                                <!-- <table id="datatablesSimple"> -->
                                                                                                <table id="example2" class="display" style="width:100%">
                                                                                                    <thead>
                                                                                                        <tr>
                                                                                                            <th>Identifiant
                                                                                                            </th>
                                                                                                            <th>Nom
                                                                                                            </th>
                                                                                                            <th>Commentaire
                                                                                                            </th>
                                                                                                            <th>Supprimer
                                                                                                            </th>
                                                                                                        </tr>
                                                                                                    </thead>
                                                                                                    <tbody>
                                                                                                        <?php $ret = mysqli_query($con, "SELECT * FROM `loisirs-individu` WHERE `id-indiv` = '$id'");
                                                                                                        $cnt = 1;
                                                                                                        while ($row = mysqli_fetch_array($ret)) { ?>
                                                                                                            <?php
                                                                                                            $id2 = $row['id-lois'];
                                                                                                            $sql2 = mysqli_query($con, "SELECT * FROM `loisirs` WHERE `id` = '$id2'");
                                                                                                            while ($row2 = mysqli_fetch_array($sql2)) { ?>
                                                                                                                <tr>
                                                                                                                    <td>
                                                                                                                        <?php echo $row2['nom']; ?>
                                                                                                                    </td>
                                                                                                                    <td>
                                                                                                                        <?php echo $row2['commentaire']; ?>
                                                                                                                    </td>
                                                                                                                    <td>
                                                                                                                        <?php echo $row['date']; ?>
                                                                                                                    </td>
                                                                                                                <?php } ?>
                                                                                                                <td>
                                                                                                                    <!--<a href="edit-competences.php?id=<?php echo $row['id']; ?>" class="btn btn-transparent btn-xs" tooltip-placement="top" tooltip="Edit"><i class="fa fa-pencil"></i></a>
                                                                                                                                                    <i class="fas fa-edit"></i></a> -->
                                                                                                                    <a href="ajout-loisirs.php?id=<?php echo $row['id'] ?>&del=deleteind" onClick="return confirm('Are you sure you want to delete?')" class="btn btn-transparent btn-xs tooltips" tooltip-placement="top" tooltip="Remove"><i class="fa fa-times fa fa-white"></i></a>
                                                                                                                </td>
                                                                                                                </tr>
                                                                                                            <?php $cnt = $cnt + 1;
                                                                                                            } ?>
                                                                                                    </tbody>
                                                                                                </table>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                </main>
                                                                            </div>
                                                                        </div>
                                                                        <form role="form" name="adddoc" method="post" onSubmit="return valid();">
                                                                            <div class="form-group">
                                                                                <label for="lois">
                                                                                    Ajout
                                                                                    Loisir
                                                                                </label>
                                                                                <select name="lois" class="form-control" required="true">
                                                                                    <!--		<option value="compet">Select Competence</option> -->
                                                                                    <option value="lois">
                                                                                        Choix
                                                                                        du Loisir
                                                                                    </option>
                                                                                    <?php $ret2 = mysqli_query($con, "select * from loisirs");
                                                                                    while ($row2 = mysqli_fetch_array($ret2)) {
                                                                                    ?>
                                                                                        <option value="<?php echo htmlentities($row2['id']); ?>">
                                                                                            <?php echo htmlentities($row2['nom']); ?>
                                                                                        </option>
                                                                                    <?php } ?>
                                                                                </select>
                                                                            </div>
                                                                            <button type="submit" name="submit3" id="submit3" class="btn btn-o btn-primary">
                                                                                Ajout Lois
                                                                            </button>
                                                                        </form>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div id="colE">
                                        <div class="row">
                                            <div class="col-md-12">
                                                <!-- <h5 class="over-title margin-bottom-15">-> <span class="text-bold">Gestion des Competences</span></h5> -->
                                                <div class="container-fluid container-fullw bg-white">
                                                    <div class="row">
                                                        <div class="col-md-12">
                                                            <div class="row margin-top-30">
                                                                <div class="col-lg-8 col-md-12">
                                                                    <div class="panel panel-white">
                                                                        <div class="panel-heading">
                                                                            <h5 class="panel-title">Tombolas</h5>
                                                                        </div>
                                                                        <div class="panel-body">
                                                                            <div id="layoutSidenav_content">
                                                                                <main>
                                                                                    <div class="container-fluid px-4">
                                                                                                <?php
                                                                                                // Sélection du mois / année pour les tickets de tombola de ce membre
                                                                                                $selected_month_tom = isset($_GET['month_tom']) ? intval($_GET['month_tom']) : intval(date('m'));
                                                                                                $selected_year_tom  = isset($_GET['year_tom']) ? intval($_GET['year_tom']) : intval(date('Y'));

                                                                                                // Sécuriser : si valeurs GET invalides, revenir au mois/année courants
                                                                                                if ($selected_month_tom < 1 || $selected_month_tom > 12) {
                                                                                                    $selected_month_tom = intval(date('m'));
                                                                                                }
                                                                                                $current_year_tom = intval(date('Y'));
                                                                                                if ($selected_year_tom < 2023 || $selected_year_tom > $current_year_tom) {
                                                                                                    $selected_year_tom = $current_year_tom;
                                                                                                }
                                                                                                $months_fr = [
                                                                                                    1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
                                                                                                    5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
                                                                                                    9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
                                                                                                ];
                                                                                                ?>
                                                                                                <form method="get" class="form-inline" style="margin-bottom:15px;">
                                                                                                    <input type="hidden" name="id" value="<?php echo intval($id); ?>">
                                                                                                    <div class="form-group" style="margin-right:10px;">
                                                                                                        <label for="month_tom" style="margin-right:5px;">Mois :</label>
                                                                                                        <select name="month_tom" id="month_tom" class="form-control">
                                                                                                            <?php
                                                                                                            for ($m = 1; $m <= 12; $m++) {
                                                                                                                $val = str_pad($m, 2, '0', STR_PAD_LEFT);
                                                                                                                $sel = ($selected_month_tom === $m) ? 'selected' : '';
                                                                                                                echo "<option value='$m' $sel>" . $months_fr[$m] . "</option>";
                                                                                                            }
                                                                                                            ?>
                                                                                                        </select>
                                                                                                    </div>
                                                                                                    <div class="form-group" style="margin-right:10px;">
                                                                                                        <label for="year_tom" style="margin-right:5px;">Année :</label>
                                                                                                        <select name="year_tom" id="year_tom" class="form-control">
                                                                                                            <?php
                                                                                                            for ($y = $current_year_tom; $y >= 2023; $y--) {
                                                                                                                $sel = ($selected_year_tom === $y) ? 'selected' : '';
                                                                                                                echo "<option value='$y' $sel>$y</option>";
                                                                                                            }
                                                                                                            ?>
                                                                                                        </select>
                                                                                                    </div>
                                                                                                    <button type="submit" class="btn btn-primary">Filtrer</button>
                                                                                                </form>
                                                                                        <!--    <h1 class="mt-4">Gestion des Competences</h1> -->
                                                                                        <ol class="breadcrumb mb-4">
                                                                                            <li class="breadcrumb-item">
                                                                                                <?php 
                                                                                                    $jou = mysqli_query($con, "SELECT * FROM `membres` WHERE `id-membre` = $id ");
                                                                                                    while ($rjou = mysqli_fetch_array($jou)) { 
                                                                                                        $nomjou = $rjou['pseudo'];
                                                                                                        ?>
                                                                                                        <a href="voir-membre.php?id=<?php echo $id; ?>"><?php echo $nomjou; ?></a>        
                                                                                                        <?php } ?>
                                                                                            </li>
                                                                                            <li class="breadcrumb-item active">
                                                                                                Tickets de Tombolas
                                                                                            </li>
                                                                                        </ol>
                                                                                        <div class="card mb-4">
                                                                                            <!--   <div class="card-header">
                                                                                    <i class="fas fa-table me-1"></i>
                                                                                    Registered User Details
                                                                                </div> -->
                                                                                            <div class="card-body">
                                                                                                <!-- <table id="datatablesSimple"> -->
                                                                                                <form method="post" id="tombolas_form">
                                                                                                <table id="example4" class="table table-hover w-100">
                                                                                                    <thead>
                                                                                                        <tr>
                                                                                                            <th>QRcode
                                                                                                            </th>
                                                                                                            <th>Valeur
                                                                                                            </th>
                                                                                                            <th>Date
                                                                                                            </th>
                                                                                                            <th>Titre Activité
                                                                                                            </th>
                                                                                                            <th>Réduction Rake
                                                                                                            </th>
                                                                                                            <?php if ($is_admin): ?>
                                                                                                            <th>Supprimer
                                                                                                            </th>
                                                                                                            <?php endif; ?>
                                                                                                        </tr>
                                                                                                    </thead>
                                                                                                    <tbody>
                                                                                                        <?php $ret = mysqli_query($con, "SELECT * FROM `collections-individu` WHERE `id-indiv` = '$id' AND MONTH(`date`) = $selected_month_tom AND YEAR(`date`) = $selected_year_tom ORDER BY `date` DESC");
                                                                                                        $cnt = 1;
                                                                                                        while ($row = mysqli_fetch_array($ret)) { ?>
                                                                                                            <?php
                                                                                                            $id2 = $row['id_col'];
                                                                                                            $col_id = $row['id'];
                                                                                                            $aff_rake_value = isset($row['aff_rake']) ? $row['aff_rake'] : 0;
                                                                                                            $sql2 = mysqli_query($con, "SELECT * FROM `collections` WHERE `id_collection` = '$id2'");
                                                                                                            
                                                                                                            // Récupérer le titre de l'activité correspondant à la date
                                                                                                            $date_collection = $row['date'];
                                                                                                            $sql_activite = mysqli_query($con, "SELECT `titre-activite` FROM `activite` WHERE DATE(`date_depart`) = DATE('$date_collection') LIMIT 1");
                                                                                                            $activite_titre = '';
                                                                                                            if ($sql_activite && mysqli_num_rows($sql_activite) > 0) {
                                                                                                                $row_activite = mysqli_fetch_array($sql_activite);
                                                                                                                $activite_titre = $row_activite['titre-activite'];
                                                                                                            }
                                                                                                            
                                                                                                            while ($row2 = mysqli_fetch_array($sql2)) { ?>
                                                                                                                <tr>
                                                                                                                    <td>
                                                                                                                        <?php echo $row2['nom']; ?>
                                                                                                                    </td>
                                                                                                                    <td>
                                                                                                                        <?php echo $row2['valeur']; ?>
                                                                                                                    </td>
                                                                                                                    <td data-order="<?= strtotime($row['date']) ?>">
                                                                                                                        <?= date('d/m/Y', strtotime($row['date'])) ?>
                                                                                                                    </td>
                                                                                                                    <td>
                                                                                                                        <?php echo $activite_titre ? htmlspecialchars($activite_titre) : '-'; ?>
                                                                                                                    </td>
                                                                                                                    <td>
                                                                                                                        <input type="checkbox" name="aff_rake[]" value="<?php echo $col_id; ?>" class="form-check-input" <?php echo (intval($aff_rake_value) === 1 || $aff_rake_value === '1') ? 'checked' : ''; ?>>
                                                                                                                    </td>
                                                                                                                <?php if ($is_admin): ?>
                                                                                                                <td>
                                                                                                                    <a href="ajout-collection.php?id=<?php echo $row['id'] ?>&del=deleteind" onClick="return confirm('Are you sure you want to delete?')" class="btn btn-transparent btn-xs tooltips" tooltip-placement="top" tooltip="Remove"><i class="fa fa-times fa fa-white"></i></a>
                                                                                                                </td>
                                                                                                                <?php endif; ?>
                                                                                                                </tr>
                                                                                                            <?php } ?>
                                                                                                            <?php $cnt = $cnt + 1;
                                                                                                            } ?>
                                                                                                    </tbody>
                                                                                                </table>
                                                                                                <div style="margin-top: 15px;">
                                                                                                    <button type="submit" name="save_aff_rake" class="btn btn-primary">
                                                                                                        <i class="fa fa-save"></i> Sauvegarder
                                                                                                    </button>
                                                                                                </div>
                                                                                                </form>
                                                                                                
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                </main>
                                                                            </div>
                                                                        </div>
                                                                        <?php if ($is_admin): ?>
                                                                        <form role="form" name="adddoc" method="post" onSubmit="return valid();">
                                                                            <div class="form-group">
                                                                                <label for="col">
                                                                                    Ajout
                                                                                    Collection
                                                                                </label>
                                                                                <select name="col" class="form-control" required="true">
                                                                                    <!--		<option value="compet">Select Competence</option> -->
                                                                                    <option value="col">
                                                                                        Choix
                                                                                        de la Collection
                                                                                    </option>
                                                                                    <?php $ret2 = mysqli_query($con, "select * from collections");
                                                                                    while ($row2 = mysqli_fetch_array($ret2)) {
                                                                                    ?>
                                                                                        <option value="<?php echo htmlentities($row2['id_collection']); ?>">
                                                                                            <?php echo htmlentities($row2['nom']); ?>
                                                                                        </option>
                                                                                    <?php } ?>
                                                                                </select>
                                                                            </div>
                                                                            <button type="submit" name="submit4" id="submit4" class="btn btn-o btn-primary">
                                                                                Ajout Coll.
                                                                            </button>
                                                                        </form>
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
                                    <div id="ksE">
                                        <div class="row">
                                            <div class="col-md-12">
                                                <!-- <h5 class="over-title margin-bottom-15">-> <span class="text-bold">Gestion des Competences</span></h5> -->
                                                <div class="container-fluid container-fullw bg-white">
                                                    <div class="row">
                                                        <div class="col-md-12">
                                                            <div class="row margin-top-30">
                                                                <div class="col-lg-8 col-md-12">
                                                                    <div class="panel panel-white">
                                                                        <div class="panel-heading">
                                                                            <h5 class="panel-title">Activites</h5>
                                                                        </div>
                                                                        <div class="panel-body">
                                                                            <div id="layoutSidenav_content">
                                                                                <main>
                                                                                    <div class="container-fluid px-4">
                                                                                        <!--    <h1 class="mt-4">Gestion des Competences</h1> -->
                                                                                        <ol class="breadcrumb mb-4">
                                                                                            <li class="breadcrumb-item">
                                                                                                <?php 
                                                                                                    $jou = mysqli_query($con, "SELECT * FROM `membres` WHERE `id-membre` = $id ");
                                                                                                    while ($rjou = mysqli_fetch_array($jou)) { 
                                                                                                        $nomjou = $rjou['pseudo'];
                                                                                                        ?>
                                                                                                                                                                                                               <a href="voir-membre.php?id=<?php echo $id; ?>"><?php echo $nomjou; ?></a>        
                                                                                                        <?php } ?>
                                                                                            </li>
                                                                                            <li class="breadcrumb-item active">
                                                                                                Liste des Activités
                                                                                            </li>
                                                                                        </ol>
                                                                                        <div class="card mb-4">
                                                                                            <!--   <div class="card-header">
                                                                                    <i class="fas fa-table me-1"></i>
                                                                                    Registered User Details
                                                                                </div> -->
                                                                                            <div class="card-body">
                                                                                                <!-- <table id="datatablesSimple"> -->
                                                                                                <table id="activiteTable" class="table table-hover w-100">
                                                                                                    <thead>
                                                                                                        <tr>
                                                                                                            <th>Date</th>
                                                                                                            <th>Orga.</th>
                                                                                                            <th>Lieu</th>
                                                                                                            <th>Buyin</th>
                                                                                                            <th>Classt.</th>
                                                                                                            <th>Points</th>
                                                                                                            <th>Edit.</th>
                                                                                                        </tr>
                                                                                                    </thead>
                                                                                                    <tbody>
                                                                                                        <?php $ret = mysqli_query($con, "SELECT * FROM `participation` WHERE `id-membre` = '$id' ORDER BY 'id-participation' ASC");
                                                                                                        $cnt = 1;
                                                                                                        while ($row = mysqli_fetch_array($ret)) { 
                                                                                                            $id2 = $row['id-activite'];
                                                                                                            $sql2 = mysqli_query($con, "SELECT * FROM `activite` WHERE `id-activite` = '$id2' ");
                                                                                                            while ($row2 = mysqli_fetch_array($sql2)) { ?>
                                                                                                                <tr>
                                                                                                                    <td data-order="<?= strtotime($row2['date_depart']) ?>">
                                                                                                                        <?= date('d/m/Y', strtotime($row2['date_depart'])) ?>
                                                                                                                    </td>
                                                                                                                    <td>
                                                                                                                        <?php 
                                                                                                                        $ident = $row2['id-membre'];
                                                                                                                        $org = mysqli_query($con, "SELECT * FROM `membres` WHERE `id-membre` = $ident ");
                                                                                                                        while ($rorg = mysqli_fetch_array($org)) { 
                                                                                                                            $nomorg = $rorg['pseudo'];
                                                                                                                        } ?>
                                                                                                                        <a href="voir-activite.php?uid=<?php echo $row['id-activite']; ?>"><?php echo $nomorg; ?></a>
                                                                                                                    </td>
                                                                                                                    <td>
                                                                                                                        <?php echo $row2['ville']; ?>
                                                                                                                    </td>
                                                                                                                    <td>
                                                                                                                        <?php echo $row2['buyin']; ?>
                                                                                                                    </td>
                                                                                                                    <td>
                                                                                                                        <?php echo $row['classement']; ?>
                                                                                                                    </td>
                                                                                                                    <td>
                                                                                                                        <?php echo $row['points']; ?>
                                                                                                                    </td>
                                                                                                                    <td>
                                                                                                                        <a href="voir-participation.php?id=<?php echo $row['id-participation']; ?>" class="btn btn-transparent btn-xs" tooltip-placement="top" tooltip="Edit">
                                                                                                                            <i class="fa fa-pencil"></i>
                                                                                                                        </a>
                                                                                                                    </td>
                                                                                                                </tr>
                                                                                                                <?php 
                                                                                                                } // Fermeture du while $sql2
                                                                                                            $cnt = $cnt + 1;
                                                                                                            } ?>
                                                                                                    </tbody>
                                                                                                </table>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                </main>
                                                                            </div>
                                                                        </div>
                                                                        <!-- <form role="form" name="adddoc" method="post" onSubmit="return valid();">
                                                                            <div class="form-group">
                                                                                <label for="col">
                                                                                    Ajout
                                                                                    Collection
                                                                                </label>
                                                                                <select name="col" class="form-control" required="true">
                                                                                    
                                                                                    <option value="col">
                                                                                        Choix
                                                                                        de la Collection
                                                                                    </option>
                                                                                    <?php $ret2 = mysqli_query($con, "select * from collections");
                                                                                    while ($row2 = mysqli_fetch_array($ret2)) {
                                                                                    ?>
                                                                                        <option value="<?php echo htmlentities($row2['id_collection']); ?>">
                                                                                            <?php echo htmlentities($row2['nom']); ?>
                                                                                        </option>
                                                                                        $indiv=
                                                                                    <?php } ?>
                                                                                </select>
                                                                            </div>
                                                                            <button type="submit" name="submit4" id="submit4" class="btn btn-o btn-primary">
                                                                                Ajout Coll.
                                                                            </button>
                                                                        </form> -->
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                                                                    </div>

                                    <!-- ══════════ ONGLET TRAK ══════════ -->
                                    <div id="trakE">
                                        <?php
                                        // Connexion dédiée pour le bloc Trak (évite tout état résiduel de $con)
                                        $trak_con = mysqli_connect('localhost', 'root', 'Kookies7*', 'dbs9616600');
                                        if ($trak_con) mysqli_set_charset($trak_con, 'utf8mb4');

                                        // Créer la table si nécessaire
                                        if ($trak_con) mysqli_query($trak_con, "CREATE TABLE IF NOT EXISTS `trak` (
                                            `id`          INT AUTO_INCREMENT PRIMARY KEY,
                                            `id_auteur`   INT NOT NULL,
                                            `id_cible`    INT NOT NULL,
                                            `id_activite` INT NOT NULL DEFAULT 0,
                                            `note`        TEXT NOT NULL,
                                            `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                                            INDEX idx_cible (`id_cible`),
                                            INDEX idx_auteur (`id_auteur`)
                                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

                                        // ID du membre affiché (réaffectation explicite pour éviter écrasement précédent)
                                        $trak_id_cible = intval($_GET['id'] ?? 0);

                                        // Charger toutes les notes sur ce membre
                                        $trak_sql = "
                                            SELECT t.id, t.id_auteur, t.id_activite, t.note, t.created_at,
                                                   COALESCE(m.pseudo,'Inconnu') AS auteur_pseudo,
                                                   COALESCE(a.`titre-activite`,'—') AS titre_activite,
                                                   DATE_FORMAT(a.date_depart,'%d/%m/%Y') AS date_activite
                                            FROM trak t
                                            LEFT JOIN membres m ON t.id_auteur = m.`id-membre`
                                            LEFT JOIN activite a ON t.id_activite = a.`id-activite`
                                            WHERE t.id_cible = " . $trak_id_cible . "
                                            ORDER BY t.created_at DESC";
                                        $trak_res  = $trak_con ? mysqli_query($trak_con, $trak_sql) : false;
                                        $trak_rows = [];
                                        $trak_err  = '';
                                        if ($trak_res) {
                                            while ($trow = mysqli_fetch_assoc($trak_res)) { $trak_rows[] = $trow; }
                                        } else {
                                            $trak_err = $trak_con ? mysqli_error($trak_con) : 'Connexion impossible';
                                        }

                                        // Options uniques pour les filtres
                                        $trak_authors    = array_unique(array_column($trak_rows, 'auteur_pseudo'));
                                        $trak_activities = [];
                                        foreach ($trak_rows as $trow) {
                                            if ($trow['id_activite'] > 0 && !isset($trak_activities[$trow['id_activite']])) {
                                                $trak_activities[$trow['id_activite']] = $trow['date_activite'] . ' — ' . $trow['titre_activite'];
                                            }
                                        }

                                        $session_id_for_trak = (int)$_SESSION['id'];

                                        // ── Notes rédigées PAR ce membre ──
                                        $trak_sql_by = "
                                            SELECT t.id, t.id_auteur, t.id_cible, t.id_activite, t.note, t.created_at,
                                                   COALESCE(mc.pseudo,'Inconnu') AS cible_pseudo,
                                                   COALESCE(a.`titre-activite`,'—') AS titre_activite,
                                                   DATE_FORMAT(a.date_depart,'%d/%m/%Y') AS date_activite
                                            FROM trak t
                                            LEFT JOIN membres mc ON t.id_cible    = mc.`id-membre`
                                            LEFT JOIN activite a  ON t.id_activite = a.`id-activite`
                                            WHERE t.id_auteur = " . $trak_id_cible . "
                                            ORDER BY t.created_at DESC";
                                        $trak_res_by  = $trak_con ? mysqli_query($trak_con, $trak_sql_by) : false;
                                        $trak_rows_by = [];
                                        if ($trak_res_by) {
                                            while ($trow = mysqli_fetch_assoc($trak_res_by)) { $trak_rows_by[] = $trow; }
                                        }

                                        // Filtres "notes rédigées"
                                        $trak_by_cibles = array_unique(array_column($trak_rows_by, 'cible_pseudo'));
                                        $trak_by_acts   = [];
                                        foreach ($trak_rows_by as $trow) {
                                            if ($trow['id_activite'] > 0 && !isset($trak_by_acts[$trow['id_activite']])) {
                                                $trak_by_acts[$trow['id_activite']] = $trow['date_activite'] . ' — ' . $trow['titre_activite'];
                                            }
                                        }
                                        ?>
                                        <div class="wrap-content container" id="container">
                                          <div class="container-fluid container-fullw bg-white">
                                            <div class="col-md-12">
                                              <div class="row margin-top-30">
                                                <div class="panel panel-white">
                                                  <div class="panel-heading">
                                                    <h5 class="panel-title">Notes Trak &mdash;
                                                        <?php
                                                        $nm = mysqli_query($trak_con, "SELECT pseudo FROM membres WHERE `id-membre` = " . $trak_id_cible . " LIMIT 1");
                                                        $trak_pseudo = ($nm && $nr = mysqli_fetch_assoc($nm)) ? $nr['pseudo'] : '';
                                                        echo htmlspecialchars($trak_pseudo);
                                                        ?>
                                                    </h5>
                                                  </div>
                                                  <div class="panel-body">
                                                    <?php if ($trak_err): ?>
                                                    <div class="alert alert-danger" style="font-size:12px">
                                                        ❌ Erreur SQL Trak : <code><?= htmlspecialchars($trak_err) ?></code>
                                                    </div>
                                                    <?php endif; ?>

                                                    <!-- Sous-onglets Bootstrap -->
                                                    <ul class="nav nav-tabs" id="trakSubTabs" style="margin-bottom:14px;">
                                                      <li class="active">
                                                        <a href="#trakTabRecues" data-toggle="tab">
                                                          Notes reçues <span class="badge" style="background:#e74c3c"><?= count($trak_rows) ?></span>
                                                        </a>
                                                      </li>
                                                      <li>
                                                        <a href="#trakTabRedigees" data-toggle="tab">
                                                          Notes rédigées <span class="badge" style="background:#3498db"><?= count($trak_rows_by) ?></span>
                                                        </a>
                                                      </li>
                                                    </ul>

                                                    <div class="tab-content">

                                                      <!-- ══ Onglet 1 : Notes REÇUES ══ -->
                                                      <div class="tab-pane active" id="trakTabRecues">
                                                        <div class="row" style="margin-bottom:10px;gap:0">
                                                          <div class="col-md-3">
                                                            <select id="trakFilterAuteur" class="form-control form-control-sm">
                                                              <option value="">— Tous les auteurs —</option>
                                                              <?php foreach ($trak_authors as $a): ?>
                                                              <option value="<?= htmlspecialchars($a, ENT_QUOTES) ?>"><?= htmlspecialchars($a) ?></option>
                                                              <?php endforeach; ?>
                                                            </select>
                                                          </div>
                                                          <div class="col-md-4">
                                                            <select id="trakFilterActivite" class="form-control form-control-sm">
                                                              <option value="">— Toutes les activités —</option>
                                                              <?php foreach ($trak_activities as $aid => $alabel): ?>
                                                              <option value="<?= (int)$aid ?>"><?= htmlspecialchars($alabel) ?></option>
                                                              <?php endforeach; ?>
                                                            </select>
                                                          </div>
                                                          <div class="col-md-3">
                                                            <input type="text" id="trakFilterTexte" class="form-control form-control-sm" placeholder="Rechercher…">
                                                          </div>
                                                          <div class="col-md-2">
                                                            <button class="btn btn-sm btn-default" id="trakResetFilters">Réinitialiser</button>
                                                          </div>
                                                        </div>
                                                        <p id="trakCount" style="font-size:12px;color:#888;"></p>
                                                        <div class="table-responsive">
                                                          <table class="table table-striped table-sm" style="font-size:14px;">
                                                            <thead><tr>
                                                              <th style="width:130px">Date</th>
                                                              <th style="width:200px">Activité</th>
                                                              <th style="width:120px">Auteur</th>
                                                              <th>Note</th>
                                                              <th style="width:70px">Action</th>
                                                            </tr></thead>
                                                            <tbody id="trakTbody">
                                                              <?php if (empty($trak_rows)): ?>
                                                              <tr><td colspan="5" style="text-align:center;color:#aaa;">Aucune note reçue.</td></tr>
                                                              <?php else: ?>
                                                              <?php foreach ($trak_rows as $trow): ?>
                                                              <tr class="trak-row"
                                                                  data-auteur="<?= htmlspecialchars($trow['auteur_pseudo'], ENT_QUOTES) ?>"
                                                                  data-activite="<?= (int)$trow['id_activite'] ?>"
                                                                  data-note="<?= htmlspecialchars(mb_strtolower($trow['note']), ENT_QUOTES) ?>">
                                                                <td style="white-space:nowrap"><?= htmlspecialchars(date('d/m/Y H:i', strtotime($trow['created_at']))) ?></td>
                                                                <td style="font-size:12px">
                                                                  <?php if ($trow['id_activite'] > 0): ?>
                                                                    <a href="liste-participants-activite.php?id_activite=<?= (int)$trow['id_activite'] ?>" style="font-size:11px">
                                                                      <?= htmlspecialchars($trow['date_activite'] . ' — ' . $trow['titre_activite']) ?>
                                                                    </a>
                                                                  <?php else: ?>&mdash;<?php endif; ?>
                                                                </td>
                                                                <td><strong><?= htmlspecialchars($trow['auteur_pseudo']) ?></strong></td>
                                                                <td style="white-space:pre-wrap"><?= htmlspecialchars($trow['note']) ?></td>
                                                                <td>
                                                                  <?php if ($is_admin || (int)$trow['id_auteur'] === $session_id_for_trak): ?>
                                                                  <button class="btn btn-xs btn-danger trak-delete-btn"
                                                                          data-id="<?= (int)$trow['id'] ?>"
                                                                          title="Supprimer">
                                                                    <i class="fa fa-trash"></i>
                                                                  </button>
                                                                  <?php endif; ?>
                                                                </td>
                                                              </tr>
                                                              <?php endforeach; ?>
                                                              <?php endif; ?>
                                                            </tbody>
                                                          </table>
                                                        </div>
                                                      </div><!-- /tab-pane reçues -->

                                                      <!-- ══ Onglet 2 : Notes RÉDIGÉES ══ -->
                                                      <div class="tab-pane" id="trakTabRedigees">
                                                        <div class="row" style="margin-bottom:10px;gap:0">
                                                          <div class="col-md-3">
                                                            <select id="trakFilterCible" class="form-control form-control-sm">
                                                              <option value="">— Toutes les cibles —</option>
                                                              <?php foreach ($trak_by_cibles as $c): ?>
                                                              <option value="<?= htmlspecialchars($c, ENT_QUOTES) ?>"><?= htmlspecialchars($c) ?></option>
                                                              <?php endforeach; ?>
                                                            </select>
                                                          </div>
                                                          <div class="col-md-4">
                                                            <select id="trakFilterActiviteBy" class="form-control form-control-sm">
                                                              <option value="">— Toutes les activités —</option>
                                                              <?php foreach ($trak_by_acts as $aid => $alabel): ?>
                                                              <option value="<?= (int)$aid ?>"><?= htmlspecialchars($alabel) ?></option>
                                                              <?php endforeach; ?>
                                                            </select>
                                                          </div>
                                                          <div class="col-md-3">
                                                            <input type="text" id="trakFilterTexteBy" class="form-control form-control-sm" placeholder="Rechercher…">
                                                          </div>
                                                          <div class="col-md-2">
                                                            <button class="btn btn-sm btn-default" id="trakResetFiltersBy">Réinitialiser</button>
                                                          </div>
                                                        </div>
                                                        <p id="trakCountBy" style="font-size:12px;color:#888;"></p>
                                                        <div class="table-responsive">
                                                          <table class="table table-striped table-sm" style="font-size:14px;">
                                                            <thead><tr>
                                                              <th style="width:130px">Date</th>
                                                              <th style="width:160px">Sur</th>
                                                              <th style="width:200px">Activité</th>
                                                              <th>Note</th>
                                                              <th style="width:70px">Action</th>
                                                            </tr></thead>
                                                            <tbody id="trakTbodyBy">
                                                              <?php if (empty($trak_rows_by)): ?>
                                                              <tr><td colspan="5" style="text-align:center;color:#aaa;">Aucune note rédigée.</td></tr>
                                                              <?php else: ?>
                                                              <?php foreach ($trak_rows_by as $trow): ?>
                                                              <tr class="trak-row-by"
                                                                  data-cible="<?= htmlspecialchars($trow['cible_pseudo'], ENT_QUOTES) ?>"
                                                                  data-activite="<?= (int)$trow['id_activite'] ?>"
                                                                  data-note="<?= htmlspecialchars(mb_strtolower($trow['note']), ENT_QUOTES) ?>">
                                                                <td style="white-space:nowrap"><?= htmlspecialchars(date('d/m/Y H:i', strtotime($trow['created_at']))) ?></td>
                                                                <td>
                                                                  <a href="voir-membre.php?id=<?= (int)$trow['id_cible'] ?>&tab=trak">
                                                                    <?= htmlspecialchars($trow['cible_pseudo']) ?>
                                                                  </a>
                                                                </td>
                                                                <td style="font-size:12px">
                                                                  <?php if ($trow['id_activite'] > 0): ?>
                                                                    <a href="liste-participants-activite.php?id_activite=<?= (int)$trow['id_activite'] ?>" style="font-size:11px">
                                                                      <?= htmlspecialchars($trow['date_activite'] . ' — ' . $trow['titre_activite']) ?>
                                                                    </a>
                                                                  <?php else: ?>&mdash;<?php endif; ?>
                                                                </td>
                                                                <td style="white-space:pre-wrap"><?= htmlspecialchars($trow['note']) ?></td>
                                                                <td>
                                                                  <?php if ($is_admin || (int)$trow['id_auteur'] === $session_id_for_trak): ?>
                                                                  <button class="btn btn-xs btn-danger trak-delete-btn"
                                                                          data-id="<?= (int)$trow['id'] ?>"
                                                                          title="Supprimer">
                                                                    <i class="fa fa-trash"></i>
                                                                  </button>
                                                                  <?php endif; ?>
                                                                </td>
                                                              </tr>
                                                              <?php endforeach; ?>
                                                              <?php endif; ?>
                                                            </tbody>
                                                          </table>
                                                        </div>
                                                      </div><!-- /tab-pane rédigées -->

                                                    </div><!-- /tab-content -->
                                                  </div><!-- panel-body -->
                                                </div><!-- panel -->
                                              </div>
                                            </div>
                                          </div>
                                        </div>
                                    </div><!-- end trakE -->
                                </div> <!-- end bSection -->
                            </div> <!-- end auCentre -->
                        </div> <!-- end contenu -->
                    </div> <!-- end conteneur -->
                </div> <!-- end wrap-content container -->
            </div> <!-- end main-content -->
        </div> <!-- end app-content -->
    </div> <!-- end app -->





    <!-- start: FOOTER -->
    <?php include('include/footer.php'); ?>
    <!-- end: FOOTER -->
    
    <!-- start: SETTINGS -->
    <?php include('include/setting.php'); ?>
    <!-- end: SETTINGS -->

    <!-- start: MAIN JAVASCRIPTS -->
    <script src="vendor/jquery/jquery.min.js"></script>
    <script src="vendor/bootstrap/js/bootstrap.min.js"></script>
    <script src="vendor/modernizr/modernizr.js"></script>
    <script src="vendor/jquery-cookie/jquery.cookie.js"></script>
    <script src="vendor/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="vendor/switchery/switchery.min.js"></script>
    <!-- <script src="https://code.jquery.com/jquery-3.7.0.js"></script> -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <!-- end: MAIN JAVASCRIPTS -->
    <!-- start: JAVASCRIPTS REQUIRED FOR THIS PAGE ONLY -->
    <script src="vendor/maskedinput/jquery.maskedinput.min.js"></script>
    <script src="vendor/bootstrap-touchspin/jquery.bootstrap-touchspin.min.js"></script>
    <script src="vendor/autosize/autosize.min.js"></script>
    <script src="vendor/selectFx/classie.js"></script>
    <script src="vendor/selectFx/selectFx.js"></script>
    <script src="vendor/select2/select2.min.js"></script>
    <script src="vendor/bootstrap-datepicker/bootstrap-datepicker.min.js"></script>
    <script src="vendor/bootstrap-timepicker/bootstrap-timepicker.min.js"></script>
    <!-- end: JAVASCRIPTS REQUIRED FOR THIS PAGE ONLY -->
    <!-- start: CLIP-TWO JAVASCRIPTS -->
    <script src="assets/js/main.js"></script>
    <!-- start: JavaScript Event Handlers for this page -->
    <script src="assets/js/form-elements.js"></script>
    <script>
        jQuery(document).ready(function() {
            Main.init();
            FormElements.init();
        });
    </script>
    <!-- end: JavaScript Event Handlers for this page -->
    <!-- end: CLIP-TWO JAVASCRIPTS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.0/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous">
    </script>
    <script src="../js/scripts.js"></script>



    <script type="text/javascript" language="javascript">
        function afficher(id) {
            var leCalque = document.getElementById(id);
            var leCalqueE = document.getElementById(id + "E");

            // Reset all sections
            document.getElementById("cssE").className = "rubrique bgImg";
            document.getElementById("css2E").className = "rubrique bgImg";
            document.getElementById("css3E").className = "rubrique bgImg";
            document.getElementById("jsE").className = "rubrique bgImg";
            document.getElementById("ksE").className = "rubrique bgImg";
            document.getElementById("portefeuilleE").className = "rubrique bgImg";
            document.getElementById("phpE").className = "rubrique bgImg";
            document.getElementById("colE").className = "rubrique bgImg";
            document.getElementById("trakE").className = "rubrique bgImg";

            // Reset all nav buttons
            document.getElementById("css").className = "btnnav";
            document.getElementById("css2").className = "btnnav";
            document.getElementById("css3").className = "btnnav";
            document.getElementById("js").className = "btnnav";
            document.getElementById("ks").className = "btnnav";
            document.getElementById("portefeuille").className = "btnnav";
            document.getElementById("php").className = "btnnav";
            document.getElementById("col").className = "btnnav";
            document.getElementById("trak").className = "btnnav";

            // Show selected section
            leCalqueE.className = "rubrique bgImg montrer";
            leCalque.className = "btnnavA";

            // Init/redraw DataTables when their tab becomes visible
            if (id === 'ks') {
                if (activiteDataTable === null) {
                    activiteDataTable = $('#activiteTable').DataTable({
                        language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/fr-FR.json' },
                        pageLength: 8,
                        order: [[0, 'desc']]
                    });
                    $('#activiteTable').on('click', 'tr.clickable-row', function() {
                        window.location.href = 'voir-membre.php?id=' + $(this).data('id');
                    });
                } else {
                    activiteDataTable.columns.adjust().draw();
                }
            }
            if (id === 'portefeuille' && typeof $.fn.dataTable !== 'undefined') {
                if ($.fn.dataTable.isDataTable('#transactionsTable')) {
                    $('#transactionsTable').DataTable().columns.adjust().draw();
                }
            }
        }

        // ══════════════════════════════════════════════
        //  TRAK – filtres & suppression (2 sous-onglets)
        // ══════════════════════════════════════════════
        (function() {

            // ── Onglet "Notes reçues" ──
            function applyTrakFilters() {
                var auteur   = document.getElementById('trakFilterAuteur')   ? document.getElementById('trakFilterAuteur').value   : '';
                var activite = document.getElementById('trakFilterActivite') ? document.getElementById('trakFilterActivite').value : '';
                var texte    = document.getElementById('trakFilterTexte')    ? document.getElementById('trakFilterTexte').value.toLowerCase() : '';
                var rows     = document.querySelectorAll('#trakTbody .trak-row');
                var visible  = 0;
                rows.forEach(function(row) {
                    var ok = true;
                    if (auteur   && row.dataset.auteur   !== auteur)                  ok = false;
                    if (activite && String(row.dataset.activite) !== String(activite)) ok = false;
                    if (texte    && row.dataset.note.indexOf(texte) === -1)           ok = false;
                    row.style.display = ok ? '' : 'none';
                    if (ok) visible++;
                });
                var cnt = document.getElementById('trakCount');
                if (cnt) cnt.textContent = visible + ' note(s) affichée(s) sur ' + rows.length;
            }

            // ── Onglet "Notes rédigées" ──
            function applyTrakFiltersBy() {
                var cible    = document.getElementById('trakFilterCible')      ? document.getElementById('trakFilterCible').value      : '';
                var activite = document.getElementById('trakFilterActiviteBy') ? document.getElementById('trakFilterActiviteBy').value : '';
                var texte    = document.getElementById('trakFilterTexteBy')    ? document.getElementById('trakFilterTexteBy').value.toLowerCase() : '';
                var rows     = document.querySelectorAll('#trakTbodyBy .trak-row-by');
                var visible  = 0;
                rows.forEach(function(row) {
                    var ok = true;
                    if (cible    && row.dataset.cible    !== cible)                    ok = false;
                    if (activite && String(row.dataset.activite) !== String(activite)) ok = false;
                    if (texte    && row.dataset.note.indexOf(texte) === -1)            ok = false;
                    row.style.display = ok ? '' : 'none';
                    if (ok) visible++;
                });
                var cnt = document.getElementById('trakCountBy');
                if (cnt) cnt.textContent = visible + ' note(s) affichée(s) sur ' + rows.length;
            }

            document.addEventListener('DOMContentLoaded', function() {
                // Filtres onglet 1
                var fa = document.getElementById('trakFilterAuteur');
                var fv = document.getElementById('trakFilterActivite');
                var ft = document.getElementById('trakFilterTexte');
                var fr = document.getElementById('trakResetFilters');
                if (fa) fa.addEventListener('change', applyTrakFilters);
                if (fv) fv.addEventListener('change', applyTrakFilters);
                if (ft) ft.addEventListener('input',  applyTrakFilters);
                if (fr) fr.addEventListener('click', function() {
                    if (fa) fa.value = '';
                    if (fv) fv.value = '';
                    if (ft) ft.value = '';
                    applyTrakFilters();
                });

                // Filtres onglet 2
                var fc  = document.getElementById('trakFilterCible');
                var fvb = document.getElementById('trakFilterActiviteBy');
                var ftb = document.getElementById('trakFilterTexteBy');
                var frb = document.getElementById('trakResetFiltersBy');
                if (fc)  fc.addEventListener('change',  applyTrakFiltersBy);
                if (fvb) fvb.addEventListener('change', applyTrakFiltersBy);
                if (ftb) ftb.addEventListener('input',  applyTrakFiltersBy);
                if (frb) frb.addEventListener('click', function() {
                    if (fc)  fc.value  = '';
                    if (fvb) fvb.value = '';
                    if (ftb) ftb.value = '';
                    applyTrakFiltersBy();
                });

                // Compteurs initiaux
                applyTrakFilters();
                applyTrakFiltersBy();

                // Suppression (fonctionne pour les deux onglets)
                document.addEventListener('click', function(e) {
                    var btn = e.target.closest('.trak-delete-btn');
                    if (!btn) return;
                    if (!confirm('Supprimer cette note ?')) return;
                    var noteId = btn.dataset.id;
                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', 'delete_trak.php', true);
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    xhr.onload = function() {
                        try {
                            var resp = JSON.parse(xhr.responseText);
                            if (resp.success) {
                                var row = btn.closest('tr');
                                row.parentNode.removeChild(row);
                                applyTrakFilters();
                                applyTrakFiltersBy();
                            } else {
                                alert('Erreur : ' + (resp.error || 'inconnue'));
                            }
                        } catch(ex) {
                            alert('Erreur serveur.');
                        }
                    };
                    xhr.send('id=' + encodeURIComponent(noteId));
                });
            });
        })();
    </script>
    <script type="text/javascript" language="javascript">
        <?php if(isset($_GET['tab'])) { ?>
            afficher('<?php echo htmlentities($_GET['tab']); ?>');
        <?php } else { ?>
            afficher('css');
        <?php } ?>
    </script>
    <script>
        var activiteDataTable = null;
    </script>
    <script>
$(document).ready(function() {
    $('#transactionsTable').DataTable({
        language: { 
            url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/fr-FR.json' 
        },
        order: [[0, 'desc']],
        pageLength: 5,
        responsive: true
    });
});
</script>
</body>

</html>
