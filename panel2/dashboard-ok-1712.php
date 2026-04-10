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
		<title>AdmiN | Dashboard</title>
		<linkh href="http://fonts.googleapis.com/css?family=Lato:300,400,400italic,600,700|Raleway:300,400,500,600,700|Crete+Round:400italic" rel="stylesheet" type="text/css" />
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

	</head>

	<body>
		<div id="app">
			<?php
			$fiche = $_SESSION['id'];
			// echo "/".$fiche."/";
			$result = mysqli_query($con, "SELECT * FROM `membres`  WHERE 'id-membre' = $fiche");
			$num = mysqli_fetch_assoc($result);
			$droits = $num[`droits`];
			// $droits = "2";
			// echo '-----'.$fiche.'------'.$droits.")";
			// if ($droits = '1') {
			// 	include('include/sidebaruser.php');
			// } else {
			// 	if ($droits = '2')
			// 		include('include/sidebar.php');
			// }
			include('include/sidebar.php');
			?>
			<div class="app-content">
				<?php include('include/header.php'); ?>
				<!-- end: TOP NAVBAR -->
				<div class="main-content">
					<div class="wrap-content container" id="container">
						<!-- start: PAGE TITLE -->
						<section id="page-title">
							<div class="row">
								<div class="col-sm-8">
									<h1 class="mainTitle">.</h1>
									<?php
									$result = mysqli_query($con, "SELECT * FROM `membres` WHERE 'id-membre'=$fiche");
									$num = mysqli_fetch_assoc($result);
									$droits = $num['droits'];
										// echo '-----'.$fiche.'------';
										// echo $num['prenom'].' ('.$_SESSION['id'].')'.'('.$droits.')'; ?>
								</div>
								<ol class="breadcrumb">
									<li>
										<span>Admin.</span>
									</li>
									<li class="active">
										<span>DashboarD</span>
									</li>
								</ol>
							</div>
						</section>
						<!-- end: PAGE TITLE -->
						<!-- start: BASIC EXAMPLE bg-white-->
						<div class="ccontainer-fluid ccontainer-fullw ">
							<div class="rrow">

								<div class="col-sm-4"><a href="     prochaines-activites.php     ">
									<div class="ppanel panel-white-fond1 no-radius text-center">
										<div class="panel-body">
											<span class="fa-stack fa-4x"> 
											<!--	<i class="faa faa-square faa-stack-2x ttext-primary"></i> -->
											<!--	<i class="faa faa-users faa-stack-1x faa-inverse"></i> --> </span>
											<!-- <h2 class="StepTitle">Prochaines Activités</h2> where date_depart <> '0000-00-00' and datediff(date_depart,now())>-1 order-->
											<p class="cll-effect-1">
												<a href="prochaines-activites.php">
												
													<?php $result1 = mysqli_query($con, "SELECT * FROM activite");
													
													$num_rows1 = mysqli_num_rows($result1); {
														?>
														Consulter Prochaines Activités :
														<?php echo htmlentities($num_rows1);
													} ?>
												</a>
											</p>
										</div>
									</div>
								</div>

								<div class="col-sm-4"><a href="liste-membres-container.php">
									<div class="panel panel-white-membres no-radius text-center">
										<div class="panel-body">
											<span class="fa-stack fa-4x">
												<i class="faa faa-square faa-stack-2x ttext-primary"></i> 
												<i class="faa faa-users faa-stack-1x faa-inverse"></i> </span>
											<!-- <h2 class="StepTitle">Gestion des Activités</h2> -->
											<p class="cl-effect-1">
												<a href="liste-membres-container.php">
													<?php $result1 = mysqli_query($con, "SELECT * FROM membres ");
													$num_rows1 = mysqli_num_rows($result1); {
														?>
														Nos membres :
														<?php echo htmlentities($num_rows1);
													} ?>
												</a>
											</p>
										</div>
									</div>
								</div>
								
								<div class="col-sm-4"><a href="liste-activites.php">
									<div class="panel panel-white-activites no-radius text-center">
										<div class="panel-body">
											<span class="fa-stack fa-4x"> 
												<i class="faa faa-square faa-stack-2x ttext-primary"></i> 
												<i class="faa faa-smile-o faa-stack-1x faa-inverse"></i> </span>
											<!-- <h2 class="StepTitle">Gestion des Membres</h2> -->

											<p class="cl-effect-1">
												<a href="liste-activites.php">
													<?php $result = mysqli_query($con, "SELECT * FROM activite ");
													$num_rows = mysqli_num_rows($result); {
														?>
														Activités :
														<?php echo htmlentities($num_rows);
													} ?>
												</a>
											</p>
										</div>
									</div>
								</div>

								<div class="col-sm-4"><a href="prochaines-activites.php">
									<div class="panel panel-white no-radius text-center">
										<!-- <div class="panel-body"> -->
											<span class="ffa-stack fa-2x"> 
												<!-- <i	class="fa fa-square fa-stack-2x text-primary"></i> -->
												<!-- <i	class="fa fa-terminal fa-stack-1x fa-inverse"></i> -->
											</span>
											<h2 class="StepTitle">Prochainement</h2>

									</div></a>
								</div>

								<div class="col-sm-4"><a href="liste-membres-container.php">
									<div class="panel panel-white no-radius text-center">
										<!-- <div class="panel-body"> -->
											<span class="ffa-stack fa-2x"> 
												<!-- <i	class="fa fa-square fa-stack-2x text-primary"></i> -->
												<!-- <i	class="fa fa-terminal fa-stack-1x fa-inverse"></i> -->
											</span>
											<h2 class="StepTitle">Membres</h2>

									</div></a>
								</div>

								<div class="col-sm-4"><a href="liste-activites.php">
									<div class="panel panel-white no-radius text-center">
										<!-- <div class="panel-body"> -->
											<span class="ffa-stack fa-2x"> 
												<!-- <i	class="fa fa-square fa-stack-2x text-primary"></i> -->
												<!-- <i	class="fa fa-terminal fa-stack-1x fa-inverse"></i> -->
											</span>
											<h2 class="StepTitle">Historique</h2>

									</div></a>
								</div>
								
								

								<div class="col-sm-4"><a href="liste-membres-challenge-itm.php">
									<div class="ppanel panel-white-chal no-radius text-center">
										<div class="panel-body">
											<span class="fa-stack fa-4x"> 
												<i class="faa faa-square faa-stack-2x ttext-primary"></i>
												<i class="faa faa-users faa-stack-1x faa-inverse"></i> </span>
											<!-- <h2 class="StepTitle">Prochaines Activités</h2> -->
											<p class="cl-effect-1">
												<a href="liste-membres-challenge-itm.php">
													<?php $result1 = mysqli_query($con, "SELECT * FROM activite ");
													$num_rows1 = mysqli_num_rows($result1); {
														?>
														Prochaines challenges :
														<?php echo htmlentities($num_rows1);
													} ?>
												</a>
											</p>
										</div>
									</div>
								</div>
								<div class="col-sm-4"><a href="quick-part.php">
									<div class="panel panel-white-dico no-radius text-center">
										<div class="panel-body">
											<span class="fa-stack fa-4x"> 
												<i class="tti-files ffa-1x ttext-primary"></i>
												<i class="faa faa-terminal faa-stack-1x faa-inverse"></i> </span>
											<!-- <h2 class="StepTitle">Recherche Textuelle</h2> -->

											<p class="links cl-effect-1">
												<a href="book-appointment.php">
													<a href="quick-part.php">
														<?php
														$sql = mysqli_query($con, "SELECT * FROM tblcontactus where  IsRead is null");
														$num_rows22 = mysqli_num_rows($sql);
														?>
														Rechercher </a>
													</a>
											</p>
										</div>
									</div>
								</div>

								<div class="col-sm-4"><a href="/panel/map.html">
									<div class="panel panel-white-france no-radius text-center">
									<div class="panel-body">
											<span class="fa-stack fa-4x"> <i
													class="faa faa-square faa-stack-4x ttext-primary"></i> <i
													class="faa faa-users faa-stack-1x faa-inverse"></i> </span> 
													<!-- <span class="fa-stack fa-2x"> <i class="ti-files fa-1x text-primary"></i> <i
													class="fa fa-terminal fa-stack-1x fa-inverse"></i> </span> -->
											<!-- <h2 class="StepTitle">Recherche Cartographique</h2> -->
											<p class="cl-effect-1">
												<a href="/panel/map.html">
													<?php $result1 = mysqli_query($con, "SELECT * FROM activite ");
													$num_rows1 = mysqli_num_rows($result1); {
														?>
														Carte</a>
														<?php echo htmlentities($num_rows1);
													} ?>
												</a>
											</p>
										</div>
									</div>
								</div>
								<div class="col-sm-4"><a href="liste-membres-challenge-itm.php">
									<div class="panel panel-white no-radius text-center">
										<!-- <div class="panel-body"> -->
											<span class="ffa-stack fa-2x"> 
												<!-- <i	class="fa fa-square fa-stack-2x text-primary"></i> -->
												<!-- <i	class="fa fa-terminal fa-stack-1x fa-inverse"></i> -->
											</span>
											<h2 class="StepTitle">Challenges</h2>

									</div></a>
								</div>

								<div class="col-sm-4"><a href="quick-part.php">
									<div class="panel panel-white no-radius text-center">
										<!-- <div class="panel-body"> -->
											<span class="ffa-stack fa-2x"> 
												<!-- <i	class="fa fa-square fa-stack-2x text-primary"></i> -->
												<!-- <i	class="fa fa-terminal fa-stack-1x fa-inverse"></i> -->
											</span>
											<h2 class="StepTitle">Participation</h2>

									</div></a>
								</div>

								<div class="col-sm-4"><a href="/panel/map.html">
									<div class="panel panel-white no-radius text-center">
										<!-- <div class="panel-body"> -->
											<span class="ffa-stack fa-2x"> 
												<!-- <i	class="fa fa-square fa-stack-2x text-primary"></i> -->
												<!-- <i	class="fa fa-terminal fa-stack-1x fa-inverse"></i> -->
											</span>
											<h2 class="StepTitle">Localisation</h2>

									</div></a>
								</div>
							</div>
						</div>

						<div class="col-sm-4"><a href="reglement.php">
									<div class="ppanel panel-white-regles no-radius text-center">
										<div class="panel-body">
											<span class="fa-stack fa-4x"> 
												<i class="faa faa-square faa-stack-2x ttext-primary"></i>
												<i class="faa faa-users faa-stack-1x faa-inverse"></i> </span>
											<!-- <h2 class="StepTitle">Prochaines Activités</h2> -->
											<!-- <p class="cl-effect-1">
												<a href="gestion-challenges.php">
													<?php $result1 = mysqli_query($con, "SELECT * FROM activite");
													$num_rows1 = mysqli_num_rows($result1); {
														?>
														Regles :
														<?php echo htmlentities($num_rows1);
													} ?>
												</a>
											</p> -->
										</div>
									</div>
								</div>
								<div class="col-sm-4"><a href="/newtimer/index.php">
									<div class="panel panel-white-blindest no-radius text-center">
										<div class="panel-body">
											<span class="fa-stack fa-4x"> 
												<i class="tti-files ffa-1x ttext-primary"></i>
												<i class="faa faa-terminal faa-stack-1x faa-inverse"></i> </span>
											<!-- <h2 class="StepTitle">Recherche Textuelle</h2> -->

											<!-- <p class="links cl-effect-1">
												<a href="book-appointment.php">
													<a href="recherche-loisir.php">
														<?php
														$sql = mysqli_query($con, "SELECT * FROM tblcontactus where  IsRead is null");
														$num_rows22 = mysqli_num_rows($sql);
														?>
														Timer Blindes </a>
													</a>
											</p> -->
										</div>
									</div>
								</div>

								<div class="col-sm-4"><a href="agenda.php">
									<div class="panel panel-white-texto no-radius text-center">
									<div class="panel-body">
											<span class="fa-stack fa-4x"> <i
													class="faa faa-square faa-stack-4x ttext-primary"></i> <i
													class="faa faa-users faa-stack-1x faa-inverse"></i> </span> 
													<!-- <span class="fa-stack fa-2x"> <i class="ti-files fa-1x text-primary"></i> <i
													class="fa fa-terminal fa-stack-1x fa-inverse"></i> </span> -->
											<!-- <h2 class="StepTitle">Recherche Cartographique</h2> -->
											<!-- <p class="cl-effect-1">
												<a href="/index.html">
													<?php $result1 = mysqli_query($con, "SELECT * FROM activite ");
													$num_rows1 = mysqli_num_rows($result1); {
														?>
														Time 30S</a>
														<?php echo htmlentities($num_rows1);
													} ?>
												</a>
											</p> -->
										</div>
									</div>
								</div>

								<div class="col-sm-4"><a href="reglement.php">
									<div class="panel panel-white no-radius text-center">
										<!-- <div class="panel-body"> -->
											<span class="ffa-stack fa-2x"> 
												<!-- <i	class="fa fa-square fa-stack-2x text-primary"></i> -->
												<!-- <i	class="fa fa-terminal fa-stack-1x fa-inverse"></i> -->
											</span>
											<h2 class="StepTitle text-center">Règles</h2>

									</div></a>
								</div>

								<div class="col-sm-4"><a href="/newtimer/index.php">
									<div class="panel panel-white no-radius text-center">
										<!-- <div class="panel-body"> -->
											<span class="ffa-stack fa-2x"> 
												<!-- <i	class="fa fa-square fa-stack-2x text-primary"></i> -->
												<!-- <i	class="fa fa-terminal fa-stack-1x fa-inverse"></i> -->
											</span>
											<h2 class="StepTitle">Timer</h2>

									</div></a>
								</div>

								<div class="col-sm-4"><a href="agenda.php">
									<div class="panel panel-white no-radius text-center">
										<!-- <div class="panel-body"> -->
											<span class="ffa-stack fa-2x"> 
												<!-- <i	class="fa fa-square fa-stack-2x text-primary"></i> -->
												<!-- <i	class="fa fa-terminal fa-stack-1x fa-inverse"></i> -->
											</span>
											<h2 class="StepTitle">Planning</h2>

									</div></a>
								</div>
							</div>
						</div>

						<!-- end: SELECT BOXES -->

					</div>
				</div>
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
