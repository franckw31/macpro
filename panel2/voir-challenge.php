<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include('include/config.php');
if(strlen($_SESSION['id']) == 0) {
 header('location:logout.php');exit;
  } else{
$id=intval($_GET['id']);
if(isset($_POST['submit']))
{
	$nom=$_POST['titre_challenge'];
	$com=$_POST['chal_com'];
	$deb=$_POST['chal_deb'];
    $fin=$_POST['chal_fin'];
    $org=$_POST['chal_org'];
$sql=mysqli_query($con,"update  challenge set titre_challenge='$nom',chal_com='$com',chal_deb='$deb',chal_fin='$fin',chal_org='$org' where id_challenge='$id'");
$_SESSION['msg']="MAJ Ok !!";
} 
if(isset($_POST['submit2']))
{
	$activite_id = intval($_POST['compet']);
	$sql2 = mysqli_query($con, "UPDATE `activite` SET `id_challenge` = '$id' WHERE `id-activite` = '$activite_id'");
	
	if($sql2) {
		$_SESSION['msg'] = "Activité associée au challenge avec succès!";
	} else {
		$_SESSION['msg'] = "Erreur lors de l'association de l'activité!";
	}
}
?>
<!DOCTYPE html>
<html lang="fr">
	<head>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<title>Admin | Visualisation Challenge</title>
		
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
		<link href="https://cdn.jsdelivr.net/npm/simple-datatables@latest/dist/style.css" rel="stylesheet" />
		<link href="vendor/bootstrap-timepicker/bootstrap-timepicker.min.css" rel="stylesheet" media="screen">
		<link rel="stylesheet" href="assets/css/styles.css">
		<link rel="stylesheet" href="assets/css/plugins.css">
		<link rel="stylesheet" href="assets/css/themes/theme-1.css" id="skin_color" />
		<style>
			* {
				box-sizing: border-box;
			}
			
			body {
				font-size: 16px;
				padding-bottom: 120px; /* avoid footer overlap */
			}

			.challenge-info {
				display: grid;
				gap: 15px;
			}
			
			.info-row {
				display: flex;
				flex-direction: column;
				padding: 12px 0;
				border-bottom: 1px solid #eee;
			}
			
			.info-label {
				font-weight: 600;
				color: #333;
				margin-bottom: 5px;
				font-size: 14px;
			}
			
			.info-value {
				color: #666;
				word-break: break-word;
				font-size: 15px;
			}

			.action-buttons {
				display: flex;
				gap: 10px;
				flex-wrap: wrap;
				margin-top: 15px;
			}
			
			.action-buttons .btn {
				flex: 1;
				min-width: 120px;
				font-size: 14px;
				padding: 8px 12px;
			}

			.table-responsive-custom {
				overflow-x: auto;
				-webkit-overflow-scrolling: touch;
				width: 100%;
			}

			.panel {
				margin-bottom: 20px;
			}

			.panel-heading {
				padding: 15px;
			}

			.panel-body {
				padding: 15px;
			}

			.form-label {
				font-weight: 500;
				margin-bottom: 8px;
				font-size: 14px;
			}

			.form-control {
				font-size: 14px;
				padding: 8px 12px;
			}

			.btn-sm {
				padding: 5px 10px;
				font-size: 12px;
			}

			/* Tablet (768px and above) */
			@media (min-width: 768px) {
				body {
					font-size: 16px;
					padding-bottom: 120px;
				}

				.info-row {
					flex-direction: row;
					align-items: center;
					padding: 15px 0;
				}
				
				.info-label {
					min-width: 150px;
					margin-bottom: 0;
					font-size: 15px;
				}

				.info-value {
					font-size: 16px;
				}

				.panel-heading {
					padding: 20px;
				}

				.panel-body {
					padding: 20px;
				}

				.action-buttons {
					gap: 15px;
				}

				.action-buttons .btn {
					min-width: 140px;
					font-size: 15px;
					padding: 10px 15px;
				}

				.form-label {
					font-size: 15px;
				}

				.form-control {
					font-size: 15px;
					padding: 10px 15px;
				}
			}

			/* Mobile (max 576px) */
			@media (max-width: 576px) {
				body {
					font-size: 14px;
					padding-bottom: 160px;
				}

				.wrap-content {
					padding: 10px !important;
				}

				.action-buttons {
					flex-direction: column;
					gap: 8px;
				}
				
				.action-buttons .btn {
					width: 100%;
					min-width: auto;
					font-size: 13px;
					padding: 10px 12px;
				}

				.panel {
					margin-bottom: 15px;
				}

				.panel-heading {
					padding: 12px;
				}

				.panel-body {
					padding: 12px;
				}

				.info-row {
					padding: 10px 0;
					flex-direction: column;
				}

				.info-label {
					font-size: 13px;
					margin-bottom: 5px;
					min-width: auto;
				}

				.info-value {
					font-size: 14px;
				}

				.table {
					font-size: 12px;
					margin-bottom: 0;
				}

				.table td, .table th {
					padding: 8px 5px;
					vertical-align: middle;
				}

				.btn-sm {
					padding: 4px 8px;
					font-size: 11px;
				}

				.form-label {
					font-size: 13px;
					margin-bottom: 6px;
				}

				.form-control {
					font-size: 14px;
					padding: 8px 10px;
				}

				.mainTitle {
					font-size: 24px;
					margin-bottom: 15px;
				}

				.breadcrumb {
					font-size: 12px;
					margin-bottom: 15px;
				}

				.mb-3 {
					margin-bottom: 15px;
				}

				.mt-3 {
					margin-top: 20px;
				}

				.mt-1 {
					margin-top: 15px;
				}
			}

			/* Extra small devices (max 320px) */
			@media (max-width: 320px) {
				.wrap-content {
					padding: 5px !important;
				}

				.mainTitle {
					font-size: 20px;
				}

				.btn-sm {
					padding: 3px 6px;
					font-size: 10px;
				}

				.table {
					font-size: 11px;
				}

				.table td, .table th {
					padding: 5px 3px;
				}
			}

			/* Select personnalisé */
			.form-control-lg {
				min-height: 120px;
				padding: 12px 15px;
			}

			select.form-control-lg {
				min-height: 60px;
			}

			/* Override footer to avoid overlap on this page */
			#app > footer {
				position: static;
				margin-top: 20px;
				opacity: 1;
				margin-left: 0;
			}
			@media (min-width: 992px) {
				#app > footer {
					margin-left: 0;
				}
			}

			/* Footer on a single line */
			#app > footer .footer-inner {
				display: flex;
				align-items: center;
				justify-content: space-between;
				flex-wrap: nowrap;
				gap: 12px;
				line-height: 40px;
				min-height: 40px;
				padding: 0 16px;
				font-size: 12px;
				white-space: nowrap;
				overflow: hidden;
				text-overflow: ellipsis;
			}
		</style>
	</head>
	<body>
		<div id="app">		
