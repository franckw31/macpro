<?php
session_start();
error_reporting(0);
include('include/config.php');

$filterMemberId = isset($_GET['uid']) ? $_GET['uid'] : null;

if (isset($_GET['del'])) {
    $adminid = $_GET['uid'];
    $del = $_GET['del'];
    echo "-".$adminid."-";
    echo "-".$del."-";
    $msg = mysqli_query($con, "DELETE FROM activite WHERE `activite`.`id-activite` = '$adminid' ");
    echo "ok";
    if ($msg) {
        echo "<script>alert('Data deleted');</script>";
    }
}

if (isset($_GET['compact']) && $_GET['compact'] == 'true') {
    $uid = $_GET['uid'];
    
    $ret = mysqli_query($con, "SELECT DISTINCT a.*, m.pseudo 
        FROM activite a 
        JOIN participation p ON a.`id-activite` = p.`id-activite` 
        JOIN membres m ON p.`id-membre` = m.`id-membre`
        WHERE p.`id-membre` = '$uid' 
        AND a.date_depart >= CURDATE()
        ORDER BY a.date_depart ASC");
    
    if (mysqli_num_rows($ret) > 0) {
        echo "<div style='font-size:12px; line-height:1.0;'>";
        while ($row = mysqli_fetch_array($ret)) {
            echo "<div style='padding:4px 0; border-bottom:1px solid #eee;'>";
            echo "<a href='/panel/voir-activite.php?uid=" . $row['id-activite'] . "' style='text-decoration:underline; color:#1a73e8;'>";
            echo date('d/m', strtotime($row['date_depart'])) . " - " . $row['ville'] . " - " . $row['buyin']."€";
            echo "</a>";
            echo "</div>";
        }
        echo "</div>";
    } else {
        echo "<div style='padding:4px; text-align:center;'>Aucune partie à venir</div>";
    }
    exit;
}

if (strlen($_SESSION['id'] == 0)) {
    header('location:logout.php');
} else {
    ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>Admin | liste Activités</title>
    <link
        href="http://fonts.googleapis.com/css?family=Lato:300,400,400italic,600,700|Raleway:300,400,500,600,700|Crete+Round:400italic"
        rel="stylesheet" type="text/css" />
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
    <link href="https://cdn.jsdelivr.net/npm/simple-datatables@latest/dist/style.css" rel="stylesheet" />
    <!--	<link href="/admin/css/styles.css" rel="stylesheet" /> -->
    <link rel="stylesheet" href="assets/css/plugins.css">
    <link rel="stylesheet" href="assets/css/themes/theme-1.css" id="skin_color" />
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
        body {
            font-family: Arial, sans-serif;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 8px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f2f2f2;
        }
        tr:hover {
            background-color: #f5f5f5;
        }
        .center-column {
            text-align: center;
        }
        .delete-btn {
            color: red;
            cursor: pointer;
        }
    </style>

</head>

<body>
    <div id="app">
        <?php include('include/sidebar.php'); ?>
        <div class="app-content">
            <?php include('include/header.php'); ?>
            <!-- end: TOP NAVBAR -->
            <div class="main-content">
                <div class="wrap-content container" id="container">
                    <!-- start: PAGE TITLE -->
                    <section id="page-title">
                        <div class="row">
                            <div class="col-sm-8">
                                <h1 class="mainTitle">Admin | Gestion des Activités</h1>
                            </div>
                            <ol class="breadcrumb">
                                <!--	<li>
                                        <span>Admin</span>
                                    </li>
                                    <li class="active">
                                        <span>Ajout Personne</span> 
                                    </li> -->
                            </ol>
                        </div>
                    </section>
                    <!-- end: PAGE TITLE -->
                    <!-- start: BASIC EXAMPLE -->
                    <div class="container-fluid container-fullw bg-white">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="row margin-top-30">
                                    <div class="col-lg-8 col-md-12">
                                        <div class="panel panel-white">
                                            <!--div class="panel-heading">
                                                    <h5 class="panel-title">Ajout Personne</h5>
                                                </div> -->
                                            <div class="panel-body">
                                                <div id="layoutSidenav_content">
                                                    <main>
                                                        <div class="container-fluid px-4">
                                                            <h1 class="mt-4">Prochainement :</h1>
                                                            <ol class="breadcrumb mb-4">
                                                                <li class="breadcrumb-item"><a
                                                                        href="dashboard.php">Dashboard</a></li>
                                                                <li class="breadcrumb-item active">Liste des Prochaines Activités
                                                                </li>
                                                            </ol>
                                                            <div class="filter-section" style="margin-bottom: 20px;">
                                                                <select id="organizerFilter" class="form-control" style="max-width: 200px;">
                                                                    <option value="">Tous les organisateurs</option>
                                                                    <?php
                                                                    // Updated query to use participation table
                                                                    $organisateurs = mysqli_query($con, "SELECT DISTINCT m.`id-membre`, m.pseudo 
                                                                        FROM participation p 
                                                                        JOIN membres m ON p.`id-membre` = m.`id-membre` 
                                                                        ORDER BY m.pseudo");
                                                                    while ($org = mysqli_fetch_array($organisateurs)) {
                                                                        $selected = ($filterMemberId == $org['id-membre']) ? 'selected' : '';
                                                                        echo "<option value='" . $org['id-membre'] . "' $selected>" . $org['pseudo'] . "</option>";
                                                                    }
                                                                    ?>
                                                                </select>
                                                            </div>
                                                            <div class="card mb-4">
                                                                <div class="card-header">
                                                                    <i class="fas fa-table me-1"></i>
                                                                    Liste des prochaines Parties 
                                                                </div>
                                                                <div class="card-body">
                                                                    <table id="datatablesSimple">
                                                                        <thead>
                                                                            <tr>
                                                                                <th class="center-column">Date & Heure</th>
                                                                                <th>Lieu</th>
                                                                                <th>Organisateur</th>
                                                                                <th>Places Max</th>
                                                                                <th>Supprimer</th>
                                                                            </tr>
                                                                        </thead>
                                                                        <!-- <tfoot>
                                                                            <tr>
                                                                                <th class="center-column">Date & Heure</th>
                                                                                <th>Lieu</th>
                                                                                <th>Organisateur</th>
                                                                                <th>Places Max</th>
                                                                                <th>Supprimer</th>
                                                                            </tr>
                                                                        </tfoot> -->
                                                                        <tbody>
                                                                            <?php 
                                                                            $da=date("Y-m-d");
                                                                            $maintenantnum=strtotime($da); 
                                                                            $maintenantnum=strtotime(date("Y-m-d")); 
                                                                            //echo "-".$maintenantnum."-";
                                                                                //$ret = "&"i_query($con, "select * from activite where date_depart <> '0000-00-00' and datediff(date_depart,now())>-1 order by date_depart");
                                                                                $ret = mysqli_query($con, "select * from activite ORDER by date_depart ASC");
                                                                                $cnt = 1;
                                                                                while ($row = mysqli_fetch_array($ret)) { ?>
                                                                            <tr>
                                                                                <?php $ladate=strtotime($row['date_depart']);
                                                                                // echo "/".$maintenantnum."/".$ladate."/"; ?>
                                                                                <?php if ($ladate>$maintenantnum) { ?>
                                                                                <td>
                                                                                    <?php echo $row['date_depart']; ?>
                                                                                </td>
                                                                                <td>
                                                                                    <a
                                                                                        href="voir-activite.php?uid=<?php echo $row['id-activite']; ?>">
                                                                                        <?php echo $row['ville']; ?>
                                                                                    </a>
                                                                                </td>
                                                                                <td class="center-column" data-organizer-id="<?php 
                                                                                    $idm = $row['id-membre'];
                                                                                    $sqlParticipant = mysqli_query($con, "SELECT `id-membre` FROM participation WHERE `id-activite` = '" . $row['id-activite'] . "' LIMIT 1");
                                                                                    $participant = mysqli_fetch_array($sqlParticipant);
                                                                                    echo $participant['id-membre']; 
                                                                                ?>">
                                                                                    <?php 
                                                                                    $idm = $row['id-membre'];
                                                                                    $sql0 = mysqli_query($con, "SELECT * FROM `membres` WHERE `id-membre` = $idm ");
                                                                                    $result0 = mysqli_fetch_array($sql0);
                                                                                    $nomm = $result0['pseudo']; ?>
                                                                                <!--    <a href="voir-membre.php?id=<?php echo $row['id-membre']; ?>"> -->
                                                                                    <?php echo $nomm;
                                                                                    // echo $row['id-membre']; ?>
                                                                                </td>
                                                                                <td class="center-column">
                                                                                    <?php echo $row['places']; ?>
                                                                                </td>
                                                                                <td class="center-column">
                                                                                    <!--    <a href="voir-activite.php?uid=<?php echo $row['id-activite']; ?>"> <i class="fas fa-edit"></i></a> -->
                                                                                    <a href="liste-activites.php?del=<?php echo "1"; ?>&uid=<?php echo $row['id-activite']; ?>"
                                                                                        onClick="return confirm('Do you really want to delete');"><i
                                                                                            class="fa fa-trash"
                                                                                            aria-hidden="true"></i></a>
                                                                                </td>
                                                                                <!--<td class="delete-btn">Delete</td>-->
                                                                                <?php }; ?>
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
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-12 col-md-12">
                                <div class="panel panel-white">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- end: BASIC EXAMPLE -->
            <!-- end: SELECT BOXES -->
        </div>
        <!-- start: FOOTER -->
        <?php include('include/footer.php'); ?>
        <!-- end: FOOTER -->
        <!-- start: SETTINGS -->
        <?php include('include/setting.php'); ?>
        <!-- end: SETTINGS -->
    </div>
    <!-- start: MAIN JAVASCRIPTS -->
    <script src="vendor/jquery/jquery.min.js"></script>
    <script src="vendor/bootstrap/js/bootstrap.min.js"></script>
    <script src="vendor/modernizr/modernizr.js"></script>
    <script src="vendor/jquery-cookie/jquery.cookie.js"></script>
    <script src="vendor/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="vendor/switchery/switchery.min.js"></script>
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
    <script src="https://cdn.jsdelivr.net/npm/simple-datatables@latest" crossorigin="anonymous"></script>
    <script src="../js/datatables-simple-demo.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const organizerFilter = document.getElementById('organizerFilter');
        const table = document.getElementById('datatablesSimple');
        
        function filterTable() {
            const selectedOrganizer = organizerFilter.value;
            const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
            
            for (let row of rows) {
                const organizerCell = row.cells[2];
                if (!organizerCell) continue;
                
                if (selectedOrganizer === '' || organizerCell.getAttribute('data-organizer-id') === selectedOrganizer) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            }
        }
        
        organizerFilter.addEventListener('change', filterTable);
        
        // Apply initial filter if URL parameter exists
        <?php if ($filterMemberId): ?>
        filterTable();
        <?php endif; ?>
    });
    </script>
</body>

</html>
<?php } ?>