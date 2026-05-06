<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include('include/config.php');

if (strlen($_SESSION['login']) == 0) {
	$_SESSION['redirect'] = 'panel/dashboard.php';
	header('location:logout.php');
} else {
	// Si l'ID n'est pas en session, on le récupère via le login
	if (!isset($_SESSION['id']) || $_SESSION['id'] == 0) {
		$login = $_SESSION['login'];
		$q_u = mysqli_query($con, "SELECT `id-membre` FROM membres WHERE pseudo = '$login'");
		$r_u = mysqli_fetch_array($q_u);
		$_SESSION['id'] = $r_u['id-membre'];
	}

	$allowed_dashboard_ids = array(2222, 265);
	if (!isset($_SESSION['id']) || !in_array(intval($_SESSION['id']), $allowed_dashboard_ids, true)) {
		header('Location: /panel/quickview.php');
		exit();
	}
	
	// Vérifier si l'utilisateur est admin et récupérer sa photo
	$is_admin = false;
	$user_photo = null;
	$user_pseudo = $_SESSION['login'];
	if (isset($_SESSION['id']) && $_SESSION['id'] > 0) {
		$q_admin = mysqli_query($con, "SELECT `droits`, `photo`, `pseudo` FROM `membres` WHERE `id-membre` = " . intval($_SESSION['id']));
		if ($q_admin && mysqli_num_rows($q_admin) > 0) {
			$r_admin = mysqli_fetch_array($q_admin);
			if ($r_admin && isset($r_admin['droits']) && intval($r_admin['droits']) === 2) {
				$is_admin = true;
			}
			$user_photo = isset($r_admin['photo']) ? $r_admin['photo'] : null;
			$user_pseudo = isset($r_admin['pseudo']) ? $r_admin['pseudo'] : $_SESSION['login'];
		}
	}

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
		<link rel="stylesheet" href="assets/css/styles.css?v=<?php echo time(); ?>">
		<link rel="stylesheet" href="assets/css/plugins.css?v=<?php echo time(); ?>">
		<link rel="stylesheet" href="assets/css/themes/theme-1.css?v=<?php echo time(); ?>" id="skin_color" />
		
		<!-- Modern Dashboard CSS -->
		<link rel="stylesheet" href="assets/css/modern-dashboard.css?v=<?php echo time(); ?>">
		<link rel="stylesheet" href="assets/css/card-bg.css?v=<?php echo time(); ?>">
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
								<div class="col-sm-12 text-center">
									<!-- <h1 class="mainTitle">Tableau de Bord</h1> -->
                                     <span class="mainDescription">.</span>

									<div style="display: flex; align-items: center; justify-content: center; gap: 20px;">
										<a href="voir-membre.php?id=<?php echo $_SESSION['id']; ?>" style="text-decoration: none;">
											<?php if ($user_photo && !empty($user_photo)) { ?>
												<img src="/images/faces/<?php echo htmlspecialchars($user_photo, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($_SESSION['login']); ?>" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 3px solid #00d2ff;">
											<?php } else { ?>
												<div style="width: 80px; height: 80px; border-radius: 50%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; color: white; font-size: 32px; font-weight: bold; border: 3px solid #00d2ff;">
													<?php echo strtoupper(substr($_SESSION['login'], 0, 1)); ?>
												</div>
											<?php } ?>
										</a>
										<h2 class="mainTitle" style="color:white; margin: 0; font-size: 3.5em;" ><?php echo $is_admin ? '<a href="passeport.php" style="text-decoration: none; color: white;">Bienvenue Admin</a>' : ('Bienvenue ' . htmlspecialchars($user_pseudo)); ?></h2>
									</div>
									<!--  Paragraphe Raccourcis 
									<div style="margin: 30px auto 0 auto; max-width: 700px; background: rgba(255,255,255,0.95); border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); padding: 24px; text-align: left;">
										<h3 style="color: #333; font-size: 1.6em; margin-bottom: 10px;">Raccourcis</h3>
										<ul style="list-style: none; padding: 0; margin: 0;">
											<li style="margin-bottom: 8px;"><a href="/panel/prochaines-activites.php" style="color: #007bff; text-decoration: none; font-weight: 500;"><i class="fa fa-rocket"></i> Prochaines activités</a></li>
											<li style="margin-bottom: 8px;"><a href="/panel/liste-activites.php" style="color: #007bff; text-decoration: none; font-weight: 500;"><i class="fa fa-list-alt"></i> Historique des activités</a></li>
											<li style="margin-bottom: 8px;"><a href="/panel/liste-membres-container.php" style="color: #007bff; text-decoration: none; font-weight: 500;"><i class="fa fa-user"></i> Liste des membres</a></li>
											<li style="margin-bottom: 8px;"><a href="/panel/map.html" style="color: #007bff; text-decoration: none; font-weight: 500;"><i class="fa fa-map-marker"></i> Carte des membres</a></li>
											<li style="margin-bottom: 8px;"><a href="/panel/agenda.php" style="color: #007bff; text-decoration: none; font-weight: 500;"><i class="fa fa-calendar"></i> Planning mensuel</a></li>
											<li style="margin-bottom: 8px;"><a href="/panel/chat.php" style="color: #007bff; text-decoration: none; font-weight: 500;"><i class="fa fa-whatsapp"></i> Chat joueurs</a></li>
										</ul>
									</div> -->
								</div>
							</div>
						</section>

						<!-- Stats Overview -->
						<div class="row">
							<div class="col-sm-4">
								<a href="prochaines-activites.php" class="dashboard-card card-blue">
									<div class="card-icon"><i class="fa fa-rocket" style="background: linear-gradient(45deg, #FF512F 0%, #DD2476 50%, #FF512F 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; display: inline-block;"></i></div>
									<div class="card-stat">
										<?php 
										$result_next = mysqli_query($con, "SELECT COUNT(*) as total FROM activite WHERE date_depart >= CURDATE()");
										$row_next = mysqli_fetch_array($result_next);
										echo htmlentities($row_next['total']); 
										?>
									</div>
									<div class="card-description">Événements à venir</div>
								</a>
							</div>
							<div class="col-sm-4">
								<a href="liste-activites.php" class="dashboard-card card-orange">
									<div class="card-icon"><i class="fa fa-list-alt"></i></div>
									<div class="card-title">Historique des Activités</div>
									<div class="card-stat">
										<?php 
										$result_act = mysqli_query($con, "SELECT COUNT(*) as total FROM activite");
										$row_act_count = mysqli_fetch_array($result_act);
										echo htmlentities($row_act_count['total']); 
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
							<!-- <div class="col-sm-4">
								<a href="chat.php" class="dashboard-card card-green" style="background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);">
									<div class="card-icon"><i class="fa fa-whatsapp"></i></div>
									<div class="card-title">Chat Joueurs</div>
									<div class="card-description">Discutez avec les autres membres</div>
								</a>
							</div> -->
						</div>

						<!-- Main Navigation Sections -->
						
						<!-- Gestion -->
						<div class="row">
							<?php if ($is_admin) { ?>
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
							<?php } ?>
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
							<?php if ($is_admin) { ?>
							<div class="col-sm-4">
								<a href="quick-part.php" class="dashboard-card">
									<div class="card-icon"><i class="fa fa-search"></i></div>
									<div class="card-title">Recherche Rapide</div>
									<div class="card-description">Trouver un membre ou une info</div>
								</a>
							</div>
							<?php } ?>
						</div>

						<!-- QR Codes -->
						<div class="row">
							<?php if ($is_admin) { ?>
							<div class="col-sm-4">
								<a href="/panel/qrcodes.php" class="dashboard-card card-green">
									<div class="card-icon"><i class="fa fa-qrcode"></i></div>
									<div class="card-title">Gestion QRcodes</div>
									<div class="card-description">Créer, imprimer et vérifier les codes</div>
								</a>
							</div>
							<?php } ?>
							<div class="col-sm-4">
								<?php 
								$result_current_act = mysqli_query($con, "SELECT `id-activite` FROM activite WHERE date_depart <= NOW()  ORDER BY date_depart DESC LIMIT 1");
								$row_current_act = mysqli_fetch_array($result_current_act);
								$current_act_id = isset($row_current_act['id-activite']) ? $row_current_act['id-activite'] : 0;
								?>
								<a href="fullscreen-cardevent.php?uid=<?php echo $current_act_id; ?>" class="dashboard-card card-blue">
									<div class="card-icon"><i class="fa fa-hourglass-start"></i></div>
									<div class="card-title">Activité en Cours</div>
									<div class="card-description">Timer en plein écran</div>
								</a>
							</div>
							<?php if ($is_admin) { ?>
							<div class="col-sm-4">
								<a href="fullscreen-player-simple.php?uid=<?php echo $current_act_id; ?>" class="dashboard-card card-red">
									<div class="card-icon"><i class="fa fa-user-times"></i></div>
									<div class="card-title">Éliminations Rapides</div>
									<div class="card-description">Gérer les éliminations</div>
								</a>
							</div>
							<?php } ?>
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
		<script src="assets/js/card-bg.js"></script>
		<!-- start: JavaScript Event Handlers for this page -->
		<script src="assets/js/form-elements.js"></script>
		<script>
			jQuery(document).ready(function () {
				Main.init();
				FormElements.init();

				// Initialize reusable card background
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
		<!-- end: JavaScript Event Handlers for this page -->
		<!-- end: CLIP-TWO JAVASCRIPTS -->
	</body>

	</html>
<?php } ?>
