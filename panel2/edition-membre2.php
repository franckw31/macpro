<?php
session_start();
error_reporting(0);
include('include/config.php');
if (strlen($_SESSION['id'] == 0)) {
    header('location:logout.php');
} else {
    ?>
    <!DOCTYPE html>
    <html lang="fr">

    <head>
        <title>Admin | Liste des membres</title>
        <link rel="stylesheet" href="vendor/bootstrap/css/bootstrap.min.css">
        <link rel="stylesheet" href="vendor/fontawesome/css/font-awesome.min.css">
        <link rel="stylesheet" href="vendor/themify-icons/themify-icons.min.css">
        <link rel="stylesheet" href="assets/css/styles.css">       
        <link rel="stylesheet" href="assets/css/themes/theme-1.css" id="skin_color" />
    </head>

    <head>
<link rel="stylesheet" href="css/mes-styles.css">
<link rel="stylesheet" href="css/les-styles.css">
<link href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/simple-datatables@latest/dist/style.css" rel="stylesheet" />

<script src="https://cdn.jsdelivr.net/npm/simple-datatables@latest" crossorigin="anonymous"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>






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
		<!-- <link href="https://cdn.jsdelivr.net/npm/simple-datatables@latest/dist/style.css" rel="stylesheet" /> -->
		<link href="vendor/bootstrap-timepicker/bootstrap-timepicker.min.css" rel="stylesheet" media="screen">
		<link rel="stylesheet" href="assets/css/styles.css">
		<link rel="stylesheet" href="assets/css/plugins.css">
		<link rel="stylesheet" href="assets/css/themes/theme-1.css" id="skin_color" />
		<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
		<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
		<link href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css" rel="stylesheet" />
		









<script type="text/javascript">$(document).ready(function () {
        $('#example').DataTable({ pageLength: 3, language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/fr-FR.json' } });});
</script>
<script type="text/javascript">$(document).ready(function () {
        $('#example2').DataTable({ pageLength: 3, language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/fr-FR.json' } });});
</script>
<script type="text/javascript">$(document).ready(function () {
        $('#example3').DataTable({ pageLength: 3, language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/fr-FR.json' } });
    });
</script>
<script type="text/javascript" language="javascript">
    function afficher(id) {
        var leCalque = document.getElementById(id);
        var leCalqueE = document.getElementById(id + "E");
        document.getElementById("cssE").className = "rubrique bgImg";
        document.getElementById("jsE").className = "rubrique bgImg";
        document.getElementById("ksE").className = "rubrique bgImg";
        document.getElementById("phpE").className = "rubrique bgImg";
        document.getElementById("css").className = "btnnav";
        document.getElementById("js").className = "btnnav";
        document.getElementById("ks").className = "btnnav";
        document.getElementById("php").className = "btnnav";
        leCalqueE.className += " montrer";
        leCalque.className = "btnnavA";
    }
</script>
<script type="text/javascript" language="javascript">
    afficher('css');
</script>
</head>


    <body>
        <div id="app">
            <!-- start: NAVBAR -->
            <?php include('include/sidebar.php'); ?>
            <div class="app-content">
                <?php include('include/header.php'); ?>
                <!-- end: TOP NAVBAR -->
                





                <!-- end: BASIC EXAMPLE -->
                <!-- end: SELECT BOES -->
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
        <script src="vendor/switchery/switchery.min.js"></script>
        <script src="assets/js/main.js"></script>
        <script>
            jQuery(document).ready(function () {
                Main.init();
                FormElements.init();
            });
        </script>
        <!-- end: MAIN JAVASCRIPTS -->
    </body>

    </html>
<?php } ?>