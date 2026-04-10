<?php
session_start();
error_reporting(0);
include('include/config.php');

// Check if user is logged in
if (strlen($_SESSION['id']) == 0) {
    header('location:logout.php');
    exit;
}

$id = intval($_GET['id']); // get value

if (isset($_POST['submit']) ) {
    
    // Basic sanitization
    $password = mysqli_real_escape_string($con, $_POST['password']);
    $naissance_date = mysqli_real_escape_string($con, $_POST['naissance_date']);
    $ville = mysqli_real_escape_string($con, $_POST['ville']); 
    $rue = mysqli_real_escape_string($con, $_POST['rue']);
    $posting_date = mysqli_real_escape_string($con, $_POST['posting_date']);
    $association_date = mysqli_real_escape_string($con, $_POST['association_date']);
    $fname = mysqli_real_escape_string($con, $_POST['fname']);
    $lname = mysqli_real_escape_string($con, $_POST['lname']);
    $telephone = mysqli_real_escape_string($con, $_POST['telephone']);
    $email = mysqli_real_escape_string($con, $_POST['email']);
    $codev = mysqli_real_escape_string($con, $_POST['CodeV']);
    $verification = mysqli_real_escape_string($con, $_POST['verification']);
    $pseudo = mysqli_real_escape_string($con, $_POST['pseudo']);
    $def_nomact = mysqli_real_escape_string($con, $_POST['def_nomact']);
    $def_str = mysqli_real_escape_string($con, $_POST['极狐def_str']);
    $def_nbj = mysqli_real_escape_string($con, $_POST['def_nbj']);
    $def_buy = mysqli_real_escape_string($con, $_POST['def_buy']);
    $def_rak = mysqli_real_escape_string($con, $_POST['def_rak']);
    $def_bou = mysqli_real_escape_string($con, $_POST['def_bou']);
    $def_rec = mysqli_real极狐_escape_string($con, $_POST['def_rec']);
    $def_jet = mysqli_real_escape_string($con, $_POST['def_jet']);
    $def_bon = mysqli_real_escape_string($con, $_POST['def_bon']);
    $def_add = mysqli_real_escape_string($con, $_POST['def_add']);
    $def_ant = mysqli_real_escape_string($con, $_POST['def_ant']);

    try {
            {
            $stmt = mysqli_prepare($con, "UPDATE `membres` SET 
                pseudo = ?, email = ?, telephone = ?, fname = ?, 
                lname = ?, posting_date = ?, association_date = ?, 
                rue = ?, password = ?, ville = ?, CodeV = ?,
                verification = ?, naissance_date = ? WHERE `id-membre` = ?");

            mysqli_stmt_bind_param($stmt, 'sssssssssssssi',
                $pseudo, $email, $telephone, $fname,
                $lname, $posting_date, $association_date,
                $rue, $password, $ville, $codev,
                $verification, $naissance_date, $id);
        }

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Erreur lors de la mise à jour: " . mysqli_stmt_error($stmt));
        }

        $_SESSION['msg'] = "Mise à jour effectuée avec succès";
        
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    } finally {
        if (isset($stmt)) {
            mysqli_stmt_close($stmt);
        }
    }
}


