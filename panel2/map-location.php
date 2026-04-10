<?php
session_start();
include('include/config.php');

// Récupérer la localisation du membre connecté
$user_id = $_SESSION['id'];
$q = mysqli_query($con, "SELECT latitude, longitude FROM membres WHERE `id-membre` = '$user_id'");
$r = mysqli_fetch_array($q);

$lat = ($r && $r['latitude']) ? $r['latitude'] : 43.608325;
$lng = ($r && $r['longitude']) ? $r['longitude'] :  1.479574;

// Possibilité de passer des coordonnées en paramètre (ex: pour l'activité)
if (isset($_GET['lat']) && isset($_GET['lng'])) {
    $lat = floatval($_GET['lat']);
    $lng = floatval($_GET['lng']);
    $zoom = 14;
} else {
    $zoom = 9.6;
}

$is_mini = isset($_GET['mini']);

// Récupérer les détails de l'activité si l'ID est fourni
$activity_info = "";
if (isset($_GET['id_act'])) {
    $id_act = intval($_GET['id_act']);
    $q_act = mysqli_query($con, "SELECT a.*, m.pseudo as organisateur 
                                 FROM activite a 
                                 LEFT JOIN membres m ON a.`id-membre` = m.`id-membre` 
                                 WHERE a.`id-activite` = '$id_act'");
    $r_act = mysqli_fetch_array($q_act);
    if ($r_act) {
        $activity_info = "Organisé par <strong>" . htmlentities($r_act['organisateur']) . "</strong> - " . htmlentities($r_act['rue']) . ", " . htmlentities($r_act['ville']);
    }
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php if (!$is_mini): ?>
        <title>Localisation | Dashboard</title>
        <link href="http://fonts.googleapis.com/css?family=Lato:300,400,400italic,600,700|Raleway:300,400,500,600,700|Crete+Round:400italic" rel="stylesheet" type="text/css" />
        <link rel="stylesheet" href="vendor/bootstrap/css/bootstrap.min.css">
        <link rel="stylesheet" href="vendor/fontawesome/css/font-awesome.min.css">
        <link rel="stylesheet" href="vendor/themify-icons/themify-icons.min.css">
        <link href="vendor/animate.css/animate.min.css" rel="stylesheet" media="screen">
        <link href="vendor/perfect-scrollbar/perfect-scrollbar.min.css" rel="stylesheet" media="screen">
        <link href="vendor/switchery/switchery.min.css" rel="stylesheet" media="screen">
        <link rel="stylesheet" href="assets/css/styles.css">
        <link rel="stylesheet" href="assets/css/plugins.css">
        <link rel="stylesheet" href="assets/css/themes/theme-1.css" id="skin_color" />
        <link rel="stylesheet" href="assets/css/modern-dashboard.css">
    <?php endif; ?>
    
    <link href="https://api.tiles.mapbox.com/mapbox-gl-js/v2.14.1/mapbox-gl.css" rel="stylesheet" />
    <style>
        <?php if ($is_mini): ?>
            body { margin: 0; padding: 0; }
            #map { position: absolute; top: 0; bottom: 0; width: 100%; height: 100%; }
        <?php else: ?>
            #map { 
                width: 100%; 
                height: calc(100vh - 150px); 
                border-radius: 10px;
                box-shadow: 0 0 20px rgba(0,0,0,0.1);
            }
            .main-content { background: #f7f7f8; }
        <?php endif; ?>
    </style>
</head>

<body>
    <?php if ($is_mini): ?>
        <div id="map"></div>
    <?php else: ?>
        <div id="app" class="app-navbar-fixed app-sidebar-fixed app-footer-fixed">
            <?php include('include/sidebar.php'); ?>
            <div class="app-content">
                <?php include('include/header.php'); ?>
                <div class="main-content">
                    <div class="wrap-content container" id="container">
                        <section id="page-title">
                            <div class="row">
                                <div class="col-sm-8">
                                    <h1 class="mainTitle">Localisation de l'activité</h1>
                                    <span class="mainDescription"><?php echo $activity_info ? $activity_info : "Visualisation sur la carte"; ?></span>
                                </div>
                            </div>
                        </section>
                        <div class="container-fluid container-fullw bg-white">
                            <div class="row">
                                <div class="col-md-12">
                                    <div id="map"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php include('include/footer.php'); ?>
            <?php include('include/setting.php'); ?>
        </div>
    <?php endif; ?>

    <script src="https://api.tiles.mapbox.com/mapbox-gl-js/v2.14.1/mapbox-gl.js"></script>
    <script src="vendor/jquery/jquery.min.js"></script>
    
    <?php if (!$is_mini): ?>
        <script src="vendor/bootstrap/js/bootstrap.min.js"></script>
        <script src="vendor/modernizr/modernizr.js"></script>
        <script src="vendor/jquery-cookie/jquery.cookie.js"></script>
        <script src="vendor/perfect-scrollbar/perfect-scrollbar.min.js"></script>
        <script src="vendor/switchery/switchery.min.js"></script>
        <script src="assets/js/main.js"></script>
    <?php endif; ?>

    <script>
        mapboxgl.accessToken = 'pk.eyJ1IjoiZnJhbmNrdzMxIiwiYSI6ImNsbmJqemU5cjA0MDYya3RkczNrMHdqb2wifQ.6NLEMz-lShL80j9QuGW9cA';
        
        var map = new mapboxgl.Map({
            container: 'map',
            style: 'mapbox://styles/franckw31/clnd1m23b03o501qu3x5ab4xk',
            center: [<?php echo $lat; ?>, <?php echo $lng; ?>],
            zoom: <?php echo $zoom; ?>,
            attributionControl: false
        });

        <?php if (isset($_GET['lat']) && isset($_GET['lng'])): ?>
        new mapboxgl.Marker({ color: '#FF0000' })
            .setLngLat([<?php echo $lat; ?>, <?php echo $lng; ?>])
            .addTo(map);
        <?php endif; ?>

        /* map.addControl(new mapboxgl.NavigationControl(), 'bottom-right'); */
        
        <?php if (!$is_mini): ?>
            jQuery(document).ready(function() {
                Main.init();
                // Forcer le footer fixe si demandé
                $('#app').addClass('app-footer-fixed');
            });
        <?php endif; ?>
    </script>
</body>
</html>