<?php include('include/sidebar.php');?>
			<div class="app-content">
				<?php include('include/header.php');?>
				<div class="main-content">
					<div class="wrap-content container-fluid" id="container">
						<section id="page-title">
							<div class="row mb-3">
								<div class="col-12 col-sm-8">
									<h1 class="mainTitle">Visualisation Challenge</h1>
								</div>
								<div class="col-12">
									<ol class="breadcrumb mb-0">
										<li class="breadcrumb-item"><a href="dashboard.php">Admin</a></li>
										<li class="breadcrumb-item active">Visualisation</li>
									</ol>
								</div>
							</div>
						</section>

						<div class="container-fluid bg-white p-0">
							<div class="row">
								<div class="col-12">
									<div class="panel panel-white">
										<div class="panel-heading">
											<h3 class="panel-title">Détails du Challenge</h3> 
										</div> 
										<div class="panel-body">
											<?php if(isset($_SESSION['msg']) && !empty($_SESSION['msg'])): ?>
												<div class="alert alert-info alert-dismissible fade show" role="alert">
													<?php echo htmlspecialchars($_SESSION['msg']); 
													$_SESSION['msg']="";
													?>
													<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
												</div>
											<?php endif; ?>

											<div class="challenge-info">
												<?php  
													$id=intval($_GET['id']);
													$sql=mysqli_query($con,"SELECT * FROM `challenge` WHERE `id_challenge` = '$id'");
													while($row=mysqli_fetch_array($sql)) {
												?>	
													<div class="info-row">
														<span class="info-label">Titre:</span>
														<span class="info-value"><?php echo htmlspecialchars($row['titre_challenge']);?></span>
													</div>
													<div class="info-row">
														<span class="info-label">Commentaire:</span>
														<span class="info-value"><?php echo htmlspecialchars($row['chal_com']);?></span>
													</div>
													<div class="info-row">
														<span class="info-label">Organisateur:</span>
														<span class="info-value"><?php echo htmlspecialchars($row['chal_org']);?></span>
													</div>
													<div class="info-row">
														<span class="info-label">Date de début:</span>
														<span class="info-value"><?php echo $row['chal_deb'];?></span>
													</div>
													<div class="info-row">
														<span class="info-label">Date de fin:</span>
														<span class="info-value"><?php echo $row['chal_fin'];?></span>
													</div>
													<div class="action-buttons">
														<a href="edit-challenge.php?id=<?php echo $row['id_challenge'];?>" class="btn btn-primary btn-sm">
															<i class="fa fa-edit"></i> <span class="d-none d-sm-inline">Modifier</span>
														</a>
														<a href="gestion-challenge.php" class="btn btn-default btn-sm">
															<i class="fa fa-arrow-left"></i> <span class="d-none d-sm-inline">Retour</span>
														</a>
													</div>
												<?php
													}
												?>
											</div>
										</div>
									</div>
								</div>
							</div>

							<div class="row mt-1">
								<div class="col-12">
									<div class="panel panel-white">
										<div class="panel-heading">
											<h3 class="panel-title">Parties du Challenge</h3> 
										</div>
										<div class="panel-body">
											<div class="table-responsive-custom">
												<table id="activitiesTable" class="table table-hover table-striped">
													<thead class="table-light">
														<tr>
															<th>Nom</th>
															<th class="d-none d-md-table-cell">Ville</th>
															<th class="d-none d-md-table-cell">Date</th>
															<th class="text-center">Action</th>
														</tr>
													</thead>
													<tbody>
														<?php 
															$ret=mysqli_query($con,"SELECT * FROM `activite` WHERE `id_challenge` = '$id' ORDER BY `date_depart` DESC");
															
															if($ret && mysqli_num_rows($ret) > 0) {
																while($row=mysqli_fetch_array($ret)) {
														?>
															<tr>
																<td><?php echo htmlspecialchars($row['titre-activite'] ?? 'N/A'); ?></td>
																<td class="d-none d-md-table-cell"><?php echo htmlspecialchars($row['ville'] ?? '-'); ?></td>
																<td class="d-none d-md-table-cell"><?php echo substr($row['date_depart'] ?? '-', 0, 10); ?></td>
																<td class="text-center">
																	<a href="voir-activite.php?uid=<?php echo $row['id-activite']; ?>" class="btn btn-sm btn-primary">
																		<i class="fa fa-eye"></i>
																	</a>
																	<a href="delete-activite.php?id=<?php echo $row['id-activite']; ?>&challenge_id=<?php echo $id; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Confirmer la suppression ?');">
																		<i class="fa fa-trash"></i>
																	</a>
																</td>
															</tr>
														<?php 
														}
														} else {
														?>
															<tr>
																<td colspan="4" class="text-center text-muted py-4">Aucune activité associée à ce challenge</td>
															</tr>
														<?php } ?>
													</tbody>
													</tbody>
												</table>

											</div>
										</div>
									</div>
								</div>
							</div>

							<div class="row mt-3">
								<div class="col-12">
									<div class="panel panel-white">
										<div class="panel-heading">
											<h3 class="panel-title">Ajouter une Partie</h3> 
										</div>
										<div class="panel-body">
											<form role="form" name="adddoc" method="post">
												<div class="form-group mb-3">
													<label for="compet" class="form-label">Sélectionner une Partie</label>
													<select name="compet" id="compet" class="form-control form-control-lg" required>
														<option value="">-- Choisir une partie --</option>
														<?php 
															$ret2=mysqli_query($con,"SELECT * FROM `activite` ORDER BY `date_depart` DESC");
															while($row2=mysqli_fetch_array($ret2)) {
														?>
															<option value="<?php echo htmlspecialchars($row2['id-activite']);?>">
																<?php echo htmlspecialchars($row2['titre-activite']); ?>
															</option>
														<?php } ?>
													</select>
												</div>
												<div class="d-grid gap-2 d-sm-flex">
													<button type="submit" name="submit2" id="submit2" class="btn btn-primary">
														<i class="fa fa-plus"></i> Ajouter Partie
													</button>
													<a href="gestion-challenge.php" class="btn btn-secondary">
														<i class="fa fa-arrow-left"></i> Retour à la liste
													</a>
												</div>
											</form>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		<?php include('include/footer.php');?>
		<?php include('include/setting.php');?>
		</div>

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
		<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.0/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
		<script src="https://cdn.jsdelivr.net/npm/simple-datatables@latest" crossorigin="anonymous"></script>
		<script>
			jQuery(document).ready(function() {
				Main.init();
				FormElements.init();
			});

			// Enable search and column sorting on the activities table
			document.addEventListener('DOMContentLoaded', function() {
				var table = document.querySelector('#activitiesTable');
				if (table) {
									new simpleDatatables.DataTable(table, {
										searchable: true,
										fixedHeight: false,
										perPage: 10,
										perPageSelect: [10,25,50,100],
						columns: [
							// Enable sorting on all columns
							{ select: 0, sortable: true },
							{ select: 1, sortable: true },
							{ select: 2, sortable: true },
							{ select: 3, sortable: false }
						]
					});
				}
			});
		</script>
	</body>
</html>
<?php } ?>
