<?php
session_start();
// --- ENABLE ERROR REPORTING FOR DEBUGGING ---
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);
error_reporting(0); // Production setting

include('include/config.php');// Ensure DB connection ($con)

// --- Handle AJAX requests for bonus savings ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $json_input = json_decode(file_get_contents('php://input'), true);
    
    // Handle save_jetons_total (individual row)
    if (isset($json_input['action']) && $json_input['action'] === 'save_jetons_total') {
        header('Content-Type: application/json');
        error_log('AJAX save_jetons_total: ' . json_encode($json_input));
        
        if (!isset($json_input['id_participation']) || !isset($json_input['jetons_total'])) {
            die(json_encode(['success' => false, 'message' => 'Missing parameters']));
        }
        
        $id_part = intval($json_input['id_participation']);
        $jetons_total = intval($json_input['jetons_total']);
        $bonus_ins = intval($json_input['jetons_bonus_ins'] ?? 0);
        $bonus_arrivee = intval($json_input['jetons_bonus_arrivee'] ?? 0);
        
        $update_query = "UPDATE participation SET 
            jetons_bonus_ins = ?,
            jetons_bonus_arrivee = ?,
            jetons_total = ?
            WHERE `id-participation` = ?";
        
        $stmt = mysqli_prepare($con, $update_query);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "iiii", $bonus_ins, $bonus_arrivee, $jetons_total, $id_part);
            if (mysqli_stmt_execute($stmt)) {
                error_log("Updated participation $id_part: bonus_ins=$bonus_ins, bonus_arrivee=$bonus_arrivee, jetons_total=$jetons_total");
                die(json_encode(['success' => true, 'message' => 'Updated']));
            } else {
                error_log("Update failed: " . mysqli_error($con));
                die(json_encode(['success' => false, 'message' => mysqli_error($con)]));
            }
            mysqli_stmt_close($stmt);
        }
        
        die(json_encode(['success' => false, 'message' => 'Prepare failed']));
    }
    
    // Handle save_bonus_ins (bulk save for all rows)
    if (isset($json_input['action']) && $json_input['action'] === 'save_bonus_ins') {
        header('Content-Type: application/json');
        error_log('AJAX save_bonus_ins received: ' . json_encode($json_input));
        
        if (!isset($json_input['data']) || !is_array($json_input['data'])) {
            error_log('Invalid data in save_bonus_ins request');
            die(json_encode(['success' => false, 'message' => 'Invalid data']));
        }
        
        $success_count = 0;
        $error_count = 0;
        
        // Fetch jetons from activite and bonus_arrivee, then update bonus_ins and recalculate jetons_total
        $fetch_query = "SELECT a.jetons, p.jetons_bonus_arrivee FROM participation p
                        JOIN activite a ON p.`id-activite` = a.`id-activite`
                        WHERE p.`id-participation` = ?";
        $update_query = "UPDATE participation SET 
            jetons_bonus_ins = ?,
            jetons_total = ?
            WHERE `id-participation` = ?";
        
        $stmt_fetch = mysqli_prepare($con, $fetch_query);
        $stmt_update = mysqli_prepare($con, $update_query);
        
        if ($stmt_fetch && $stmt_update) {
            foreach ($json_input['data'] as $item) {
                if (!isset($item['id_participation']) || !isset($item['jetons_bonus_ins'])) {
                    $error_count++;
                    continue;
                }
                
                $bonus_ins = intval($item['jetons_bonus_ins']);
                $id_part = intval($item['id_participation']);
                
                // Cap at 5000
                if ($bonus_ins > 5000) {
                    $bonus_ins = 5000;
                }
                
                // Fetch activite.jetons and bonus_arrivee from participation
                mysqli_stmt_bind_param($stmt_fetch, "i", $id_part);
                mysqli_stmt_execute($stmt_fetch);
                $res = mysqli_stmt_get_result($stmt_fetch);
                $row = mysqli_fetch_assoc($res);
                
                if ($row) {
                    $jetons = intval($row['jetons']);
                    $bonus_arrivee = intval($row['jetons_bonus_arrivee']);
                    $jetons_total = $jetons + $bonus_ins + $bonus_arrivee;
                    
                    error_log("Updating participation $id_part: activite.jetons=$jetons, bonus_ins=$bonus_ins, bonus_arrivee=$bonus_arrivee, jetons_total=$jetons_total");
                    
                    // Update both bonus_ins and jetons_total
                    mysqli_stmt_bind_param($stmt_update, "iii", $bonus_ins, $jetons_total, $id_part);
                    if (mysqli_stmt_execute($stmt_update)) {
                        $success_count++;
                    } else {
                        $error_count++;
                        error_log("Update failed for participation $id_part: " . mysqli_error($con));
                    }
                } else {
                    $error_count++;
                    error_log("Could not fetch participation $id_part");
                }
            }
            mysqli_stmt_close($stmt_fetch);
            mysqli_stmt_close($stmt_update);
        } else {
            error_log("Prepare statement failed: " . mysqli_error($con));
        }
        
        error_log("Save complete: $success_count success, $error_count errors");
        die(json_encode(['success' => true, 'updated' => $success_count, 'errors' => $error_count]));
    }
}

// --- Initial Variable Setup & Defaults ---
$gid_part = isset($_GET['part']) ? intval($_GET['part']) : null;
$gid_acti = isset($_GET['acti']) ? intval($_GET['acti']) : null;
$gid_tabl = isset($_GET['tabl']) ? intval($_GET['tabl']) : null;
$gid_sieg = isset($_GET['sieg']) ? intval($_GET['sieg']) : null;
$source = isset($_GET['sour']) ? $_GET['sour'] : '';
$actu = strtotime(date("Y-m-d H:i:s"));
$actu2 = date("Y-m-d");

