<?php
session_start();
error_reporting(0);
include('include/config.php');

if (strlen($_SESSION['id'] == 0)) {
	$_SESSION['redirect'] = 'panel/dashboard.php';
	header('location:logout.php');
} else {

	?>
	<!DOCTYPE html>
	<html lang="en">
	<head>
		<title>Admin | Dashboard</title>
		<meta charset="utf-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimum-scale=1.0, maximum-scale=1.0">
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
		
		<!-- Modern Dashboard CSS -->
		<link rel="stylesheet" href="assets/css/modern-dashboard.css">
	</head>

	<body>
		<div id="app">
			<?php
			$fiche = $_SESSION['id'];
			include('include/sidebar.php');
			?>
			<div class="app-content">
				<?php include('include/header.php'); ?>
				
				<div class="main-content">
					<div class="wrap-content container" id="container">
						<!-- Page Title -->
						<section id="page-title">
							<div class="row">
								<div class="col-sm-8">
									<h1 class="mainTitle">Tableau de Bord</h1>
									<span class="mainDescription">Bienvenue sur votre espace d'administration</span>
								</div>
								<ol class="breadcrumb">
									<li><span>Admin</span></li>
									<li class="active"><span>Dashboard</span></li>
								</ol>
							</div>
						</section>

						<!-- Stats Overview -->
						<div class="row">
							<div class="col-sm-4">
								<a href="prochaines-activites.php" class="dashboard-card card-blue">
									<div class="card-icon"><i class="fa fa-calendar-check-o"></i></div>
									<div class="card-title">Prochaines Activités</div>
									<div class="card-description">Événements à venir</div>
								</a>
							</div>
							<div class="col-sm-4">
								<a href="liste-activites.php" class="dashboard-card card-orange">
									<div class="card-icon"><i class="fa fa-list-alt"></i></div>
									<div class="card-title">Historique des Activités</div>
									<div class="card-stat">
										<?php 
										$result_act = mysqli_query($con, "SELECT * FROM activite");
										echo htmlentities(mysqli_num_rows($result_act)); 
										?>
									</div>
									<div class="card-description">Historique complet</div>
								</a>
							</div>
							<div class="col-sm-4">
								<a href="/panel/map.html" class="dashboard-card">
									<div class="card-icon"><i class="fa fa-map-marker"></i></div>
									<div class="card-title">Géolocalisation</div>
									<div class="card-description">Localisation des membres</div>
								</a>
							</div>
						</div>

						<!-- Main Navigation Sections -->
						
						<!-- Gestion -->
						<div class="row">
							<div class="col-sm-4">
								<a href="liste-membres-container.php" class="dashboard-card">
									<div class="card-icon"><i class="fa fa-user"></i></div>
									<div class="card-title">Gestion des Membres</div>
									<div class="card-stat">
										<?php 
										$result_membres = mysqli_query($con, "SELECT * FROM membres");
										echo htmlentities(mysqli_num_rows($result_membres)); 
										?>
									</div>
									<div class="card-description">Ajouter, modifier ou supprimer des membres</div>
								</a>
							</div>
							<div class="col-sm-4">
								<a href="liste-membres-challenge-itm.php" class="dashboard-card card-purple">
									<div class="card-icon"><i class="fa fa-trophy"></i></div>
									<div class="card-title">Challenges</div>
									<div class="card-description">Suivi des compétitions et classements</div>
								</a>
							</div>
							<div class="col-sm-4">
								<a href="agenda.php" class="dashboard-card">
									<div class="card-icon"><i class="fa fa-calendar"></i></div>
									<div class="card-title">Planning Mensuel</div>
									<div class="card-description">Vue calendrier des événements</div>
								</a>
							</div>
						</div>

						<!-- Jeu & Compétition -->
						<div class="row">
							<div class="col-sm-4">
								<a href="/newtimer/index.php" class="dashboard-card card-red">
									<div class="card-icon"><i class="fa fa-clock-o"></i></div>
									<div class="card-title">Horloge Rapide</div>
									<div class="card-description">Lancer le chronomètre de tournoi</div>
								</a>
							</div>
							<div class="col-sm-4">
								<a href="reglement.php" class="dashboard-card">
									<div class="card-icon"><i class="fa fa-book"></i></div>
									<div class="card-title">Règlement Texas Holdem</div>
									<div class="card-description">Consulter les règles du club</div>
								</a>
							</div>
							<div class="col-sm-4">
								<a href="quick-part.php" class="dashboard-card">
									<div class="card-icon"><i class="fa fa-search"></i></div>
									<div class="card-title">Recherche Rapide</div>
									<div class="card-description">Trouver un membre ou une info</div>
								</a>
							</div>
						</div>

					</div>
				</div>
			</div>
			
			<?php include('include/footer.php'); ?>
			<?php include('include/setting.php'); ?>
		</div>

		<!-- Scripts -->
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
			jQuery(document).ready(function () {
				Main.init();
				FormElements.init();
			});
		</script>
		<!-- end: JavaScript Event Handlers for this page -->
		<!-- end: CLIP-TWO JAVASCRIPTS -->
	</body>

	</html>
<?php } ?>