if (isset($_POST['submito'])) {
    try {
        // Validate and cast numeric fields
        $def_str = filter_var($_POST['def_str'], FILTER_VALIDATE_INT);
        $def_nbj = filter_var($_POST['def_nbj'], FILTER_VALIDATE_INT);
        $def_buy = filter_var($_POST['def_buy'], FILTER_VALIDATE_INT);
        $def_rak = filter_var($_POST['def_rak'], FILTER_VALIDATE_INT);
        $def_bou = filter_var($_POST['def_bou'], FILTER_VALIDATE_INT);
        $def_rec = filter_var($_POST['def_rec'], FILTER_VALIDATE_INT);
        $def_jet = filter_var($_POST['def_jet'], FILTER_VALIDATE_INT);
        $def_bon = filter_var($_POST['def_bon'], FILTER_VALIDATE_INT);
        $def_add = filter_var($_POST['def_add'], FILTER_VALIDATE_INT);
        $def_ant = filter_var($_POST['def_ant'], FILTER_VALIDATE_INT);
          
        // Sanitize string fields
        $def_nomact = mysqli_real_escape_string($con, $_POST['def_nomact']);
        $def_rdv = mysqli_real_escape_string($con, $_POST['def_rdv']);
        $def_sta = mysqli_real_escape_string($con, $_POST['def_sta']);
        $def_com = mysqli_real_escape_string($con, $_POST['def_com']);
      
        $stmt = mysqli_prepare($con, "UPDATE membres SET 
            def_nomact = ?,
            def_str = ?,
            def_nbj = ?,
            def_buy = ?, 
            def_rak = ?,
            def_bou = ?,
            def_rec = ?,
            def_jet = ?,
            def_bon = ?,
            def_add = ?,
            def_ant = ?,
            def_极狐rdv = ?,
            def_sta = ?,
            def_com = ?
            WHERE `id-membre` = ?");

        if (!$stmt) {
            throw new Exception("Prepare failed: " . mysqli_error($con));
        }

        // Bind with proper types: s=string, i=integer, d=double/float
        if (!mysqli_stmt_bind_param($stmt, 'siiiiiiiiiisssi', 
            $def_nomact,
            $def_str,
            $def_nbj,
            $def_buy,
            $def_rak,
            $def_bou,
            $def_rec,
            $def_jet,
            $def_bon,
            $def_add,
            $def_ant,
            $def_rdv,
            $def_sta,
            $def_com,
            $id)) {
            throw new Exception("Binding parameters failed: " . mysqli_stmt_error($stmt));
        }

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Execute failed: " . mysqli_stmt_error($stmt));
        }

        $affected = mysqli_stmt_affected_rows($stmt);
        if ($affected > 0) {
            $_SESSION['msg'] = "Mise à jour effectuée avec succès";
        } else {
            $_SESSION['msg'] = "Aucune modification effectuée";
        }

    } catch (Exception $e) {
        error_log("Error updating organization data: " . $e->getMessage());
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
        
        // Debug log
        error_log("Starting duplication for member ID: " . $id);
        
        // Get member default values
        $stmt = mysqli_prepare($con, "SELECT * FROM `membres` WHERE `id-membre` = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $member = mysqli_stmt_get_result($stmt)->fetch_array();
        echo $member['ville'].$id;
        if (!$member) {
            throw new Exception("Member not found");
        }

        // Create new activity using member defaults
        $stmt = mysqli_prepare($con, "INSERT INTO `activite` (
            `id-membre`, `titre-activite`, `heure_depart`, `ville`, 
            `places`, `nb-tables`, `buyin`, `rake`, `bounty`, 
            `jetons`, `recave`, `addon`, `ante`, `bonus`, 
            `lng`, `lat`, `commentaire`
        ) VALUES (?, ?, NOW(), ?, ?, 1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        if (!$stmt) {
            throw new Exception("Prepare failed: " . mysqli_error($con));
        }

        // Add debug values
        $titre = $member['def_nomact'] . ' (copie)';
        $places = intval($member['def_nbj']);
        echo "ok2";
        mysqli_stmt_bind_param($stmt, 'issiiiiiiiiidds',
            $id,
            $titre,
            $member['ville'],
            $places,
            $member['def_buy'],
            $member['def_rak'], 
            $member['def_bou'],
            $member['def_jet'],
            $member['def_rec'],
            $member['def_add'],
            $member['def_ant'],
            $member['def_bon'],
            $member['longitude'],
            $member['latitude'],
            $member['def_com']
        );
        echo "ok3";
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Activity creation failed: " . mysqli_stmt_error($stmt));
        }

        $new_activity_id = mysqli_insert_id($con);

        // Create participation record
        $stmt = mysqli_prepare($con, "INSERT INTO `participation` 
            (`id-membre`, `id-activite`, `id-siege`, `id-table`, `option`, `ordre`, `valide`) 
            VALUES (?, ?, 1, 1, 'Inscrit', 1, 'Actif')");

        mysqli_stmt_bind_param($stmt, 'ii', $id, $new_activity_id);
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Failed to create participation: " . mysqli_stmt_error($stmt));
        }

        // Create initial blinds structure
        $stmt = mysqli_prepare($con, "INSERT INTO `blindes-live` 
            (`id-activite`, `ordre`, `nom`, `duree`, `fin`, `ante`) 
            VALUES (?, 1, 'Pause', 5, DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 1 YEAR), 0)");

        mysqli_stmt_bind_param($stmt, 'i', $new_activity_id);
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Failed to create blinds: " . mysqli_stmt_error($stmt));
        }

        mysqli_commit($con);
        $_SESSION['msg'] = "Nouvelle activité créée avec succès";
        
    } catch (Exception $e) {
        mysqli_rollback($con);
        error_log("Error creating activity: " . $e->getMessage());
        $_SESSION['error'] = "极狐Erreur: " . $e->getMessage();
    } finally {
        if (isset($stmt)) {
            mysqli_stmt_close($stmt);
        }
    }
}