// --- Handle Activity Choice Submission ---
if (isset($_POST['submitchoixact'])) {
    $acti_choice = $_POST['acti'];
    if ($acti_choice != '-Anonyme-' && filter_var($acti_choice, FILTER_VALIDATE_INT)) {
        $_SESSION['selected_activity'] = intval($acti_choice);
    } else {
         unset($_SESSION['selected_activity']);
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// --- Handle Quick Player Creation ---
if (isset($_POST['submitcreaj'])) {
    $pseudo = trim($_POST['pseudo']);
    $fname = trim($_POST['fname']);
    $auto_register = isset($_POST['auto_register']) && $_POST['auto_register'] == '1';
    $jetons_crea = isset($_POST['jetons_crea']) && $_POST['jetons_crea'] !== '' ? intval($_POST['jetons_crea']) : 0;
    $selected_activity_id = isset($_SESSION['selected_activity']) ? intval($_SESSION['selected_activity']) : null;

    if (!empty($pseudo)) {

        // If first name is empty, default it to the pseudo
        if ($fname === '') {
            $fname = $pseudo;
        }

        // Check if pseudo already exists (with whitespace handling)
        $check_pseudo_sql = "SELECT `id-membre` FROM `membres` WHERE LOWER(TRIM(`pseudo`)) = LOWER(TRIM(?))";
        $stmt_check = mysqli_prepare($con, $check_pseudo_sql);
        if ($stmt_check) {
            mysqli_stmt_bind_param($stmt_check, "s", $pseudo);
            if (mysqli_stmt_execute($stmt_check)) {
                mysqli_stmt_store_result($stmt_check);
                if (mysqli_stmt_num_rows($stmt_check) > 0) {
                    $_SESSION['feedback'] = "<div class='alert alert-warning'>Le pseudo '" . htmlspecialchars($pseudo) . "' existe déjà.</div>";
                } else {
                    // Create new player
                    $sql_create_player = "INSERT INTO `membres` (`pseudo`, `fname`) VALUES (?, ?)";
                    $stmt_create = mysqli_prepare($con, $sql_create_player);
                    if ($stmt_create) {
                        mysqli_stmt_bind_param($stmt_create, "ss", $pseudo, $fname);
                        if (mysqli_stmt_execute($stmt_create)) {
                            $new_player_id = mysqli_insert_id($con);
                            
                            // Auto-register to activity if requested
                            if ($auto_register && $selected_activity_id) {
                                // Fetch activite defaults
                                $act_jetons = 0;
                                $act_rake = 0.0;
                                $act_buyin = 0.0;
                                $act_bounty = 0.0;
                                $act_date_depart = "";
                                $stmtA = mysqli_prepare($con, "SELECT jetons, rake, buyin, bounty, date_depart FROM activite WHERE `id-activite` = ? LIMIT 1");
                                if ($stmtA) {
                                    mysqli_stmt_bind_param($stmtA, "i", $selected_activity_id);
                                    mysqli_stmt_execute($stmtA);
                                    $resA = mysqli_stmt_get_result($stmtA);
                                    if ($rowA = mysqli_fetch_assoc($resA)) {
                                        $act_jetons = intval($rowA['jetons'] ?? 0);
                                        $act_rake = floatval($rowA['rake'] ?? 0);
                                        $act_buyin = floatval($rowA['buyin'] ?? 0);
                                        $act_bounty = floatval($rowA['bounty'] ?? 0);
                                        $act_date_depart = $rowA['date_depart'];
                                    }
                                    mysqli_stmt_close($stmtA);
                                }

                                // Calculate bonus values
                                $initial_cout_in = $act_buyin + $act_bounty + $act_rake + 5;
                                $bonus_ins = 0;
                                if (!empty($act_date_depart)) {
                                    $diff_minutes = abs(strtotime($act_date_depart) - time()) / 60;
                                    $bonus_ins = min(5000, 200 * floor($diff_minutes / 60));
                                }
                                $jetons_total = $act_jetons + $bonus_ins;

                                // Insert participation with bonus values
                                $register_sql = "INSERT INTO `participation` (`id-membre`, `nom-membre`, `id-activite`, `id-table`, `id-siege`, `jetons`, `rake`, `cout_in`, `challenger`, `jetons_bonus_ins`, `jetons_total`) VALUES (?, ?, ?, 1, 1, ?, ?, ?, 1, ?, ?)";
                                $stmt_register = mysqli_prepare($con, $register_sql);
                                if ($stmt_register) {
                                    mysqli_stmt_bind_param($stmt_register, "issiidii", $new_player_id, $pseudo, $selected_activity_id, $act_jetons, $act_rake, $initial_cout_in, $bonus_ins, $jetons_total);
                                    if (mysqli_stmt_execute($stmt_register)) {
                                        $_SESSION['feedback'] = "<div class='alert alert-success'>Joueur créé et inscrit à l'activité : " . htmlspecialchars($pseudo) . " (Bonus: $bonus_ins)</div>";
                                    } else {
                                        $_SESSION['feedback'] = "<div class='alert alert-warning'>Joueur créé mais erreur lors de l'inscription à l'activité : " . htmlspecialchars(mysqli_stmt_error($stmt_register)) . "</div>";
                                    }
                                    mysqli_stmt_close($stmt_register);
                                }
                            } else {
                                $_SESSION['feedback'] = "<div class='alert alert-success'>Joueur créé : " . htmlspecialchars($pseudo) . "</div>";
                            }
                        } else {
                            $_SESSION['feedback'] = "<div class='alert alert-danger'>Erreur création joueur : " . htmlspecialchars(mysqli_stmt_error($stmt_create)) . "</div>";
                        }
                        mysqli_stmt_close($stmt_create);
                    }
                }
                mysqli_stmt_close($stmt_check);
            }
        }
    } else {
        $_SESSION['feedback'] = "<div class='alert alert-warning'>Le pseudo est requis pour la création rapide.</div>";
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// --- Handle Quick Registration (Initializes rake/cout_in from activity) ---
if (isset($_POST['submit'])) {
    $membre = isset($_POST['membre']) && $_POST['membre'] != '' ? intval($_POST['membre']) : null;
    // Use the current activity selected in session; do not accept activity from POST
    $acti = isset($_SESSION['selected_activity']) ? intval($_SESSION['selected_activity']) : null;
    // Do not request table/seat/jetons in quick registration; use defaults
    $tabl = 0;
    $sieg = 0;
    $jetons_insc = 0;

    if ($membre && $acti) {
        $check_sql = "SELECT `id-participation` FROM `participation` WHERE `id-membre` = ? AND `id-activite` = ?";
        $stmt_check = mysqli_prepare($con, $check_sql);
        mysqli_stmt_bind_param($stmt_check, "ii", $membre, $acti);
        mysqli_stmt_execute($stmt_check);
        mysqli_stmt_store_result($stmt_check);

        if (mysqli_stmt_num_rows($stmt_check) == 0) {
            mysqli_stmt_close($stmt_check);

            $default_activity_rake = 0.0;
            $default_activity_buyin = 0.0;
            $default_activity_bounty = 0.0;
            $default_activity_date_depart = "";
            $fetch_defaults_sql = "SELECT rake, buyin, bounty, date_depart FROM activite WHERE `id-activite` = ?";
            $stmt_fetch_defaults = mysqli_prepare($con, $fetch_defaults_sql);
            if ($stmt_fetch_defaults) {
                mysqli_stmt_bind_param($stmt_fetch_defaults, "i", $acti);
                if (mysqli_stmt_execute($stmt_fetch_defaults)) {
                    $defaults_result = mysqli_stmt_get_result($stmt_fetch_defaults);
                    if ($defaults_row = mysqli_fetch_assoc($defaults_result)) {
                        $default_activity_rake = floatval($defaults_row['rake'] ?? 0.0);
                        $default_activity_bounty = floatval($defaults_row['bounty'] ?? 0.0);
                        $default_activity_buyin = floatval($defaults_row['buyin'] ?? 0.0);
                        $default_activity_date_depart = $defaults_row['date_depart'] ?? "";
                    }
                     mysqli_free_result($defaults_result);
                } else { error_log("Erreur execution fetch defaults pour activité ID $acti: " . mysqli_stmt_error($stmt_fetch_defaults)); }
                mysqli_stmt_close($stmt_fetch_defaults);
            } else { error_log("Erreur préparation fetch defaults pour activité ID $acti: " . mysqli_error($con)); }

            // Calculate cout_in = buyin + bounty + rake + 5 (challenger by default)
            $initial_cout_in = $default_activity_buyin + $default_activity_bounty + $default_activity_rake + 5;

            // Récupérer le pseudo du membre pour remplir nom-membre dans participation
            $pseudo = '';
            $stmt_pseudo = mysqli_prepare($con, "SELECT `pseudo` FROM `membres` WHERE `id-membre` = ? LIMIT 1");
            if ($stmt_pseudo) {
                mysqli_stmt_bind_param($stmt_pseudo, "i", $membre);
                mysqli_stmt_execute($stmt_pseudo);
                $res_pseudo = mysqli_stmt_get_result($stmt_pseudo);
                if ($res_pseudo && $row_p = mysqli_fetch_assoc($res_pseudo)) {
                    $pseudo = $row_p['pseudo'];
                }
                mysqli_free_result($res_pseudo);
                mysqli_stmt_close($stmt_pseudo);
            }

            // Calcul bonus inscription
            $bonus_ins_q = 0;
            if (!empty($default_activity_date_depart)) {
                $diff_min_q = abs(strtotime($default_activity_date_depart) - time()) / 60;
                $bonus_ins_q = min(5000, 200 * floor($diff_min_q / 60));
            }
            $jetons_total_q = $jetons_insc + $bonus_ins_q;

            // Insérer en renseignant nom-membre (pseudo) et id-membre, challenger = 1 par défaut, avec le nombre de jetons saisi
            $sql_quick_reg = "INSERT INTO `participation` (`id-membre`, `nom-membre`, `id-activite`, `id-table`, `id-siege`, rake, cout_in, challenger, jetons, jetons_bonus_ins, jetons_total) VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?)";

              $stmt_reg = mysqli_prepare($con, $sql_quick_reg);
              if ($stmt_reg) {
                  mysqli_stmt_bind_param($stmt_reg, "isiiiddiii", $membre, $pseudo, $acti, $tabl, $sieg, $default_activity_rake, $initial_cout_in, $jetons_insc, $bonus_ins_q, $jetons_total_q);
                 if (mysqli_stmt_execute($stmt_reg)) {
                     $insert_id = mysqli_insert_id($con);

                     $_SESSION['feedback'] = "<div class='alert alert-success'>Inscription rapide réussie : Joueur ID = $membre (".htmlspecialchars($pseudo)."), Activité ID = $acti, Table = $tabl, Siège = $sieg. (Bonus: $bonus_ins_q, Total Jetons: $jetons_total_q)</div>";
                 } else {
                     $_SESSION['feedback'] = "<div class='alert alert-danger'>Erreur inscription rapide: " . htmlspecialchars(mysqli_stmt_error($stmt_reg)) . "</div>";
                 }
                 mysqli_stmt_close($stmt_reg);
            } else {
                $_SESSION['feedback'] = "<div class='alert alert-danger'>Erreur préparation inscription: " . htmlspecialchars(mysqli_error($con)) . "</div>";
            }
        } else {
            mysqli_stmt_close($stmt_check);
            $_SESSION['feedback'] = "<div class='alert alert-warning'>Ce joueur est déjà inscrit à cette activité.</div>";
        }
    } else { $_SESSION['feedback'] = "<div class='alert alert-warning'>Veuillez sélectionner un joueur et une activité valides pour l'inscription rapide.</div>"; }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}


// --- Handle Quick Deletion ---
if (isset($_POST['submitsupc'])) {
    $membre = isset($_POST['membresup']) && $_POST['membresup'] != '' ? intval($_POST['membresup']) : null;
    // Prefer activity selected in session; fall back to POST if session missing
    $acti = isset($_SESSION['selected_activity']) ? intval($_SESSION['selected_activity']) : (isset($_POST['actisup']) && $_POST['actisup'] != '' ? intval($_POST['actisup']) : null);
    if ($membre && $acti) {
        $sql_quick_del = "DELETE FROM `participation` WHERE `id-membre` = ? AND `id-activite` = ?";
        $stmt_del = mysqli_prepare($con, $sql_quick_del);
        if ($stmt_del) {
            mysqli_stmt_bind_param($stmt_del, "ii", $membre, $acti);
            if (mysqli_stmt_execute($stmt_del)) {
                $affected_rows = mysqli_stmt_affected_rows($stmt_del);
                if ($affected_rows > 0) { $_SESSION['feedback'] = "<div class='alert alert-success'>Suppression rapide réussie: Participation pour Joueur ID = $membre, Activité ID = $acti supprimée.</div>"; }
                else { $_SESSION['feedback'] = "<div class='alert alert-info'>Aucune participation trouvée pour Joueur ID = $membre et Activité ID = $acti à supprimer.</div>"; }
            } else { $_SESSION['feedback'] = "<div class='alert alert-danger'>Erreur exécution suppression rapide: " . htmlspecialchars(mysqli_stmt_error($stmt_del)) . "</div>"; }
            mysqli_stmt_close($stmt_del);
        } else { $_SESSION['feedback'] = "<div class='alert alert-danger'>Erreur préparation suppression rapide: " . htmlspecialchars(mysqli_error($con)) . "</div>"; }
    } else { $_SESSION['feedback'] = "<div class='alert alert-warning'>Veuillez sélectionner un joueur et une activité valides pour la suppression rapide.</div>"; }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}


// --- Handle form submission for updating participations ---
if (isset($_POST['update_participation'])) {
    if (!isset($_SESSION['selected_activity'])) {
        $_SESSION['feedback'] = "<div class='alert alert-warning'>Veuillez sélectionner une activité.</div>";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    try {
        // Mise à jour des participations
        $updated_member_ids = [];

        $sql_update = "UPDATE participation SET 
            `challenger` = ?,
            `id-table` = ?,
            `id-siege` = ?,
            `rake` = ?,
            `recave` = ?,
            `classement` = ?,
            `tf` = ?,
            `win` = ?,
            `remise` = ?,
            `gain` = ?,
            `points` = ?,
            `cagnotte` = ?,
            `jetons` = ?,
            `cout_in` = ?,
            `jetons_bonus_ins` = ?,
            `jetons_bonus_arrivee` = ?,
            `jetons_total` = ?
            WHERE `id-participation` = ?";

        $stmt_update = mysqli_prepare($con, $sql_update);
        // Prepared SELECT to fetch current values for comparison, JOINed with activite to get current buyin
        $sql_select_current = "SELECT p.challenger, p.`id-table`, p.`id-siege`, p.rake, p.recave, p.classement, p.tf, p.win, p.remise, p.gain, p.points, p.cagnotte, p.cout_in, p.jetons_bonus_ins, p.jetons_bonus_arrivee, p.jetons_total, a.buyin as activite_buyin, p.`id-membre` 
                               FROM participation p 
                               JOIN activite a ON p.`id-activite` = a.`id-activite` 
                               WHERE p.`id-participation` = ?";
        $stmt_select_current = mysqli_prepare($con, $sql_select_current);
        
        if ($stmt_update && $stmt_select_current) {
            foreach ($_POST['participations'] as $participation) {
                if (!isset($participation['id_participation'])) continue;

                // Fetch current DB values FIRST
                mysqli_stmt_bind_param($stmt_select_current, "i", $participation['id_participation']);
                mysqli_stmt_execute($stmt_select_current);
                $res_cur = mysqli_stmt_get_result($stmt_select_current);
                $cur = $res_cur ? mysqli_fetch_assoc($res_cur) : null;
                mysqli_free_result($res_cur);

                if (!$cur) continue;

                // Posted values (with fallbacks to current DB if missing)
                $challenger = isset($participation['challenger']) ? 1 : 0;
                $table = isset($participation['table']) ? intval($participation['table']) : intval($cur['id-table']);
                $siege = isset($participation['siege']) ? intval($participation['siege']) : intval($cur['id-siege']);
                $rake = isset($participation['rake']) ? floatval($participation['rake']) : floatval($cur['rake'] ?? 0);
                $recave = isset($participation['recave']) ? intval($participation['recave']) : intval($cur['recave'] ?? 0);
                $classement = (isset($participation['classement']) && $participation['classement'] !== '') ? intval($participation['classement']) : 0;
                $tf = isset($participation['tf']) ? 1 : 0;
                $win = isset($participation['win']) ? 1 : 0;
                $remise = isset($participation['remise']) ? 1 : 0;
                $gain = isset($participation['gain']) ? floatval(str_replace(',', '.', $participation['gain'])) : floatval($cur['gain'] ?? 0);
                
                // Recalculated values (new rule for cagnotte)
                // Si gain > 0 -> cagnotte = gain / 10
                // Si gain = 0 -> cagnotte = buyin / 10
                $buyin = isset($participation['buyin_display']) ? floatval($participation['buyin_display']) : floatval($cur['activite_buyin'] ?? 0);
                if ($gain > 0) {
                    $cagnotte = $gain / 10;
                } else {
                    $cagnotte = $buyin / 10;
                }

                // Points de la partie = cagnotte de la partie
                // (pour cohérence avec participation.points et l'affichage "Pts Partie")
                $points = intval(round($cagnotte));

                // Jetons now defaults to activite.jetons value
                $jetons = isset($participation['jetons']) ? intval($participation['jetons']) : intval($cur['jetons'] ?? 0);

                // Bonus jetons
                $jetons_bonus_ins = isset($participation['jetons_bonus_ins']) ? intval($participation['jetons_bonus_ins']) : intval($cur['jetons_bonus_ins'] ?? 0);
                $jetons_bonus_arrivee = isset($participation['jetons_bonus_arrivee']) ? intval($participation['jetons_bonus_arrivee']) : intval($cur['jetons_bonus_arrivee'] ?? 0);

                // Total jetons = jetons + bonus_ins + bonus_arrivee
                $jetons_total = $jetons + $jetons_bonus_ins + $jetons_bonus_arrivee;

                $cout_in = intval($buyin + $rake + ($challenger ? 5 : 0));

                // Final check before update
                $changed = (
                    intval($cur['challenger']) !== $challenger ||
                    intval($cur['id-table']) !== intval($table) ||
                    intval($cur['id-siege']) !== intval($siege) ||
                    abs(floatval($cur['rake'] ?? 0) - $rake) > 0.01 ||
                    intval($cur['recave'] ?? 0) !== intval($recave) ||
                    intval($cur['classement'] ?? 0) !== intval($classement) ||
                    intval($cur['tf'] ?? 0) !== $tf ||
                    intval($cur['win'] ?? 0) !== $win ||
                    intval($cur['remise'] ?? 0) !== $remise ||
                    abs(floatval($cur['gain'] ?? 0) - $gain) > 0.01 ||
                    intval($cur['points'] ?? 0) !== $points ||
                    intval($cur['cagnotte'] ?? 0) !== $cagnotte ||
                    intval($cur['jetons'] ?? 0) !== $jetons ||
                    intval($cur['cout_in'] ?? 0) !== $cout_in ||
                    intval($cur['jetons_bonus_ins'] ?? 0) !== $jetons_bonus_ins ||
                    intval($cur['jetons_bonus_arrivee'] ?? 0) !== $jetons_bonus_arrivee ||
                    intval($cur['jetons_total'] ?? 0) !== $jetons_total
                );

                if (!$changed) {
                    continue;
                }

                // Proceed with update
                // Types must match exactement les 18 placeholders dans $sql_update
                // challenger(i), table(i), siege(i), rake(i), recave(i), classement(i), tf(i), win(i), remise(i), gain(d), points(i), cagnotte(i), jetons(i), cout_in(i), jetons_bonus_ins(i), jetons_bonus_arrivee(i), jetons_total(i), id-participation(i)
                mysqli_stmt_bind_param($stmt_update, "iiiiiiiiidiiiiiiii",
                    $challenger,
                    $table,
                    $siege,
                    $rake,
                    $recave,
                    $classement,
                    $tf,
                    $win,
                    $remise,
                    $gain,
                    $points,
                    $cagnotte,
                    $jetons,
                    $cout_in,
                    $jetons_bonus_ins,
                    $jetons_bonus_arrivee,
                    $jetons_total,
                    $participation['id_participation']
                );
                
                if (mysqli_stmt_execute($stmt_update)) {
                    $updated_member_ids[] = intval($cur['id-membre']);
                } else {
                    error_log("SQL Error updating participation ID " . $participation['id_participation'] . ": " . mysqli_stmt_error($stmt_update));
                }
            }
            mysqli_stmt_close($stmt_update);
            mysqli_stmt_close($stmt_select_current);
        }

        // Après la mise à jour des participations, mettre à jour gain_cumul UNIQUEMENT pour les joueurs impactés
        if (!empty($updated_member_ids)) {
            $member_ids_list = implode(',', array_unique($updated_member_ids));
            
            // Get the challenge ID
            $challenge_result = mysqli_query($con, "SELECT id_challenge FROM activite WHERE `id-activite` = " . intval($_SESSION['selected_activity']));
            if ($challenge_row = mysqli_fetch_assoc($challenge_result)) {
                $challenge_id = intval($challenge_row['id_challenge']);
                
                $update_gain_cumul = "
                    UPDATE participation p1 
                    SET p1.gain_cumul = COALESCE((
                        SELECT SUM(p2.gain) 
                        FROM participation p2 
                        JOIN activite a2 ON p2.`id-activite` = a2.`id-activite`
                        WHERE p2.`id-membre` = p1.`id-membre` 
                        AND a2.`id_challenge` = $challenge_id
                    ), 0)
                    WHERE p1.`id-membre` IN ($member_ids_list)
                    AND p1.`id-activite` IN (
                        SELECT a.`id-activite` 
                        FROM activite a 
                        WHERE a.`id_challenge` = $challenge_id
                    )";

                mysqli_query($con, $update_gain_cumul);
            }
        }

        $_SESSION['feedback'] = "<div class='alert alert-success'>Participations mises à jour avec succès.</div>";
    } catch (Exception $e) {
        $_SESSION['error'] = "Erreur lors de la mise à jour: " . $e->getMessage();
        error_log("Erreur mise à jour gain_cumul: " . $e->getMessage());
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// --- Retrieve feedback message from session and clear it ---
$session_feedback = $_SESSION['feedback'] ?? null;
unset($_SESSION['feedback']);

// --- Variables needed for Price Pool Calculation (Fetch only if an activity is selected) ---
$selected_activity = isset($_SESSION['selected_activity']) ? intval($_SESSION['selected_activity']) : null;
$activity_buyin_for_pricepool = 0.0; // Initialize
$total_buyin_sum = 0.0; // Initialize
$total_recave_sum = 0; // Initialize
$price_pool = 0.0; // Initialize
$total_participants = 0; // Initialize for prize pool form

if ($selected_activity !== null) {
    // Fetch activity buyin for price pool calculation
    $stmt_bp = mysqli_prepare($con, "SELECT buyin FROM activite WHERE `id-activite` = ?");
    if($stmt_bp) {
        mysqli_stmt_bind_param($stmt_bp, "i", $selected_activity);
        mysqli_stmt_execute($stmt_bp);
        $bp_result = mysqli_stmt_get_result($stmt_bp);
        if ($bp_row = mysqli_fetch_assoc($bp_result)) {
            $activity_buyin_for_pricepool = floatval($bp_row['buyin'] ?? 0.0);
        }
        mysqli_free_result($bp_result);
        mysqli_stmt_close($stmt_bp);
    }

    // Fetch sums needed for price pool from participation table for the selected activity
    $stmt_sums = mysqli_prepare($con, "SELECT COUNT(*) as participant_count, SUM(a.buyin) as total_buyin, SUM(p.recave) as total_recave
                                      FROM participation p
                                      JOIN activite a ON p.`id-activite` = a.`id-activite`
                                      WHERE p.`id-activite` = ?");
     if ($stmt_sums) {
        mysqli_stmt_bind_param($stmt_sums, "i", $selected_activity);
        mysqli_stmt_execute($stmt_sums);
        $sums_result = mysqli_stmt_get_result($stmt_sums);
        if ($sums_row = mysqli_fetch_assoc($sums_result)) {
            $total_participants = intval($sums_row['participant_count'] ?? 0); // Get participant count here
            $total_buyin_sum = floatval($sums_row['total_buyin'] ?? 0.0); // Sum of activity.buyin for each participant
            $total_recave_sum = intval($sums_row['total_recave'] ?? 0);
        }
        mysqli_free_result($sums_result);
        mysqli_stmt_close($stmt_sums);

        // Calculate Price Pool
        $price_pool = $total_buyin_sum + ($total_recave_sum * $activity_buyin_for_pricepool);
     }
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <title>Admin | Gestion des Participations</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Template CSS -->
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
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/plugins.css">
    <link rel="stylesheet" href="assets/css/themes/theme-1.css" id="skin_color" />
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css" rel="stylesheet" />
    <!-- <link href="https://cdn.jsdelivr.net/npm/simple-datatables@latest/dist/style.css" rel="stylesheet" /> -->
    <link rel="stylesheet" href="quick-part-style.css">
    <!-- Specific CSS from original quick-part.php -->
 
</head>
<body>
    <div id="app">
    <?php include('include/sidebar.php'); ?>
    <div class="app-content">
    <?php include('include/header.php'); ?>
            <!-- start: MAIN CONTAINER -->
            <div class="main-content" >
                <div class="wrap-content container" id="container">
                    <!-- start: PAGE TITLE -->
                    <section id="page-title">
                        <div class="row">
                            <div class="col-sm-8">
                                <h1 class="mainTitle">Admin | Gestion des Participations</h1>
                            </div>
                            <ol class="breadcrumb">
                                <li><span>Admin</span></li>
                                <li class="active"><span>Gestion Participations</span></li>
                            </ol>
                        </div>
                    </section>
                    <!-- end: PAGE TITLE -->

                    <!-- start: BASIC EXAMPLE -->
                    <div class="container-fluid container-fullw bg-white">
                        <div class="row">
                            <div class="col-md-12">
                                <?php
                                    // Display feedback message from session if it exists
                                    if ($session_feedback) {
                                        echo $session_feedback; // Message should already contain HTML
                                    }
                                ?>

                                <!-- Activity Selection Form -->
                                <div class="panel panel-white card">
                                    <div class="panel-body">
                                        <h2>Filtrer par Activité</h2>
                                        <form method="post">
                                            <table class="simple-form-table">
                                                <tr>
                                                    <th>Activité</th>
                                                    <td>
                                                        <?php
                                                        // Calculate date 365 days ago
                                                        $three_days_ago = date('Y-m-d', strtotime('-365 days', strtotime($actu2)));
                                                        $safe_three_days_ago_date = mysqli_real_escape_string($con, $three_days_ago);

                                                        // Modify query to include last 365 days
                                                        $acti_query = mysqli_query($con, "SELECT `id-activite`,`titre-activite`,`date_depart` FROM `activite` WHERE (`date_depart` >= '$safe_three_days_ago_date') ORDER BY `date_depart` DESC");
                                                        echo "<select name='acti' class='form-control'>";
                                                        echo "<option value='-Anonyme-'>-- Afficher Toutes --</option>";
                                                        $current_selected_activity = isset($_SESSION['selected_activity']) ? $_SESSION['selected_activity'] : null;
                                                        if ($acti_query) {
                                                            while ($choix = mysqli_fetch_assoc($acti_query)) {
                                                                $selected_attr = ($choix["id-activite"] == $current_selected_activity) ? ' selected' : '';
                                                                $formatted_date = date("d/m/Y H:i", strtotime($choix["date_depart"]));
                                                                echo "<option value='" . htmlspecialchars($choix["id-activite"]) . "'$selected_attr>" . $formatted_date . " (" . htmlspecialchars($choix["titre-activite"]) . ")</option>";
                                                            }
                                                            mysqli_free_result($acti_query);
                                                        } else { echo "<option value=''>Erreur chargement activités</option>"; }
                                                        echo "</select>";
                                                        ?>
                                                    </td>
                                                    <td class="btn-container-cell">
                                                        <div class="btn-container">
                                                            <button type="submit" class="btn btn-primary-orange2" name="submitchoixact"> Filtrer </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            </table>
                                        </form>
                                    </div>
                                </div>

                                <!-- Quick Player Creation Form -->
                                <div class="panel panel-white card">
                                    <div class="panel-body">
                                        <h2>Création Rapide Joueur</h2>
                                        <form method="post">
                                            <table class="simple-form-table">
                                                <tr>
                                                    <th>Pseudo *</th>
                                                    <td> <input class="form-control" id="pseudo" name="pseudo" type="text" required> </td>
                                                </tr>
                                                <tr>
                                                    <th>Prénom</th>
                                                    <td> <input class="form-control" id="fname" name="fname" type="text"> </td>
                                                </tr>
                                                
                                                <?php if ($selected_activity !== null): ?>
                                                <tr>
                                                    <th>Inscrire à l'activité</th>
                                                    <td>
                                                        <div class="checkbox">
                                                            <label>
                                                                <input type="checkbox" name="auto_register" value="1" checked>
                                                                Inscrire directement à l'activité filtrée (ID: <?php echo $selected_activity; ?>)
                                                            </label>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php endif; ?>
                                                <tr>
                                                    <th>&nbsp;</th>
                                                    <td class="btn-container-cell">
                                                        <div class="btn-container">
                                                            <button type="submit" class="btn btn-primary-orange2" name="submitcreaj">Créer Joueur</button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            </table>
                                        </form>
                                    </div>
                                </div>

                                <!-- Quick Registration Form -->
                                <div class="panel panel-white card">
                                    <div class="panel-body">
                                        <h2>Inscription Rapide Joueur</h2>
                                        <?php
                                        // --- Fetch occupied seats for the selected activity ---
                                        $occupied_seats_json = '[]'; // Default to empty JSON array
                                        if ($selected_activity !== null) {
                                            $occupied_seats_query = "SELECT `id-table`, `id-siege` FROM `participation` WHERE `id-activite` = ?";
                                            $stmt_occupied = mysqli_prepare($con, $occupied_seats_query);
                                            if ($stmt_occupied) {
                                                mysqli_stmt_bind_param($stmt_occupied, "i", $selected_activity);
                                                if (mysqli_stmt_execute($stmt_occupied)) {
                                                    $occupied_result = mysqli_stmt_get_result($stmt_occupied);
                                                    $occupied_seats_data = [];
                                                    while ($seat_row = mysqli_fetch_assoc($occupied_result)) {
                                                        $occupied_seats_data[] = ['table' => $seat_row['id-table'], 'siege' => $seat_row['id-siege']];
                                                    }
                                                    mysqli_free_result($occupied_result);
                                                    $occupied_seats_json = json_encode($occupied_seats_data);
                                                } else {
                                                    error_log("Erreur execution fetch occupied seats: " . mysqli_stmt_error($stmt_occupied));
                                                }
                                                mysqli_stmt_close($stmt_occupied);
                                            } else {
                                                 error_log("Erreur préparation fetch occupied seats: " . mysqli_error($con));
                                            }
                                        }
                                        ?>
                                        <form method="post">
                                            <table class="simple-form-table">
                                                 <tr>
                                                    <th>Joueur *</th>
                                                    <td>
                                                        <?php
                                                        $membres_reg = mysqli_query($con, "SELECT `id-membre`,`pseudo` FROM `membres` ORDER BY `pseudo` ASC");
                                                        echo "<select name='membre' id='membre_select' class='form-control' required>
                                                            <option value=''>-- Sélectionner Pseudo --</option>";
                                                        if ($membres_reg) {
                                                            while ($choix = mysqli_fetch_assoc($membres_reg)) {
                                                                echo "<option value='" . htmlspecialchars($choix["id-membre"]) . "'>" 
                                                                    . htmlspecialchars($choix["pseudo"]) . "</option>";
                                                            }
                                                            mysqli_free_result($membres_reg);
                                                        }
                                                        echo "</select>";
                                                        ?>
                                                    </td>
                                                 </tr>
                                                 <!-- Activity selection removed: quick registration uses current session activity -->
                                                 <!-- Table selection removed per request -->
                                                 <!-- Siège selection removed per request -->
                                                 <!-- Nombre de jetons removed per request -->
                                                 <tr>
                                                     <th>&nbsp;</th>
                                                     <td class="btn-container-cell">
                                                        <div class="btn-container">
                                                           <button type="submit" class="btn btn-primary-orange2" name="submit" <?php echo $selected_activity === null ? 'disabled' : ''; ?>>Inscrire Joueur</button>
                                                         </div>
                                                    </td>
                                                 </tr>
                                            </table>
                                        </form>
                                    </div>
                                </div>

                                 <!-- Quick Delete Form -->
                                <div class="panel panel-white card">
                                    <div class="panel-body">
                                        <h2>Suppression Rapide Participation</h2>
                                        <form method="post">
                                            <table class="simple-form-table">
                                                <tr>
                                                    <th>Joueur *</th>
                                                    <td>
                                                         <?php
                                                         $activity_id = isset($_SESSION['selected_activity']) ? $_SESSION['selected_activity'] : (isset($_POST['actisup']) ? $_POST['actisup'] : null);
                                                         $activity_filter = $activity_id ? "WHERE p.`id-activite` = " . intval($activity_id) : "";
                                                         $membres_del = mysqli_query($con, "SELECT DISTINCT m.`id-membre`, m.`pseudo` 
                                                           FROM `membres` m
                                                           JOIN `participation` p ON m.`id-membre` = p.`id-membre`
                                                           $activity_filter
                                                           ORDER BY m.`pseudo` ASC");
                                                         echo "<select name='membresup' class='form-control' required><option value=''>-- Sélectionner Pseudo --</option>";
                                                         if ($membres_del) {
                                                            while ($choix = mysqli_fetch_assoc($membres_del)) { echo "<option value='" . htmlspecialchars($choix["id-membre"]) . "'>" . htmlspecialchars($choix["pseudo"]) . "</option>"; }
                                                            mysqli_free_result($membres_del);
                                                         } else { echo "<option value=''>Erreur chargement</option>"; }
                                                         echo "</select>";
                                                         ?>
                                                    </td>
                                                </tr>
                                                <tr>
                                                                    <th style="display:none"></th>
                                                                     <td>
                                                                            <?php if ($selected_activity !== null): ?>
                                                                                <input type="hidden" name="actisup" value="<?php echo htmlspecialchars($selected_activity); ?>">
                                                                            <?php else: ?>
                                                                            <?php
                                                                            // Calculate date 7 days ago
                                                                            $seven_days_ago = date('Y-m-d', strtotime('-7 days', strtotime($actu2)));
                                                                            $safe_seven_days_ago_date = mysqli_real_escape_string($con, $seven_days_ago);
                                                                            $acti_del = mysqli_query($con, "SELECT `id-activite`,`titre-activite`,`date_depart` FROM `activite` WHERE ( `date_depart` >= '$safe_seven_days_ago_date') ORDER BY `date_depart` ASC");
                                                                            echo "<select name='actisup' class='form-control' required><option value=''>-- Sélectionner Date --</option>";
                                                                            if ($acti_del) {
                                                                                 while ($choix = mysqli_fetch_assoc($acti_del)) {
                                                                                     $formatted_date = date("d/m/Y H:i", strtotime($choix["date_depart"]));
                                                                                     echo "<option value='" . htmlspecialchars($choix["id-activite"]) . "'>" . $formatted_date . "</option>";
                                                                                 }
                                                                                  mysqli_free_result($acti_del);
                                                                            } else { echo "<option value=''>Erreur chargement</option>"; }
                                                                            echo "</select>";
                                                                            ?>
                                                                            <?php endif; ?>
                                                                     </td>
                                                                 </tr>
                                                 <tr>
                                                     <th>&nbsp;</th>
                                                     <td class="btn-container-cell">
                                                        <div class="btn-container">
                                                            <button type="submit" class="btn btn-primary-orange2" name="submitsupc" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette participation ?');" <?php echo $selected_activity === null ? 'disabled' : ''; ?>>Supprimer Participation</button>
                                                         </div>
                                                    </td>
                                                </tr>
                                            </table>
                                        </form>
                                    </div>
                                </div>


                                <!-- Participation List & Update Form -->
                                <div class="panel panel-white card">
                                    <div class="panel-body">
                                        <h2>Liste des Participations <?php echo $selected_activity ? "(Activité ID: ".$selected_activity.")" : "(Toutes les activités à venir)"; ?></h2>

                                        <?php
                                            $data_found = false;
                                            // Initialize totals
                                            $total_challengers = 0;
                                            $total_buyin_activite = 0.0; // Sum of activity.buyins for displayed rows
                                            $total_rake_participation = 0.0;
                                            $total_cout_in = 0.0;
                                            $total_recave = 0;
                                            $total_cagnotte = 0;
                                            $total_points_partie = 0; // Added for point totals
                                            $total_bounty = 0.0; // Initialize bounty total
                                            // $total_participants already calculated if activity selected
                                        ?>

                                        <form method="post" id="participation-form">
                                            <div class="table-responsive">
                                                <table class="data-table">
                                                    <thead>
        <tr>
            <th class="cell-center">Ch.</th>
            <th>Joueur</th>
            <th class="cell-right">Jetons</th>
            <th class="cell-right">Bonus Ins</th>
            <th class="cell-right">Bonus Arr</th>
            <th class="cell-right">Total Jetons</th>
            <th class="cell-right">Buy-in</th>
            <th class="cell-right">Bounty</th> 
            <th class="cell-right">Rake</th>
            <th class="cell-right">Cout</th>
            <th class="cell-right">Recaves</th>
            <th class="cell-center">Clas.</th>
            <th class="cell-center">TF</th>
            <th class="cell-center">ITM</th>
            
            <th class="cell-center">Win</th> <!-- Nouvelle colonne Win -->
            <th class="cell-right">Pts</th>
            <th class="cell-center">Remise</th>
            <th class="cell-right">Cagnotte</th>
            <th class="cell-right">Gain</th>
        </tr>
    </thead>
    <tbody>
        <?php
        // $selected_activity defined earlier
        // $data_found, totals initialized above

        $query_list = "SELECT 
    p.`id-membre`, m.pseudo, p.`id-activite`, a.`titre-activite`, a.`date_depart`,
    p.`id-table`, p.`id-siege`, p.challenger, p.recave, p.classement,
    p.cout_in, p.rake AS participation_rake, a.bounty, p.win,
    p.points, p.cagnotte, p.tf, p.remise, a.buyin AS activite_buyin, a.jetons AS activite_jetons,
    p.jetons, p.jetons_bonus_ins, p.jetons_bonus_arrivee, p.ds,
    COALESCE(p.gain, 0) as gain,
    p.`id-participation`
FROM participation p
JOIN membres m ON p.`id-membre` = m.`id-membre`
JOIN activite a ON p.`id-activite` = a.`id-activite`";

        $safe_actu2_date = mysqli_real_escape_string($con, $actu2);
        $participants_in_list = 0; // Counter for displayed rows

        if ($selected_activity !== null) {
            $query_list .= " WHERE p.`id-activite` = ?";
        } else {
            $query_list .= " WHERE a.`date_depart` >= '$safe_actu2_date'";
        }
        // Sort by Challenger DESC, then Pseudo ASC, then other criteria
        $query_list .= " ORDER BY p.challenger DESC, m.pseudo ASC, a.date_depart DESC, p.`id-table` ASC, p.`id-siege` ASC";

        $stmt_list = mysqli_prepare($con, $query_list);

        if ($stmt_list) {
            if ($selected_activity !== null) {
                mysqli_stmt_bind_param($stmt_list, "i", $selected_activity);
            }

            if (mysqli_stmt_execute($stmt_list)) {
                $result = mysqli_stmt_get_result($stmt_list);
                if (mysqli_num_rows($result) > 0) {
                    $data_found = true;
                    $index = 0;
                    $current_activity_header = null;
                    $participants_in_list = mysqli_num_rows($result); // Count displayed rows

                    while($row = mysqli_fetch_assoc($result)) {
                        // Accumulate totals for displayed rows
                        if ($row['challenger']) $total_challengers++;
                        $total_buyin_activite += floatval($row['activite_buyin'] ?? 0.0); // Add for every row displayed
                        $total_rake_participation += floatval($row['participation_rake'] ?? 0.0);
                        $total_cout_in += floatval($row['cout_in'] ?? 0.0);
                        $total_recave += intval($row['recave'] ?? 0);
                        $total_cagnotte += intval($row['cagnotte'] ?? 0);
                        $total_points_partie += intval($row['points'] ?? 0); // Accumulate points

                        // Display Activity Header Row
                        if ($selected_activity === null && $row['id-activite'] !== $current_activity_header) {
                            $formatted_date_header = date("d/m/Y H:i", strtotime($row["date_depart"]));
                            // Output 19 cells to match header count (added 1 total jetons column), avoiding colspan in tbody
                            echo '<tr class="activity-header">';
                            echo '  <td style="font-weight: bold; background-color: #e9ecef !important;">Activité: ' . htmlspecialchars($row['titre-activite']) . ' (' . $formatted_date_header . ') - ID: ' . $row['id-activite'] . '</td>'; // Style added to mimic header
                            for ($i = 0; $i < 18; $i++) { echo '<td style="background-color: #e9ecef !important;"></td>'; } // Add 18 empty styled cells
                            echo '</tr>';
                            $current_activity_header = $row['id-activite'];
                        }

                        // Display Data Row
                        // Prepare raw numeric values for data-sort attributes
                        $raw_activite_buyin = floatval($row['activite_buyin'] ?? 0.0);
                        $raw_participation_rake = floatval($row['participation_rake'] ?? 0.0);
                        $raw_cout_in = floatval($row['cout_in'] ?? 0.0);
                        $raw_recave = intval($row['recave'] ?? 0);
                        $raw_classement = ($row['classement'] === null || $row['classement'] === '') ? 9999 : intval($row['classement']); // Use a high number for null/empty classement for sorting purposes
                        $raw_tf = intval($row['tf'] ?? 0); // Sort 0/1
                        $raw_points = intval($row['points'] ?? 0);
                        $raw_cagnotte = intval($row['cagnotte'] ?? 0);
                        $raw_jetons = intval($row['jetons'] ?? 0);
                        $is_win = intval($row['win'] ?? 0); // Use DB value for win status

                        echo "<tr>";
                        // Column 0: Ch. (Checkbox - now sortable using hidden span)
                        $challenger_sort_value = $row['challenger'] ? 1 : 0;
                        echo "<td class='cell-center'><span class='sort-value' style='display: none;'>" . $challenger_sort_value . "</span><input type='checkbox' name='participations[$index][challenger]' value='1' " . ($row['challenger'] ? 'checked' : '') . ($selected_activity ? '' : ' disabled') . "></td>";
                            // Column 1: Joueur (Text - default sorting)
                            // Removed data-sort attribute
                        echo "<td title='" . htmlspecialchars($row['pseudo']) . "'>" . htmlspecialchars(substr($row['pseudo'], 0, 15)) . (strlen($row['pseudo']) > 15 ? '...' : '')
                             . "<input type='hidden' name='participations[$index][membre_id]' value='" . htmlspecialchars($row['id-membre']) . "'>"
                             . "<input type='hidden' name='participations[$index][activite_id]' value='" . htmlspecialchars($row['id-activite']) . "'></td>";
                            // Column 2: Jetons (readonly numeric - uses activite.jetons as default)
                            $raw_jetons = isset($row['activite_jetons']) ? intval($row['activite_jetons']) : 0;
                            echo "<td class='cell-right'><span class='sort-value' style='display: none;'>" . $raw_jetons . "</span><input type='number' name='participations[$index][jetons]' value='" . htmlspecialchars((string)$raw_jetons) . "' step='1' min='0' readonly title='Nombre de jetons (de l\'activité)'></td>";

                            // Column 3: Bonus Ins (editable numeric)
                            $raw_bonus_ins = isset($row['jetons_bonus_ins']) ? intval($row['jetons_bonus_ins']) : 0;
                            $date_depart = isset($row['date_depart']) ? $row['date_depart'] : '';
                            $date_inscription = isset($row['ds']) ? $row['ds'] : '';
                            // Format dates for JavaScript: ensure they're ISO 8601 format
                            if ($date_depart && !strpos($date_depart, 'T')) {
                                $date_depart = date('c', strtotime($date_depart)); // Convert to ISO 8601
                            }
                            if ($date_inscription && !strpos($date_inscription, 'T')) {
                                $date_inscription = date('c', strtotime($date_inscription)); // Convert to ISO 8601
                            }
                            echo "<td class='cell-right'><span class='sort-value' style='display: none;'>" . $raw_bonus_ins . "</span><input type='number' id='bonus_ins_" . $index . "' name='participations[$index][jetons_bonus_ins]' value='" . htmlspecialchars((string)$raw_bonus_ins) . "' step='1' min='0' max='5000' title='Bonus Inscription' data-date-depart='" . htmlspecialchars($date_depart) . "' data-date-inscription='" . htmlspecialchars($date_inscription) . "'" . ($selected_activity ? '' : ' disabled') . "></td>";

                            // Column 4: Bonus Arrivée (editable numeric)
                            $raw_bonus_arrivee = isset($row['jetons_bonus_arrivee']) ? intval($row['jetons_bonus_arrivee']) : 0;
                            echo "<td class='cell-right'><span class='sort-value' style='display: none;'>" . $raw_bonus_arrivee . "</span><input type='number' name='participations[$index][jetons_bonus_arrivee]' value='" . htmlspecialchars((string)$raw_bonus_arrivee) . "' step='1' min='0' title='Bonus Arrivée'" . ($selected_activity ? '' : ' disabled') . "></td>";

                            // Column 5: Total Jetons (Readonly - calculation of jetons + bonus_ins + bonus_arrivee)
                            $bonus_total = $raw_jetons + $raw_bonus_ins + $raw_bonus_arrivee;
                            echo "<td class='cell-right'><span class='sort-value' style='display: none;'>" . $bonus_total . "</span><strong>" . number_format($bonus_total, 0, ',', ' ') . "</strong></td>";

                            // Colonne suivante: Buy-in Act. (Readonly Input - numeric sort using hidden span avec class)
                        echo "<td class='cell-right'><span class='sort-value' style='display: none;'>" . $raw_activite_buyin . "</span><input type='number' name='participations[$index][buyin_display]' value='" . htmlspecialchars(number_format($raw_activite_buyin, 2, '.', '')) . "' step='0.01' readonly title='Buy-in Activité'></td>
                        ";
                        
                        // Column 5: Bounty (Readonly Input - numeric sort using hidden span with class)
                        $raw_bounty = floatval($row['bounty'] ?? 0.0);
                        $total_bounty += $raw_bounty; // Accumulate bounty total
                        echo "<td class='cell-right'><span class='sort-value' style='display: none;'>" . $raw_bounty . "</span><input type='number' name='participations[$index][bounty_display]' value='" . htmlspecialchars(number_format($raw_bounty, 2, '.', '')) . "' step='0.01' readonly title='Bounty'></td>";
                        
                        // Column 6: Rake Part. (Dropdown with specific values)
                        echo "<td class='cell-right'><span class='sort-value' style='display: none;'>" . $raw_participation_rake . "</span>";
                        echo "<select name='participations[$index][rake]' " . ($selected_activity ? '' : 'disabled') . ">";
                        $rake_values = [0, 5, 10, 12, 15, 20];
                        foreach ($rake_values as $value) {
                            $selected = (abs($raw_participation_rake - $value) < 0.01) ? 'selected' : '';
                            echo "<option value='$value' $selected>$value</option>";
                        }
                        echo "</select></td>";
                        // Column 7: Cout In (Readonly Input - numeric sort using hidden span with class)
                        echo "<td class='cell-right'><span class='sort-value' style='display: none;'>" . $raw_cout_in . "</span><input type='number' name='participations[$index][cout_in_display]' value='" . htmlspecialchars(number_format($raw_cout_in, 2, '.', '')) . "' step='0.01' readonly title='onne
                         (BuyIn Act.+Rake Part.[+5 si Ch])'></td>";
                        // Column 7: Rec. (Dropdown - numeric sort using hidden span with class)
                        echo "<td class='cell-right'><span class='sort-value' style='display: none;'>" . $raw_recave . "</span>";
                        echo "<select class='recave-select' name='participations[$index][recave]' " . ($selected_activity ? '' : 'disabled') . ">";
                        for ($v = 0; $v <= 4; $v++) {
                            echo "<option value='$v' " . ($raw_recave == $v ? 'selected' : '') . ">$v</option>";
                        }
                        echo "</select></td>";
                        // Column 8: Clas. (Input - numeric sort using hidden span with class, handle null/empty)
                        echo "<td class='cell-center'><span class='sort-value' style='display: none;'>" . $raw_classement . "</span><select name='participations[$index][classement]' " . ($selected_activity ? '' : 'disabled') . "><option value=''>N/A</option>";
                        for ($c = 0; $c <= 50; $c++) {
                            $selected = ($row['classement'] !== null && $row['classement'] == $c) ? 'selected' : '';
                            echo "<option value='$c' $selected>$c</option>";
                        }
                        echo "</select></td>";
                        // Column 9: TF (Checkbox - set to true if ITM is true or if tf field is true)
                        $is_itm = (floatval($row['gain']) > 0) ? 1 : 0;
                        $effective_tf = (intval($row['tf']) === 1 || $is_itm === 1) ? 1 : 0;
                        echo "<td class='cell-center'><span class='sort-value' style='display: none;'>" . $effective_tf . "</span><input type='checkbox' name='participations[$index][tf]' value='1' " . ($effective_tf ? 'checked' : '') . ($selected_activity ? '' : ' disabled') . "></td>";
                        // Column 10: ITM (Checkbox - set to true if gain > 0)
                        echo "<td class='cell-center'><span class='sort-value' style='display: none;'>" . $is_itm . "</span>";
                        echo "<input type='checkbox' name='participations[$index][itm]' value='1' " . ($is_itm ? 'checked' : '') . ($selected_activity ? '' : ' disabled') . " title='ITM = 1 si Gain > 0'>";
                        echo "</td>";
                        
                        // Nouvelle colonne Win (modifiable)
                        echo "<td class='cell-center'><span class='sort-value' style='display: none;'>" . $is_win . "</span>";
                        echo "<input type='checkbox' name='participations[$index][win]' value='1' " . ($is_win ? 'checked' : '') . ($selected_activity ? '' : ' disabled') . " title='Win = 1 si Clas. = 1'>";
                        echo "</td>";
                        // Column 10: Pts
                        // Doit être égal à la cagnotte (nouvelle règle)
                        $display_points = intval($row['cagnotte'] ?? 0);

                        // Affichage du champ PTS (miroir de la cagnotte)
                        echo "<td class='cell-right'><span class='sort-value' style='display: none;'>" . $display_points . "</span>
<input type='number' name='participations[$index][points]' value='" . $display_points . "' step='1' placeholder='0' title='Points (égaux à la cagnotte)'></td>";
                        // Column 11: Cagnotte (Readonly Input - numeric sort using hidden span with class)
                                        $remise_checked = $row['remise'] ? 'checked' : '';
                                        echo "<td class='cell-center'><span class='sort-value' style='display: none;'>" . $row['remise'] . "</span><input type='checkbox' name='participations[$index][remise]' value='1' $remise_checked " . ($selected_activity ? '' : ' disabled') . "></td>";
                                        echo "<td class='cell-center'><span class='sort-value' style='display: none;'>" . $raw_cagnotte . "</span><input type='number' name='participations[$index][cagnotte_display]' value='" . htmlspecialchars((string)$raw_cagnotte) . "' step='1' placeholder='0' readonly title='Calc: Si Ch.=(Recave*3)+3, sinon 0'></td>";
                                        // Column: Gain
                                        echo "<td class='cell-right'>";
                                        echo "<input type='number' 
                                            name='participations[$index][gain]' 
                                            value='" . number_format(floatval($row['gain']), 2, '.', '') . "' 
                                            class='gain-input'
                                            style='width: 80px;' 
                                            step='0.01' " . 
                                            ($selected_activity ? '' : ' disabled') . ">";
                                        echo "<input type='hidden' name='participations[$index][id_participation]' value='" . $row['id-participation'] . "'>";
                                        echo "</td>";
                                                                        echo "</tr>";
                                                                        $index++;
                                                                    }
                                                                } else {
                                                                    $data_found = false;
                                                                }
                                                                mysqli_free_result($result);
                                                            } else {
                                                                 // Output cells for error message
                                                                 echo '<tr>';
                                                                 echo '  <td><div class="alert alert-danger" style="margin-bottom: 0;">Erreur execution requête liste: ' . htmlspecialchars(mysqli_stmt_error($stmt_list)) . '</div></td>';
                                                                 for ($i = 0; $i < 18; $i++) { echo '<td></td>'; }
                                                                 echo '</tr>';
                                                                 $data_found = false;
                                                            }
                                                            mysqli_stmt_close($stmt_list);
                                                        } else {
                                                             // Output cells for error message
                                                             echo '<tr>';
                                                             echo '  <td><div class="alert alert-danger" style="margin-bottom: 0;">Erreur préparation requête liste: ' . htmlspecialchars(mysqli_error($con)) . '</div></td>';
                                                             for ($i = 0; $i < 18; $i++) { echo '<td></td>'; }
                                                             echo '</tr>';
                                                             $data_found = false;
                                                        }

                                                         // Display info messages or no data message
                                                         if (!$data_found) {
                                                             // Output 19 cells to match header count (1 total jetons column added)
                                                             echo '<tr>';
                                                             echo '  <td style="text-align: center; padding: 1.5rem;">Aucun participant trouvé pour ' . ($selected_activity ? "l'activité sélectionnée." : "les activités à venir.") . '</td>';
                                                             for ($i = 0; $i < 18; $i++) { echo '<td></td>'; } // Add 18 empty cells
                                                             echo '</tr>';
                                                         } elseif (!$selected_activity && $data_found) {
                                                             // Output 19 cells, place alert in the first one
                                                             echo '<tr>';
                                                             echo '  <td><div class="alert alert-info" style="margin-top: 1rem; text-align: center; margin-bottom: 0;">Filtrez par une activité pour activer la modification groupée.</div></td>';
                                                             for ($i = 0; $i < 18; $i++) { echo '<td></td>'; } // Add 18 empty cells
                                                             echo '</tr>';
                                                        }
                                                        ?>
                                                    </tbody>
                                                     <?php if ($data_found): // Show Footer if data exists ?>
                                                     <tfoot>
                                                         <tr class="total-row">
                                                             <td class="cell-center"><?php echo $total_challengers; ?></td>
                                                             <td>Total (<?php echo $participants_in_list; ?>):</td>
                                                             <td class="cell-right"></td> <!-- Jetons (total non calculé) -->
                                                             <td class="cell-right"></td> <!-- Bonus Ins (total non calculé) -->
                                                             <td class="cell-right"></td> <!-- Bonus Arrivée (total non calculé) -->
                                                             <td class="cell-right"></td> <!-- Total Jetons (total non calculé) -->
                                                             <td class="cell-right"><?php echo number_format($total_buyin_activite, 2, '.', ' '); ?></td>
                                                             <td class="cell-right"><?php echo number_format($total_bounty, 2, '.', ' '); ?></td>
                                                             <td class="cell-right"><?php echo number_format($total_rake_participation, 2, '.', ' '); ?></td>
                                                             <td class="cell-right"><?php echo number_format($total_cout_in, 2, '.', ' '); ?></td>
                                                             <td class="cell-right"><?php echo $total_recave; ?></td>
                                                             <td class="cell-center"></td> <!-- Clas. -->
                                                             <td class="cell-center"></td> <!-- TF -->
                                                             <td class="cell-center"></td> <!-- ITM -->
                                                             <td class="cell-center"></td> <!-- Win -->
                                                             <td class="cell-right" id="footer-total-points"><?php echo $total_points_partie; ?></td>
                                                             <td class="cell-center"></td> <!-- Remise -->
                                                             <td class="cell-right"><?php echo $total_cagnotte; ?></td>
                                                             <td class="cell-right">
                                                                <?php 
                                                                    $total_gains = 0;
                                                                    if (isset($result) || $selected_activity !== null) {
                                                                        // Réexécuter la requête pour les totaux
                                                                        $stmt_total = mysqli_prepare($con, "SELECT SUM(COALESCE(gain, 0)) as total_gains 
                                                                            FROM participation 
                                                                            WHERE `id-activite` = ?");
                                                                        if ($stmt_total) {
                                                                            mysqli_stmt_bind_param($stmt_total, "i", $selected_activity);
                                                                            mysqli_stmt_execute($stmt_total);
                                                                            $result_total = mysqli_stmt_get_result($stmt_total);
                                                                            if ($row_total = mysqli_fetch_assoc($result_total)) {
                                                                                $total_gains = floatval($row_total['total_gains']);
                                                                            }
                                                                            mysqli_free_result($result_total);
                                                                            mysqli_stmt_close($stmt_total);
                                                                        }
                                                                    }
                                                                    echo number_format($total_gains, 2, ',', ' ') . ' €';
                                                                ?>
                                                            </td>
                                                         </tr>
                                                         <tr>
                                                            <td colspan="19" class="text-center">
                                                                <input type="hidden" name="update_participation" value="1">
                                                                <button type="submit" class="btn btn-primary-orange2">
                                                                    Mettre à jour les Participations
                                                                </button>
                                                            </td>
                                                        </tr>
                                                     </tfoot>
                                                     <?php endif; // End if($data_found) ?>
                                                </table>
                                            </div> <!-- /table-responsive -->
                                        </form>

                                        <?php
                                        // Display Price Pool if an activity is selected and data was found
                                        if ($selected_activity !== null && $data_found) {
                                             // Use the pre-calculated $price_pool based on DB sums for accuracy
                                             echo "<div class='pricepool-display'>";
                                             echo "Price Pool Estimé: " . number_format($price_pool, 2, '.', ' ') . " ";
                                             echo "<span style='font-size: 0.8em; font-weight: normal;'> (Total Buyins Act.: " . number_format($total_buyin_sum, 2, '.', ' ') . " + (Total Recaves: " . $total_recave_sum . " * Buyin Act.: " . number_format($activity_buyin_for_pricepool, 2, '.', ' ') . "))</span>";
                                             echo "</div>";
                                        }
                                        ?>
                                    </div>
                                </div> <!-- /panel -->

                                <!-- Prize Pool Distribution -->
                                <div class="panel panel-white card">
                                    <div class="panel-body">
                                        <h2>Répartition du Prize Pool</h2>
                                        <?php if ($selected_activity !== null && $data_found): 
                                            // Calculate default number of paid players based on total participants
                                            $default_nb_joueurs_payes = min(ceil($total_participants * 0.3), 8);
                                            
                                            // Use posted value if available, otherwise use default
                                            $nb_joueurs_payes = isset($_POST['nb_joueurs_payes']) ? 
                                                min(max(1, intval($_POST['nb_joueurs_payes'])), 8) : 
                                                $default_nb_joueurs_payes;
                                            ?>
                                            
                                            <form method="post" class="mb-3">
                                                <table class="simple-form-table">
                                                    <tr>
                                                        <th>Nombre de Joueurs Payés</th>
                                                        <td>
                                                            <select name="nb_joueurs_payes" class="form-control" style="max-width: 100px;">
                                                                <?php for($i = 1; $i <= 8; $i++): ?>
                                                                    <option value="<?php echo $i; ?>" <?php echo ($i == $nb_joueurs_payes) ? 'selected' : ''; ?>>
                                                                        <?php echo $i; ?> jr<?php echo ($i > 1) ? 's' : ''; ?>
                                                                    </option>
                                                                <?php endfor; ?>
                                                            </select>
                                                        </td>
                                                        <td class="btn-container-cell">
                                                            <div class="btn-container">
                                                                <button type="submit" class="btn btn-primary-orange2">Recalculer</button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                </table>
                                            </form>

                                            <?php
                                            // Define distribution percentages based on number of paid players
                                            $distributions = [];
                                            switch($nb_joueurs_payes) {
                                                case 1: $distributions = [1.00]; break;
                                                case 2: $distributions = [0.65, 0.35]; break;
                                                case 3: $distributions = [0.50, 0.30, 0.20]; break;
                                                case 4: $distributions = [0.40, 0.30, 0.20, 0.10]; break;
                                                case 5: $distributions = [0.35, 0.25, 0.20, 0.12, 0.08]; break;
                                                case 6: $distributions = [0.32, 0.23, 0.17, 0.13, 0.09, 0.06]; break;
                                                case 7: $distributions = [0.29, 0.21, 0.16, 0.13, 0.10, 0.07, 0.04]; break;
                                                case 8: $distributions = [0.27, 0.20, 0.15, 0.12, 0.10, 0.08, 0.05, 0.03]; break;
                                                default: $distributions = [1.00]; break;
                                            }

                                            echo "<div class='table-responsive'>";
                                            echo "<table class='data-table distribution-table'>";
                                            echo "<thead><tr>";
                                            echo "<th class='cell-center'>Position</th>";
                                            echo "<th class='cell-right'>Pourcentage</th>";
                                            echo "<th class='cell-right'>Gain Estimé</th>";
                                            echo "</tr></thead>";
                                            echo "<tbody>";

                                            for($i = 0; $i < count($distributions); $i++) {
                                                $gain_estime = $price_pool * $distributions[$i];
                                                // Arrondir au multiple de 5 supérieur, minimum 5€
                                                $gain_arrondi = ($gain_estime > 0) ? max(5, round($gain_estime / 5) * 5) : 0;
                                                
                                                echo "<tr>";
                                                echo "<td class='cell-center'>" . ($i + 1) . "</td>";
                                                echo "<td class='cell-right'>" . number_format($distributions[$i] * 100, 1) . "%</td>";
                                                echo "<td class='cell-right'>" . number_format($gain_arrondi, 2) . " €</td>";
                                                echo "</tr>";
                                            }

                                            echo "</tbody></table></div>";
                                            ?>
                                        <?php else: ?>
                                            <div class="alert alert-info">Sélectionnez une activité pour voir la répartition du Prize Pool.</div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Podium Display -->
<div class="panel panel-white card">
    <div class="panel-body">
        <h2>Podium des Joueurs Classés</h2>
        <?php
        if ($selected_activity !== null && $data_found) {
            // First, get the challenge ID from the selected activity
            $challenge_query = "SELECT id_challenge FROM activite WHERE `id-activite` = ?";
            $stmt_challenge_id = mysqli_prepare($con, $challenge_query);
            $current_challenge_id = null;
            
            if ($stmt_challenge_id) {
                mysqli_stmt_bind_param($stmt_challenge_id, "i", $selected_activity);
                mysqli_stmt_execute($stmt_challenge_id);
                $challenge_result = mysqli_stmt_get_result($stmt_challenge_id);
                if ($challenge_row = mysqli_fetch_assoc($challenge_result)) {
                    $current_challenge_id = intval($challenge_row['id_challenge']);
                }
                mysqli_free_result($challenge_result);
                mysqli_stmt_close($stmt_challenge_id);
            }
            
            // Get cumulative data for players who participated in the current activity,
            // but show their cumulative stats across all activities in the challenge
            // Pour le podium : "Pts Partie" doit refléter directement la cagnotte de la partie en cours
            $podium_query = "
    SELECT 
        m.`id-membre`,
        m.pseudo,
        p_current.`cagnotte` as points_partie,
        SUM(p.`points`) as points_total,
        (SUM(p.`gain`) / 10) as cagnotte_total,
        p_current.`classement`
    FROM membres m
    JOIN participation p_current ON p_current.`id-membre` = m.`id-membre`
    JOIN participation p ON p.`id-membre` = m.`id-membre`
    JOIN activite a ON p.`id-activite` = a.`id-activite`
    WHERE p_current.`id-activite` = ? 
        AND a.`id_challenge` = ?
        AND p_current.`classement` IS NOT NULL 
        AND p_current.`classement` > 0
    GROUP BY m.`id-membre`, m.pseudo, p_current.`cagnotte`, p_current.`classement`
    ORDER BY p_current.`classement` ASC";

            if ($stmt_podium = mysqli_prepare($con, $podium_query)) {
                mysqli_stmt_bind_param($stmt_podium, "ii", $selected_activity, $current_challenge_id);
                
                if (mysqli_stmt_execute($stmt_podium)) {
                    $podium_result = mysqli_stmt_get_result($stmt_podium);
                    
                    if (mysqli_num_rows($podium_result) > 0) {
                        // First, get ALL players from the entire challenge for general ranking
                        $all_challenge_players_query = "
                            SELECT 
                                m.`id-membre`,
                                SUM(p.`points`) as total_points,
                                (SUM(p.`gain`) / 10) as total_cagnotte
                            FROM membres m
                            JOIN participation p ON p.`id-membre` = m.`id-membre`
                            JOIN activite a ON p.`id-activite` = a.`id-activite`
                            WHERE a.`id_challenge` = ?
                            GROUP BY m.`id-membre`
                            ORDER BY total_points DESC, total_cagnotte DESC";
                        
                        $general_ranking = [];
                        if ($stmt_all_challenge = mysqli_prepare($con, $all_challenge_players_query)) {
                            mysqli_stmt_bind_param($stmt_all_challenge, "i", $current_challenge_id);
                            if (mysqli_stmt_execute($stmt_all_challenge)) {
                                $all_challenge_result = mysqli_stmt_get_result($stmt_all_challenge);
                                $rank = 1;
                                while ($challenge_row = mysqli_fetch_assoc($all_challenge_result)) {
                                    $general_ranking[$challenge_row['id-membre']] = $rank++;
                                }
                                mysqli_free_result($all_challenge_result);
                            }
                            mysqli_stmt_close($stmt_all_challenge);
                        }
                        
                        // Collect current game players
                        $all_players = [];
                        while ($row = mysqli_fetch_assoc($podium_result)) {
                            $all_players[] = $row;
                        }
                        
                        // Sort by current game classement for display
                        usort($all_players, function($a, $b) {
                            return intval($a['classement']) - intval($b['classement']);
                        });
                        
                        echo "<div class='table-responsive'>";
                        echo "<table class='data-table podium-table' id='podiumTable'>";
                        echo "<thead>";
                        echo "<tr>";
                        echo "<th class='cell-center sortable'>Podium</th>";
                        echo "<th class='sortable'>Nom</th>";
                        echo "<th class='cell-right sortable'>Pts Partie</th>";
                        echo "<th class='cell-center sortable'>Challenge.</th>";
                        echo "<th class='cell-right sortable'>Pts Chal.</th>";
                        echo "<th class='cell-right sortable'>Cagnotte</th>";
                        echo "<th class='cell-right sortable'>Nb Jetons</th>";
                        echo "</tr>";
                        echo "</thead>";
                        echo "<tbody>";
                        
                        foreach ($all_players as $player) {
                            $position = intval($player['classement']);
                            $rowClass = '';
                            switch ($position) {
                                case 1: $rowClass = 'background-color: #FFD700;'; break; // Gold
                                case 2: $rowClass = 'background-color: #C0C0C0;'; break; // Silver
                                case 3: $rowClass = 'background-color: #CD7F32;'; break; // Bronze
                                default: $rowClass = ($position <= $nb_joueurs_payes) ? 'background-color: #E8F5E9;' : '';
                            }
                            
                            // Calcul des jetons basé sur la cagnotte
                            // On utilise points_total (alias pour SUM(p.points)) pour les calculs cumulés si besoin
                            // Mais ici jetons est basé sur cagnotte_total (alias pour SUM(p.gain)/10)
                            $cagnotte_cumulative = floatval($player['cagnotte_total']);
                            $jetons = 35000 + ($cagnotte_cumulative * 200);
                            if ($jetons > 50000) {
                                $jetons = 50000;
                            }
                            
                            $general_rank = isset($general_ranking[$player['id-membre']]) ? $general_ranking[$player['id-membre']] : '-';
                            
                            echo "<tr style='$rowClass'>";
                            echo "<td class='cell-center'>" . ($position == 1 ? '🏆' : $position) . "</td>";
                            echo "<td>" . htmlspecialchars($player['pseudo']) . "</td>";
                            echo "<td class='cell-right'><strong>" . intval($player['points_partie']) . "</strong></td>";
                            echo "<td class='cell-center'><strong>" . $general_rank . "</strong></td>";
                            echo "<td class='cell-right'>" . intval($player['points_total']) . "</td>";
                            echo "<td class='cell-right'>" . number_format($cagnotte_cumulative, 2, ',', ' ') . "</td>";
                            echo "<td class='cell-right'>" . number_format($jetons, 0, ',', ' ') . "</td>";
                            echo "</tr>";
                        }
                        echo "</tbody></table></div>";
                    } else {
                        echo "<div class='alert alert-info'>Aucun joueur classé pour cette activité.</div>";
                    }
                } else {
                    echo "<div class='alert alert-danger'>Erreur lors de l'exécution de la requête podium.</div>";
                }
                mysqli_stmt_close($stmt_podium);
            } else {
                echo "<div class='alert alert-danger'>Erreur lors de la préparation de la requête podium.</div>";
            }
        } else {
            echo "<div class='alert alert-info'>Sélectionnez une activité pour voir le podium.</div>";
        }
        ?>
    </div>
</div>

                            </div>
                        </div>
                    </div>
                    <!-- end: BASIC EXAMPLE -->

                </div>
            </div>
            <!-- end: MAIN CONTAINER -->
    <?php include('include/footer.php'); ?>
    <?php include('include/setting.php'); ?>
        </div>
    </div><!-- /app -->

    <!-- Template JS -->
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
    <script src="assets/js/form-elements.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <!-- <script src="https://cdn.jsdelivr.net/npm/simple-datatables@latest" crossorigin="anonymous"></script> -->
    <!-- <script src="../js/datatables-simple-demo.js"></script> -->

    <!-- External bonus calculation script (bypasses CSP) - with cache buster -->
    <script src="assets/js/bonus-calculation.js?v=<?php echo time(); ?>"></script>
    
    <script>
        jQuery(document).ready(function() {

            // Function to update points in the UI
            function updatePoints(row) {
                const isChallenger = row.find('input[name$="[challenger]"]').is(':checked');
                const isTF = row.find('input[name$="[tf]"]').is(':checked');
                const isWin = row.find('input[name$="[win]"]').is(':checked');
                const classement = row.find('select[name$="[classement]"]').val();
                
                let points = 0;
                if (isChallenger) {
                    points = 1;
                    if (isTF) points += 1;
                    if (isWin || classement == '1') points += 1;
                }
                
                row.find('input[name$="[points]"]').val(points);
                row.find('.sort-value').last().text(points); // Update sort value for Pts column
                
                // Update footer total points
                let total = 0;
                $('input[name$="[points]"]').each(function() {
                    total += parseInt($(this).val()) || 0;
                });
                $('#footer-total-points').text(total);
            }

            // Sync Win with Classement
            $(document).on('change', 'select[name$="[classement]"]', function() {
                const row = $(this).closest('tr');
                if ($(this).val() == '1') {
                    row.find('input[name$="[win]"]').prop('checked', true);
                } else {
                    row.find('input[name$="[win]"]').prop('checked', false);
                }
                updatePoints(row);
            });

            // Sync ITM with Gain
            $(document).on('input change', 'input[name$="[gain]"]', function() {
                const row = $(this).closest('tr');
                const gain = parseFloat($(this).val()) || 0;
                row.find('input[name$="[itm]"]').prop('checked', gain > 0);
            });

            // Trigger points update on any relevant change
            $(document).on('change', 'input[name$="[challenger]"], input[name$="[tf]"], input[name$="[win]"]', function() {
                updatePoints($(this).closest('tr'));
            });

            // Ajout de la variable manquante
            const isAllActivitiesView = <?php echo isset($_SESSION['selected_activity']) ? 'false' : 'true'; ?>;
            const occupiedSeats = <?php echo isset($occupied_seats_json) ? $occupied_seats_json : '[]'; ?>;

            // Initialize DataTables with row filtering
            if ($('.data-table').length > 0) {
                $('.data-table').DataTable({
                    "paging": false,
                    "info": false,
                    "language": {
                        "search": "Rechercher Joueur:",
                        "zeroRecords": "Aucun joueur trouvé",
                        "emptyTable": "Aucune participation à afficher"
                    },
                    "ordering": true,
                    "columnDefs": [
                        { "orderable": false, "targets": [0, 2, 3] },
                        {
                            "targets": [4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16],
                            "render": function(data, type, row) {
                                if (type === 'sort' || type === 'type') {
                                    // For inputs and selects, get the value
                                    const $el = $(data);
                                    if ($el.is('input') || $el.is('select')) {
                                        if ($el.is(':checkbox')) return $el.is(':checked') ? 1 : 0;
                                        return parseFloat($el.val()) || 0;
                                    }
                                    // Fallback to searching inside the cell for the first input/select
                                    const $input = $(data).find('input, select');
                                    if ($input.length > 0) {
                                        if ($input.is(':checkbox')) return $input.is(':checked') ? 1 : 0;
                                        return parseFloat($input.val()) || 0;
                                    }
                                    // Check for sort-value span
                                    const $sortValue = $(data).find('.sort-value');
                                    if ($sortValue.length > 0) {
                                        return parseFloat($sortValue.text()) || 0;
                                    }
                                }
                                return data;
                            }
                        }
                    ],
                    "order": [[0, "desc"], [1, "asc"]],
                    "initComplete": function() {
                        // Add filter inputs for each column
                        this.api().columns().every(function() {
                            var column = this;
                            var header = $(column.header());
                            
                            // Skip filter for certain columns
                            if (header.hasClass('cell-center') || header.hasClass('cell-right')) {
                                return;
                            }
                            
                            var input = $('<input type="text" placeholder="Filtrer..." style="width:100%"/>')
                                .appendTo(header)
                                .on('keyup change', function() {
                                    if (column.search() !== this.value) {
                                        column.search(this.value).draw();
                                    }
                                });
                        });
                    }
                });
            }

            // Specific JS from original quick-part.php for seat selection
            const tableSelect = document.getElementById('table_reg_select');
            const siegeSelect = document.getElementById('siege_reg_select');
            // const activitySelect = document.getElementById('acti_reg_select'); // Not needed if relying on filter button

            // Function to update siege options based on selected table
            function updateSiegeOptions() {
                const selectedTable = tableSelect ? parseInt(tableSelect.value) : null;
                if (!siegeSelect) return;

                siegeSelect.innerHTML = '<option value="">-- Choisir Siège --</option>';

                if (!selectedTable) {
                    siegeSelect.disabled = true;
                    return;
                }

                const occupiedSeatsForTable = occupiedSeats
                    .filter(seat => seat.table === selectedTable)
                    .map(seat => seat.siege);

                let availableSeatsFound = false;
                // Limiter à 8 sièges par table
                for (let s = 1; s <= 8; s++) {
                    if (!occupiedSeatsForTable.includes(s)) {
                        const option = document.createElement('option');
                        option.value = s;
                        option.textContent = 'Siège ' + s;
                        siegeSelect.appendChild(option);
                        availableSeatsFound = true;
                    }
                }

                siegeSelect.disabled = !availableSeatsFound;
    if (!availableSeatsFound) {
        siegeSelect.innerHTML = '<option value="">-- Aucun siège libre --</option>';
    }
}

// Update recave select styling when challenger checkbox changes
$(document).on('change', 'input[name^="participations["][name$="[challenger]"]', function() {
    const recaveSelect = $(this).closest('tr').find('.recave-select');
    if (this.checked) {
        recaveSelect.css({'color': 'blue', 'font-weight': 'bold'});
    } else {
        recaveSelect.css({'color': '', 'font-weight': ''});
    }
});

// Apply initial styling
$('input[name^="participations["][name$="[challenger]"]:checked').each(function() {
    $(this).closest('tr').find('.recave-select')
        .css({'color': 'blue', 'font-weight': 'bold'});
});

// Event listener for table selection change
if (tableSelect) {
    tableSelect.addEventListener('change', updateSiegeOptions);
    // Initial call in case activity/table is pre-selected on load
    updateSiegeOptions();
}

// Initialize alpha keyboard
function initAlphaKeyboard() {
    const keyboard = $('.alpha-keyboard');
    const keys = $('.alpha-key');
    const membreSelect = $('#membre_select');
    
    // Ensure keyboard is visible and interactive
    keyboard.css({
        'display': 'flex',
        'pointer-events': 'auto'
    });

    // Bind click handlers
    keys.off('click').on('click', function() {
        const selectedLetter = $(this).data('letter').toUpperCase();
        console.log('Key clicked:', selectedLetter, this);

        // Visual feedback
        keys.removeClass('active');
        $(this).addClass('active');
        console.log('Active state applied to:', this);

                // Iterate through options and show/hide based on the selected letter
                let visibleCount = 0;
                membreSelect.find('option').each(function() {
                    const option = $(this);
                    const pseudo = option.text().toUpperCase();
                    
                    // Always show the default "Select Pseudo" option
                    if (option.val() === '') {
                        option.show();
                        return true;
                    }

                    if (selectedLetter === '') {
                        // Show all options if "Tous" is clicked
                        option.show();
                        visibleCount++;
                    } else {
                        // Hide options that don't start with the selected letter
                        if (pseudo.startsWith(selectedLetter)) {

                            option.show();
                            visibleCount++;
                            console.log('Showing:', option.text());
                        } else {
                            option.hide();
                        }
                    }
                });

                console.log('Total visible options:', visibleCount);
                
                // Reset the select value if the currently selected option is hidden
                if (membreSelect.find('option:selected').is(':hidden')) {
                    membreSelect.val('');
                }
                
                // Refresh select2 if it exists
                if (membreSelect.hasClass('select2-hidden-accessible')) {
                    membreSelect.select2();
                }
            });

    // Pour le bouton "Tous"
    $('.alpha-key-special').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
       
        
        $('.alpha-key').removeClass('active');
        $(this).addClass('active');
        $('#membre_select option').show();
        $('#membre_select').val('').focus();
       });
        
}

// Appeler la fonction initAlphaKeyboard si nécessaire
// initAlphaKeyboard();

// Add sidebar toggle functionality using event delegation
$(document).on('click', '.sidebar-toggler', function(e) {
    e.preventDefault();
    $('#app').toggleClass('app-sidebar-closed');
});

// DataTable for podium display
$('#podiumTable').DataTable({
        "paging": false,
        "info": false,
        "searching": true,
        "ordering": true,
        "columnDefs": [
            { 
                "targets": [0],     // Colonne Position
                "type": "num",
                "orderable": true
            },
            { 
                "targets": [1],     // Colonne Joueur
                "type": "string",
                "orderable": true
            },
            { 
                "targets": [2, 4, 5, 6],  // Colonnes Pts Partie, Points Totaux, Cagnotte, Jetons
                "type": "num",
                "orderable": true
            },
            { 
                "targets": [3],     // Colonne Classement Général
                "type": "num",
                "orderable": true
            }
        ],
        "order": [[0, "asc"]],     // Tri par défaut sur la position
        "language": {
            "search": "Rechercher:",
            "zeroRecords": "Aucun joueur trouvé"
        },
        "drawCallback": function(settings) {
            // Maintenir les couleurs après le tri
            $(this).find('tr').each(function(index) {
                const position = parseInt($(this).find('td:first').text()) || (index + 1);
                if (position === 1) $(this).css('background-color', '#FFD700');
                else if (position === 2) $(this).css('background-color', '#C0C0C0');
                else if (position === 3) $(this).css('background-color', '#CD7F32');
                else if (position <= <?php echo $nb_joueurs_payes ?? 0; ?>) 
                    $(this).css('background-color', '#E8F5E9');
            });
        }
    });
    
});
</script>
</body>
</html>
<?php
 if (isset($con) && $con instanceof mysqli) {
     mysqli_close($con);
 }
?>
