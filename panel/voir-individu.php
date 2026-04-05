<?php
session_start();
error_reporting(0);
include('include/config.php');
if (strlen($_SESSION['id'] == 0)) {
	header('location:logout.php');
	exit;
} else {
	$id = intval($_GET['id']); // get value
	if (isset($_POST['submit'])) {
		$joueurpass = $_POST['joueurpassword'];
		$joueurcontact = $_POST['joueurcontactno'];
		$joueurfnam = $_POST['joueurfname'];
		$joueurlnam = $_POST['joueurlname'];
		$joueurpreno = $_POST['joueurprenom'];
		$joueuremai = $_POST['joueuremail'];
		$sql = mysqli_query($con, "update  joueurs set password='$joueurpass',contactno='$joueurcontact',fname='$joueurfnam',lname='$joueurlnam',prenom='$joueurpreno',email='$joueuremai'where id='$id'");
		$_SESSION['msg'] = "MAJ Ok !!";
	}
	if (isset($_POST['submit2'])) {
		$compet = $_POST['compet'];
		echo $compet;
		$sql2 = mysqli_query($con, "INSERT INTO `competences-individu` (`id-indiv`, `id-comp`) VALUES ('$id', '$compet')");
		//$sql=mysqli_query($con,"insert into competences(nom) values('$doctorspecilization')");
		$_SESSION['msg'] = "Doctor Specialization added successfully !!";
	}
	?>
	<!DOCTYPE html>
	<html lang="en">

	<head>
		<title>Admin | Edit Joueur</title>

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
		<link href="https://cdn.jsdelivr.net/npm/simple-datatables@latest/dist/style.css" rel="stylesheet" />
		<link href="vendor/bootstrap-timepicker/bootstrap-timepicker.min.css" rel="stylesheet" media="screen">

		<link rel="stylesheet" href="assets/css/styles.css">
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
					success: function (data) {
						$("#email-availability-status").html(data);
						$("#loaderIcon").hide();
					},
					error: function () { }
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
				<div class="main-content">
					<div class="wrap-content container" id="container">
						<!-- start: PAGE TITLE -->
						<section id="page-title">
							<div class="row">
								<!--	<div class="col-sm-8">
									<h1 class="mainTitle">Admin | Visualisation Individu</h1>
								</div> -->
								<ol class="breadcrumb">
									<li>
										<span>Admin</span>
									</li>
									<li class="active">
										<span>Edition</span>
									</li>
								</ol>
							</div>
						</section>
						<!-- end: PAGE TITLE -->
						<!-- start: BASIC EXAMPLE -->
						<div class="container-fluid container-fullw bg-white">
							<div class="row">
								<div class="col-md-12">
									<div class="row margin-top-30">
										<div class="col-lg-6 col-md-12">
											<div class="panel panel-white">
												<div class="panel-heading">
													<?php
													$fiche = intval($_GET['id']); ?>
													<a href="voir-individu-loisirs.php?id=<?php echo $fiche; ?>">
														<h3 class="panel-title" align="right">Loisirs</h3>
													</a>
													<a href="voir-individu.php?id=<?php echo $fiche; ?>">
														<h3 class="panel-title" align="right">Compétences</h3>
													</a>
												</div>
												<div class="panel-body">
													<!--			<p style="color:red;"><?php echo htmlentities($_SESSION['msg']); ?> -->
													<?php echo htmlentities($_SESSION['msg'] = ""); ?>
													</p>
													<!--		<form role="form" name="dcotorspcl" method="post" > -->
													<div class="form-group">
														<!-- <label for="exampleInputEmail1">
															Voir Individu
															</label> -->
														<?php
														$id = intval($_GET['id']);
														$sql = mysqli_query($con, "select * from joueurs where id='$id'");
														while ($row = mysqli_fetch_array($sql)) {
															?>
															<form action="upload.php?editid=<?php echo $row['id']; ?>"
																method="post" enctype="multipart/form-data">
																<input type="file" name="fileToUpload" id="fileToUpload">
																<input type="submit"
																	value="Modifier Photo Après choix du fichier" name="submit">
															</form>
															<img src="images/<?php echo $row['photo']; ?>" width="100"
																height="100">
															<br>
															<table class="table table-bordered">
																<tr>
																	<th></th>
																	<td colspan="3"></td>
																</tr>
																<tr>
																	<th>Pseudo</th>
																	<td>
																		<?php echo $row['prenom']; ?>
																	</td>
																	<td><a href="edit-individu.php?id=<?php echo $row['id']; ?>">Modification
																			Profil </a></td>
																</tr>
																<tr>
																	<th>Prénom</th>
																	<td colspan="3">
																		<?php echo $row['fname']; ?>
																	</td>
																</tr>
																<tr>
																	<th>Nom</th>
																	<td colspan="3">
																		<?php echo $row['lname']; ?>
																	</td>
																</tr>
																<tr>
																	<th>Email</th>
																	<td colspan="3">
																		<?php echo $row['email']; ?>
																	</td>
																</tr>
																<tr>
																	<th>Mot de passe</th>
																	<td colspan="3">
																		<?php echo $row['password']; ?>
																	</td>
																</tr>
																<tr>
																	<th>Contact No.</th>
																	<td colspan="3">
																		<?php echo $row['contactno']; ?>
																	</td>
																</tr>
																<tr>
																	<th>Reg. Date</th>
																	<td colspan="3">
																		<?php echo $row['posting_date']; ?>
																	</td>
																</tr>
															</table>
															<?php
														}
														?>
													</div>
													<!--		<a href="manage-individu.php">    ------------------------- Quitter ------------------------- </a> -->
													<!--	<button type="submit" name="submit" class="btn btn-o btn-primary">
															Update
														</button> -->
													<!--	</form> -->
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
							<!-- form -->
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
																				<li class="breadcrumb-item"><a
																						href="dashboard.php">Dashboard</a>
																				</li>
																				<li class="breadcrumb-item active">
																					Competences</li>
																			</ol>
																			<div class="card mb-4">
																				<!--   <div class="card-header">
																					<i class="fas fa-table me-1"></i>
																					Registered User Details
																				</div> -->
																				<div class="card-body">
																					<table id="datatablesSimple">
																						<thead>
																							<tr>
																								<th>Identifiant</th>
																								<th>Nom </th>
																								<th>Commentaire</th>
																								<th>Supprimer</th>
																							</tr>
																						</thead>
																						<tfoot>
																							<tr>
																								<th>Identifiant</th>
																								<th>Nom </th>
																								<th>Commentaire</th>
																								<th>Supprimer</th>
																							</tr>
																						</tfoot>
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
																										<a href="add-competences.php?id=<?php echo $row['id'] ?>&del=deleteind"
																											onClick="return confirm('Are you sure you want to delete?')"
																											class="btn btn-transparent btn-xs tooltips"
																											tooltip-placement="top"
																											tooltip="Remove"><i
																												class="fa fa-times fa fa-white"></i></a>
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
														</div>
													</div>
												</div>
											</div>
										</div>
									</div>
								</div>
							</div>

						</div>
						<!-- end: BASIC EXAMPLE -->
						<form role="form" name="adddoc" method="post" onSubmit="return valid();">
							<div class="form-group">
								<label for="compet">
									Ajout Competence
								</label>
								<select name="compet" class="form-control" required="true">
									<!--		<option value="compet">Select Competence</option> -->
									<option value="compet">Select Competence</option>
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
		<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.0/dist/js/bootstrap.bundle.min.js"
			crossorigin="anonymous"></script>
		<script src="../js/scripts.js"></script>
		<script src="https://cdn.jsdelivr.net/npm/simple-datatables@latest" crossorigin="anonymous"></script>
		<script src="../js/datatables-simple-demo.js"></script>
	</body>

	</html>
<?php } ?>