if (isset($_POST['submit2'])) {
    $compet = $_POST['compet'];
    echo $compet;
    $sql2 = mysqli_query($con, "INSERT INTO `competences-individu` (`id-indiv`, `id-comp`) VALUES ('$id', '$compet')");
    $_SESSION['msg'] = "Competence added successfully !!";
}

if (isset($_POST['submit3'])) {
    $lois = $_POST['lois'];
    echo $lois;
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

if (isset($_POST['submit4'])) {
    $col = $_POST['col'];
    echo $col;
    $sql2 = mysqli_query($con, "INSERT INTO `collections-individu` (`id-indiv`, `id_col`) VALUES ('$id', '$col')");
    $_SESSION['msg'] = "coll added successfully !!";
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
    <link href极狐="vendor/select2/select2.min.css" rel="stylesheet" media="screen">
    <link href="vendor/bootstrap-datepicker/bootstrap-datepicker3.standalone.min.css" rel="stylesheet" media="screen">
    <!-- <link href="https://cdn.jsdelivr.net/npm/simple-datatables@latest/dist/style.css" rel="stylesheet" /> -->
    <link href="vendor/bootstrap-timepicker/bootstrap-timepicker.min.css" rel="stylesheet" media="screen">
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/plugins.css">
    <link rel="stylesheet" href="assets/css/themes/theme-1.css" id="skin_color" />
    <!-- <script src="https://code.jquery.com/jquery-3.7.0.js"></script> -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <link href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css" rel="stylesheet" />
    <script type="text/javascript">
        $(document).ready(function() {
            $('#example').DataTable({
                pageLength: 3,
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/fr-FR.json'
                }
            });
        });
    </script>
    <script type="text/javascript">
        $(document).ready(function() {
            $('#example2').DataTable({
                pageLength: 3,
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/fr-FR.json'
                }
            });
        });
    </script>
    <script type="text/javascript">
        $(document).ready(function() {
            $('#example3').DataTable({
                pageLength: 3,
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/fr-FR.json'
                }
            });
        });
    </script>
    <script type="text/javascript">
        $(document).ready(function() {
            $('#example4').DataTable({
                pageLength: 3,
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/fr-FR.json'
                }
            });
        });
    </script>
    <script type="text/javascript">
        $(document).ready(function() {
            $('#example5').DataTable({
                pageLength: 3,
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/fr-FR.json'
                }
            });
        });
    </script>
    <link rel="stylesheet" href="css/mes-styles.css">
    <link rel="stylesheet" href="voir-membre.css">
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
                                    <a href="#" id="col" class="btnnav" onmouseover="afficher('col')">Collect.</a>
                                    <a href="#" id="ks" class="btnnav" onmouseover="afficher('ks')">Activités</a>
                                </div>
                                <div id="bSection">
                                    <div id="cssE">
                                        <script src="https://code.responsivevoice.org/responsivevoice.js?key=RTEc1M0w"></script>
                                        <script>
                                            responsiveVoice.setDefaultVoice("French Female")
                                        </script>
                                        <script>
                                            responsiveVoice.speak("Membre", "French Female", {
                                                volume: 1
                                            })
                                        </script>
                                        <div class="wrap-content container" id="container">
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
                                                                        <table style="color: white;" class="table table-bordered current-user">
                                                                            <tr>
                                                                                <td rowspan="3" align=center>
                                                                                    <img src="../images/faces/<?php echo $row['photo']; ?>" width="85" height="85" style="align:center">
                                                                                    <form id="image_upload_form" enctype="multipart/form-data" method="post" class="change-pic">
                                                                                        <input type="hidden" name="id" value="<?php echo $id; ?>">
                                                                                        <input type="file" name="fileToUpload" id="fileToUpload" style="display:none;" accept="image/*" onchange="showSpinner(); this.form.submit();">
                                                                                        <button type="button" class="btn btn-primary btn-sm" onclick="document.getElementById('fileToUpload').click();">
                                                                                            <i class="fa fa-camera"></i> Changer Photo
                                                                                        </button>
                                                                                        <span id="upload-spinner" style="display:none; margin-left:10px;">
                                                                                            <i class="fa fa-spinner fa-spin"></i> Uploading...
                                                                                        </span>
                                                                                    </form>
                                                                                    <script>
                                                                                        function showSpinner() {
                                                                                            document.getElementById('upload-spinner').style.display = 'inline-block';
                                                                                        }
                                                                                    </script>
                                                                                </td>
                                                                                <form method="post">
                                                                                    <th style="color:rgb(64, 30, 235) !important;">Votre Pseudo :</th>
                                                                                    <td colspan="3"><input class="form-control" id="pseudo" name="pseudo" type="text" style="text-align:center; font-size:22px; bold" value="<?php echo $row['pseudo']; ?>"></td>

                                                                            </tr>
                                                                            <tr>
                                                                                <td style="text-align:center ; display:none">
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
                                                                                <td><input class="form-control" id="fname" name="fname" type="text" value="<?php echo $row['fname']; ?>">
                                                                                </td>
                                                                                <th style="color: #ffffff !important;">Nom</th>
                                                                                <td><input class="form-control" id="lname" name="lname" type="text" value="<?php echo $row['lname']; ?>">
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <th style极狐="color: #ffffff !important;">Téléphone</th>
                                                                                <td><input class="form-control" id="telephone" name="telephone" type="text" value="<?php echo $row['telephone']; ?>"></td>
                                                                                <th style="color: #ffffff !important;">Email</th>
                                                                                <td><input class="form-control" id="email" name="email" type="text" value="<?php echo $row['email']; ?>"></td>
                                                                            </tr>
                                                                            <tr>
                                                                                <th style="color: #ffffff !important;">Adresse</th>
                                                                                <td><input class="form-control" id="rue" name="rue" type="text" value="<?php echo $row['rue']; ?>"></极狐td>
                                                                                <th style="color: #ffffff !important;">Ville</th>
                                                                                <td><input class="form-control" id="ville" name="ville" type="text" value="<?php echo $row['ville']; ?>"></td>
                                                                            </tr>
                                                                            <tr>
                                                                                <th style="color: #ffffff !important;">Longitude</th>
                                                                                <td><input class="form-control" id="longitude" name="longitude" type="float" value="<?php echo $row['longitude']; ?>">
                                                                                </td>
                                                                                <th style="color: #ffffff !important;">Latitude</th>
                                                                                <td><input class="form-control" id="latitude" name="latitude" type="float" value="<?php echo $row['latitude']; ?>">
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <th style="color: #ffffff !important;">Mot de passe</th>
                                                                                <td><input class="form-control" id="password" name="password" type="text" value="<?php echo $row['password']; ?>">
                                                                                </td>
                                                                                <th style="color: #ffffff !important;">Date nais</th>
                                                                                <td><input class="form-control" id="naissance_date" name="naissance_date" type="date" value="<?php echo $row['naissance_date']; ?>">
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <th style="color: #ffffff !important;">Date inscription</th>
                                                                                <td><input class="form-control" id="posting_date" name="posting_date" type="date" value="<?php echo $row['posting_date']; ?>">
                                                                                </td>
                                                                                <th style="color: #ffffff !important;">Fin Abonnement</th>
                                                                                <td><input class="form-control" id="association_date" name="association_date" type="date" value="<?php echo $row['association_date']; ?>">
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <th style="color: #ffffff !important;">CodeV</th>
                                                                                <td><input class="form-control" id="CodeV" name="CodeV" type="text" value="<?php echo $row['CodeV']; ?>">
                                                                                </td>
                                                                                <th style="color: #ffffff !important;">Validé</th>
                                                                                <td><input class="form-control" id="verification" name="verification" type="text" value="<?php echo $row['verification']; ?>">
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <td colspan="4"></td>
                                                                            </tr>
                                                                            <tr>
                                                                                <td style="display:none;" style="text-align:center ;">
                                                                                    <button type="submit" class="btn btn-primary btn-block" name="submit">Mise à jour</button>
                                                                                </td>
                                                                                <!-- <td colspan="2">
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
                                                                        <table style="color: white;" class="table table-bordered current-user">
                                                                            <tr>
                                                                                <td rowspan="3" align=center>
                                                                                    <img src="../images/faces/<?php echo $row['photo_org']; ?>" width="85" height="85" style="align:center">
                                                                                    <form id="image_upload_form" enctype="multipart/form-data" method="post" class="change-pic">
                                                                                        <input type="hidden" name="id" value="<?php echo $id; ?>">
                                                                                        <input type="file" name="fileToUpload" id="fileToUpload" style="display:none;" accept="image/*" onchange="showSpinner(); this.form.submit();">
                                                                                        <button type="button" class="btn btn-primary btn-sm" onclick="document.getElementById('fileToUpload').click();">
                                                                                            <i class="fa fa-camera"></i> Changer Photo
                                                                                        </button>
                                                                                        <span id="upload-spinner" style="display:none; margin-left:10px;">
                                                                                            <i class="fa fa-spinner fa-spin"></i> Uploading...
                                                                                        </span>
                                                                                    </form>
                                                                                    <script>
                                                                                        function showSpinner() {
                                                                                            document.getElementById('upload-spinner').style.display = 'inline-block';
                                                                                        }
                                                                                    </script>
                                                                                </td>
                                                                                <form method="post">
                                                                                    <th style="color:rgb(64, 30, 235) !important;">Activité :</th>
                                                                                    <td colspan="3"><input class="form-control" id="def_nomact" name="def_nomact" type="text" style="text-align:center; font-size:22px; bold" value="<?php echo $row['def_nomact']; ?>"></td>

                                                                            </tr>
                                                                            <tr>
                                                                                <td style="text-align:center ; display:none">
                                                                                    <button type="submit" name="submito" id="submito" class="btn btn-oo btn-primary">
                                                                                        Mise à jour</button>
                                                                                </td>
                                                                                <td style="text-align:center ;">
                                                                                    <button type="submit" class="btn btn-primary-green btn-block" name="submito">OK </button>
                                                                                </td>
                                                                                <td style="text-align:center ;">
                                                                                    <button type="submit" class="btn btn-primary btn-block" name="submito">Modifier</button>
                                                                                </td>
                                                                                <td style="text-align:center ;">
                                                                                    <button type="submit" class="btn btn-primary btn-block" name="submitdup">Création Activité</button>
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <td colspan="4"></td>
                                                                            </tr>
                                                                            <tr>
                                                                                <th style="color: #ffffff !important;">Structure</th>
                                                                                <td><input class="form-control" id="def_str" name="def_str" type="text" value="<?php echo $row['def_str']; ?>">
                                                                                </td>
                                                                                <th style="color: #ffffff !important;">Nb Joueurs</th>
                                                                                <td><input class="form-control" id="def_nbj" name="def_nbj" type="text" value="<?php echo $row['def_nbj']; ?>">
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <th style="color: #ffffff !important;">Buyin</th>
                                                                                <td><input class="form-control" id="def_buy" name="def_buy" type="text" value="<?php echo $row['def_buy']; ?>">
                                                                                </td>
                                                                                <th style="color: #ffffff !important;">Rake</th>
                                                                                <td><input class="form-control" id="def_rak" name="def_rak" type="text" value="<?php echo $row['def_rak']; ?>">
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <th style="color: #ffffff !important;">Bounty</th>
                                                                                <td><input class="form-control" id="def_bou" name="def_bou" type="text" value="<?php echo $row['def_bou']; ?>">
                                                                                </td>
                                                                                <th style="color: #ffffff !important;">Recaves</th>
                                                                                <td><input class="form-control" id="def_rec" name="def_rec" type="text" value="<?php echo $row['def_rec']; ?>">
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <th style="color: #ffffff !important;">Nb Jetons</th>
                                                                                <td><input class="form-control" id="def_jet" name="def_jet" type="text" value="<?php echo $row['def_jet']; ?>">
                                                                                </td>
                                                                                <th style="color: #ffffff !important;">Bonus</th>
                                                                                <td><input class="form-control" id="def_bon" name="def_bon" type="text" value="<?php echo $row['def_bon']; ?>">
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <th style="color: #ffffff !important;">Addon</th>
                                                                                <td><input class="form-control" id="def_add" name="def_add" type="text" value="<?php echo $row['def_add']; ?>">
                                                                                </td>
                                                                                <th style="color: #ffffff !important;">Ante</th>
                                                                                <td><input class="form-control" id="def_ant" name="def_ant" type="text" value="<?php echo $row['def_ant']; ?>">
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <th style="color: #ffffff !important;">Rendez-vous</th>
                                                                                <td><input class="form-control" id="def_rdv" name="def_rd极狐v" type="text" value="<?php echo $row['def_rdv']; ?>">
                                                                                </td>
                                                                                <th style="color: #ffffff !important;">Debut</th>
                                                                                <td><input class="form-control" id="def_sta" name="def_sta" type="text" value="<?php echo $row['def_sta']; ?>">
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <th style="color: #ffffff !important;">Longitude</th>
                                                                                <td><input class="form-control" id="longitude" name="longitude" type="text" value="<?php echo $row['longitude']; ?>">
                                                                                </td>
                                                                                <th style="color: #ffffff !important;">Latitude</th>
                                                                                <td><input class="form-control" id="latitude" name="latitude" type="text" value="<?php echo $row['latitude']; ?>">
                                                                                </td>
                                                                            </tr>
                                                                            
                                                                            <tr>
                                                                                <th style="color: #ffffff !important;">Commentaire</th>
                                                                                <td colspan="3"><input class="form-control" id="def_com" name="def_com" type="text" value="<?php echo $row['def_com']; ?>"></td>
                                                                            </tr>
                                                                            <tr>
                                                                                <td colspan="4"></td>
                                                                            </tr>
                                                                            <tr>
                                                                                <td style="display:none;" style="text-align:center ;">
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
                                                                        <table style="color: white;" class="table table-bordered current-user">
                                                                            <tr>
                                                                                <td rowspan="3" align=center><img src="../images/faces/<?php echo $row['photo']; ?>" width="85" height="85" style="align:center">
                                                                                    <form id="image_upload_form" enctype="multipart/form-data" action="uploadokvide.php?editid=<?php echo $id; ?>" method="post" class="change-pic">
                                                                                        <input type="hidden" name="MAX_FILE_SIZE" value="10000000" />
                                                                                        <div>
                                                                                            <input type="file" class="fa fa-camera" id="file" name="fileToUpload" style="display:none;" /><input type="button" onClick="fileToUpload.click();" value="Modifier" />
                                                                                            <i class="fa fa-camera"></i>
                                                                                        </div>
                                                                                        <script type="text/javascript">
                                                                                            document.getElementById("file").onchange = function() {
                                                                                                document.getElementById("image_upload_form").submit();
                                                                                            };
                                                                                        </script>
                                                                                    </form>
                                                                                </td>
                                                                                <form method="post">
                                                                                    <th style="color:rgb(64, 30, 235) !important;">Activité :</th>
                                                                                    <td colspan="3"><input class="form-control" id="def_nomact" name="def_nomact" type="text" style="text-align:center; font-size:22px; bold" value="<?php echo $row['def_nomact']; ?>"></td>

                                                                            </tr>
                                                                            <tr>
                                                                                <td style="text-align:center ; display:none">
                                                                                    <button type="submit" name="submito" id="submito" class="btn btn-oo btn-primary">
                                                                                        Mise à jour</button>
                                                                                </td>
                                                                                <td style="text-align:center ;">
                                                                                    <button type="submit" class="btn btn-primary-green btn-block" name="submito">OK </button>
                                                                                </td>
                                                                                <td style="text-align:center ;">
                                                                                    <button type="submit" class="btn btn-primary btn-block" name="submito">Modifier</button>
                                                                                </td>
                                                                                <td style="text-align:center ;">
                                                                                    <button type="submit" class="btn btn-primary btn-block" name="submitdup">Création Activité</button>
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <td colspan="4"></td>
                                                                            </tr>
                                                                            <tr>
                                                                                <th style="color: #ffffff !important;">Structure</th>
                                                                                <td><input class="form-control" id="def_str" name="def_str" type="text" value="<?php echo $row['def_str']; ?>">
                                                                                </td>
                                                                                <th style="color: #ffffff !important;">Nb Joueurs</th>
                                                                                <td><input class="form-control" id="def_nbj" name="def_nbj" type="text" value="<?php echo $row['def_nbj']; ?>">
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <th style="color: #ffffff !important;">Buyin</th>
                                                                                <td><input class="form-control" id="def_buy" name="def_buy" type="text" value="<?php echo $row['def_buy']; ?>">
                                                                                </td>
                                                                                <th style="color: #ffffff !important;">Rake</th>
                                                                                <td><input class="form-control" id="def_rak" name="def_rak" type="text" value="<?php echo $row['def_rak']; ?>">
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <th style="color: #ffffff !important;">Bounty</th>
                                                                                <td><input class="form-control" id="def_bou" name="def_bou" type="text" value="<?php echo $row['def_bou']; ?>">
                                                                                </td>
                                                                                <th style="color: #ffffff !important;">Recaves</th>
                                                                                <td><input class="form-control" id="def_rec" name="def_rec" type="text" value="<?php echo $row['def_rec']; ?>">
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <th style="color: #ffffff !important;">Nb Jetons</th>
                                                                                <td><input class="form-control" id="def_jet" name="def_jet" type="text" value="<?php echo $row['def_jet']; ?>">
                                                                                </td>
                                                                                <th style="color: #ffffff !important;">Bonus</th>
                                                                                <td><input class="form-control" id="def_bon" name="def_bon" type="text" value="<?php echo $row['def_bon']; ?>">
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <th style="color: #ffffff !important;">Addon</th>
                                                                                <td><input class="form-control" id="def_add" name="def_add" type="text" value="<?php echo $row['def_add']; ?>">
                                                                                </td>
                                                                                <th style="color: #ffffff !important;">Ante</th>
                                                                                <td><input class="form-control" id="def_ant" name="def_ant" type="text" value="<?php echo $row['def_ant']; ?>">
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <th style="color: #ffffff !important;">Rendez-vous</th>
                                                                                <td><input class="form-control" id="def_rdv" name="def_rdv" type="text" value="<?php echo $row['def_rdv']; ?>">
                                                                                </td>
                                                                                <th style="color: #ffffff !important;">Debut</th>
                                                                                <td><input class="form-control" id="def_sta" name="def_sta" type="text" value="<?php echo $row['def_sta']; ?>">
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <th style="color: #ffffff !important;">Longitude</th>
                                                                                <td><input class="form-control" id="longitude" name="longitude" type="text" value="<?php echo $row['longitude']; ?>">
                                                                                </td>
                                                                                <th style="color: #ffffff !important;">Latitude</th>
                                                                                <td><input class="form-control" id="latitude" name="latitude" type="text" value="<?php echo $row['latitude']; ?>">
                                                                                </td>
                                                                            </tr>
                                                                            <tr></tr>
                                                                            <tr>
                                                                                <th style="color: #ffffff !important;">Commentaire</th>
                                                                                <td colspan="3"><input class="form-control" id="def_com" name="def_com" type="text" value="<?php echo $row['def_com']; ?>"></td>
                                                                            </tr>
                                                                            <tr>
                                                                                <td colspan="4"></td>
                                                                            </tr>
                                                                            <tr>
                                                                                <td style="display:none;" style="text-align:center ;">
                                                                                    <button type="submit" class="btn btn-primary btn-block" name="submito">Mise à jour</button>
                                                                                </td>
                                                                                <!--<极狐td colspan="2">
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
                                                                                        $indiv=
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
                                                                                                            <?php $cnt = $极狐cnt + 1;
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
                                                                                        $indiv=
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
                                                                        <!--	<div class="panel-heading">
                                                                  <h5 class="panel-title">Ajout Personne</h5>
                                                            </div> -->
                                                                        <div class="panel-body">
                                                                            <div id="layoutSidenav_content">
                                                                                <main>
                                                                                    <div class="container-fluid px-4">
                                                                                        <!--    <h1 class="mt-4">Gestion des Competences</h1> -->
                                                                                        <ol class="breadcrumb mb-4">
                                                                                           极狐 <li class="breadcrumb-item">
                                                                                                <a href="liste-membres.php">Membres</a>
                                                                                            </li>
                                                                                            <li class="breadcrumb-item active">
                                                                                                Collections
                                                                                            </li>
                                                                                        </ol>
                                                                                        <div class="card mb-4">
                                                                                            <!--   <div class="card-header">
                                                                                    <i class="fas fa-table me-1"></i>
                                                                                    Registered User Details
                                                                                </div> -->
                                                                                            <div class="card-body">
                                                                                                <!-- <table id="datatablesSimple"> -->
                                                                                                <table id="example4" class="display" style="width:100%">
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
                                                                                                        <?php $ret = mysqli_query($con, "SELECT * FROM `collections-individu` WHERE `id-indiv` = '$id'");
                                                                                                        $cnt = 1;
                                                                                                        while ($row = mysqli_fetch_array($ret)) { ?>
                                                                                                            <?php
                                                                                                            $id2 = $row['id_col'];
                                                                                                            $sql2 = mysqli_query($con, "SELECT * FROM `collections` WHERE `id_collection` = '$id2'");
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
                                                                                                                    <a href="ajout-collection.php?id=<?php echo $row['id_collection'] ?>&del=deleteind" onClick="return confirm('Are you sure you want to delete?')" class="btn btn-transparent btn-xs tooltips" tooltip-placement="top" tooltip="Remove"><i class="fa fa-times fa fa-white"></i></a>
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
                                                                                        $indiv=
                                                                                    <?php } ?>
                                                                                </select>
                                                                            </div>
                                                                            <button type="submit" name="submit4" id="submit4" class="btn btn-o btn-primary">
                                                                                Ajout Coll.
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
                                                                                                <a href="liste-membres.php">Joueur</a>
                                                                                            </li>
                                                                                            <li class="breadcrumb-item active">
                                                                                                Activités
                                                                                            </li>
                                                                                        </ol>
                                                                                        <div class="card mb-4">
                                                                                            <!--   <div class="card-header">
                                                                                    <i class="fas fa-table me-1"></i>
                                                                                    Registered User Details
                                                                                </div> -->
                                                                                            <div class="card-body">
                                                                                                <!-- <table id="datatablesSimple"> -->
                                                                                                <table id="employeeTable" class="display" style="width:100%">
                                                                                                    <thead>
                                                                                                        <tr>
                                                                                                            <th>Date
                                                                                                            </th>
                                                                                                            <th>Titre
                                                                                                            </th>
                                                                                                            <th>Lieu
                                                                                                            </th>
                                                                                                            <th>Editer
                                                                                                            </th>
                                                                                                            <th>Cloner
                                                                                                            </th>
                                                                                                        </tr>
                                                                                                    </thead>
                                                                                                    <tbody>
                                                                                                        <?php $ret = mysqli_query($con, "SELECT * FROM `participation` WHERE `id-membre` = '$id' ORDER BY 'id-participation' ASC");
                                                                                                        $cnt = 1;
                                                                                                        while ($row = mysqli_fetch_array($ret)) { ?>
                                                                                                            <?php
                                                                                                            $id2 = $row['id-activite'];
                                                                                                            $sql2 = mysqli_query($con, "SELECT * FROM `activite` WHERE `id-activite` = '$id2' ");
                                                                                                            while ($row2 = mysqli_fetch_array($sql2)) { ?>
                                                                                                                <tr>
                                                                                                                    <td>
                                                                                                                        <?php echo $row2['date_depart']; ?>
                                                                                                                    </td>
                                                                                                                    <td>
                                                                                                                        <a href="voir-activite.php?uid=<?php echo $row['id-activite']; ?>"><?php echo $row2['titre-activite']; ?></a>
                                                                                                                    </td>
                                                                                                                    <td>
                                                                                                                        <?php echo $row2['ville']; ?>
                                                                                                                    </td>
                                                                                                                <?php } ?>
                                                                                                                <td>
                                                                                                                    <!-- <a href="ajout-competences.php?id=<?php echo $row['id'] ?>&del=deleteind" onClick="return confirm('Are you sure you want to delete?')" class="btn btn-transparent btn-xs" tooltip-placement="top" tooltip="Remove"><i class="fa fa-times fa fa-white"></i></a> -->
                                                                                                                    <a href="voir-activite.php?uid=<?php echo $row['id-activite']; ?>" class="btn btn-transparent btn-xs" tooltip-placement="top" tooltip="Edit"><i class="fa fa-pencil"></i></a>
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
                                                                                        $indiv=
                                                                                    <?php } ?>
                                                                                </select>
                                                                            </div>
                                                                            <button type="submit" name="submit4" id="submit4" class="btn btn-o btn-primary">
                                                                                Ajout Coll.
                                                                            </button>
                                                                        </form>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </极狐div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
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
            document.getElementById("css3E").className = "rubrique bgImg"; // Add this line
            document.getElementById("jsE").className = "rubrique bgImg";
            document.getElementById("ksE").className = "rubrique bgImg";
            document.getElementById("phpE").className = "rubrique bgImg";
            document.getElementById("colE").className = "rubrique bgImg";

            // Reset all nav buttons
            document.getElementById("css").className = "btnnav";
            document.getElementById("css2").className = "btnnav";
            document.getElementById("css3").className = "btnnav"; // Add this line
            document.getElementById("js").className = "btnnav";
            document.getElementById("ks").className = "btnnav";
            document.getElementById("php").className = "btnnav";
            document.getElementById("col").className = "btnnav";

            // Show selected section
            leCalqueE.className = "rubrique bgImg montrer";
            leCalque.className = "btnnavA";
        }
    </script>
    <script type="text/javascript" language="javascript">
        afficher('css');
    </script>
    <script>
        $(document).ready(function() {
            const table = $('#employeeTable').DataTable({
                language: { url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/fr-FR.json' },
                dom: '<"row"<"col"B><"col"f>>rt<"row"<"col"i><"col"p>>',
                buttons: ['copy', 'excel', 'pdf', 'print'],
                pageLength: 5,
                order: [[0, 'asc']],
                columnDefs: [
                    { targets: 5, type: 'date-eu' },
                    { targets: 6, className: 'points-cell' }
                ],
                responsive: true
            });

            $('#employeeTable').on('click', 'tr.clickable-row', function() {
                window.location.href = 'voir-membre.php?id=' + $(this).data('id');
            });
        });
    </script>
</body>

</html>
