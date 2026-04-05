<?php
session_start();
error_reporting(0);
include('include/config.php');
if(strlen($_SESSION['id']==0)) {
 header('location:logout.php');
  } else{

if(isset($_POST['submit']))
	{	
//    $part=$_POST['chal_part'];
	$nom=$_POST['chal_nom'];
	$com=$_POST['chal_com'];
	$deb=$_POST['chal_deb'];
    $fin=$_POST['chal_fin'];
    $org=$_POST['chal_org'];
	
	$doctorspecilization=$_POST['doctorspecilization'];
    $msg=mysqli_query($con,"insert into challenge(chal_nom,chal_com,chal_deb,chal_fin,chal_org) values('$nom','$com','$deb','$fin','$org')");    
	//$sql=mysqli_query($con,"insert into competences(nom) values('$doctorspecilization')");
	$_SESSION['msg']="Challenge added successfully !!";
	}
//Code Deletion
if(isset($_GET['del']))
	{
	$sid=$_GET['id'];	
	echo $sid;
	mysqli_query($con,"DELETE FROM `challenge-partie` WHERE `challenge-partie`.`chapar_id` = '$sid'");
//	mysqli_query($con,"delete from 'challenge-partie' where 'chapar_id' = '$sid'");
	$_SESSION['msg']="data deleted !!";
	}
?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<title>Admin | Doctor Specialization</title>	
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
	</head>
	<body>
		<div id="app">		
			<?php include('include/sidebar.php');?>
			<div class="app-content">				
				<?php include('include/header.php');?>	
				<!-- end: TOP NAVBAR -->
				<div class="main-content" >
					<div class="wrap-content container" id="container">
						<!-- start: PAGE TITLE -->
						<section id="page-title">
							<div class="row">
								<div class="col-sm-8">
									<h1 class="mainTitle">Admin | AJOUTER CHALLENGE </h1>
								</div>
								<ol class="breadcrumb">
									<li>
										<span>Admin</span>
									</li>
									<li class="active">
										<span>Ajouter CHALLENGE</span>
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
													<h5 class="panel-title">Challenge</h5>
												</div>
												<div class="panel-body">
													<p style="color:red;"><?php echo htmlentities($_SESSION['msg']);?>
													<?php echo htmlentities($_SESSION['msg']="");?></p>														
													<form role="form" name="dcotorspcl" method="post" >
																									
														<div class="card-body">
															<table class="table table-bordered">
																<tr>
																	<th>Titre</th>
																	<td><input class="form-control" id="chal_nom" name="chal_nom" type="text" value="<?php echo $result['chal_nom'];?>" required /></td>
																</tr>
														<!--		<tr>
																	<th>Partie</th>
																	<td><input class="form-control" id="chal_part" name="chal_part" type="int" value="<?php echo $result['chal_part'];?>" required /></td>
																</tr> -->
																<tr>
																	<th>Date de début</th>
																	<td><input class="form-control" id="chal_deb" name="chal_deb" type="date" value="<?php echo $result['chal_deb'];?>" ></td>
																</tr>
																<tr>
																	<th>Date de fin</th>
																	<td><input class="form-control" id="chal_fin" name="chal_fin" type="date" value="<?php echo $result['chal_fin'];?>"  required /></td>
																</tr>
																<tr>
																	<th>Organisateur</th>
																	<td><input class="form-control" id="chal_org" name="chal_org" type="int" value="<?php echo $result['chal_org'];?>"  ></td>
																</tr>
																<tr>
																	<th>Commentaire</th>
																	<td><input class="form-control" id="chal_com" name="chal_com" type="text" value="<?php echo $result['chal_com'];?>" required / ></td>
																</tr>
													<!--			<tr>
																	<th>Nb Joueurs Max</th>
																	<td><input class="form-control" id="nb_classa" name="nb_classa" type="text" value="<?php echo $result['nb_classa'];?>"  ></td>
																</tr>
																<tr>
																	<th>Buyin</th>
																	<td><input class="form-control" id="buyin" name="buyin" type="text" value="<?php echo $result['buyin'];?>"  required /></td>
																</tr>
																<tr>
																	<th>Rake</th>
																	<td><input class="form-control" id="rake" name="rake" type="text" value="<?php echo $result['rake'];?>"  ></td>
																</tr>
																<tr>
																	<th>Bounty</th>
																	<td><input class="form-control" id="bounty" name="bounty" type="text" value="<?php echo $result['bounty'];?>"  ></td>
																</tr>
																<tr>
																	<th>Nb Recave</th>
																	<td><input class="form-control" id="recave" name="recave" type="text" value="<?php echo $result['recave'];?>"  required /></td>
																</tr>
																<tr>
																	<th>Addon</th>
																	<td><input class="form-control" id="addon" name="addon" type="text" value="<?php echo $result['addon'];?>"  ></td>
																</tr>
																<tr>
																	<th>Ante</th>
																	<td><input class="form-control" id="ante" name="ante" type="text" value="<?php echo $result['ante'];?>"  ></td>
																</tr>
																<tr>
																	<th>Structure</th>
																	<td><input class="form-control" id="structure" name="structure" type="text" value="<?php echo $result['structure'];?>"  ></td>
																</tr>
																<tr>
																	<th>Stack</th>
																	<td><input class="form-control" id="jetons" name="jetons" type="text" value="<?php echo $result['jetons'];?>"  ></td>
																</tr> -->
																<tr>
																	<td colspan="4" style="text-align:center ;"><button type="submit" class="btn btn-primary btn-block" name="submit">Creation</button></td>
																</tr> 
															<!--</tbody>-->
															</table>
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
				</div>
				<!-- end: BASIC EXAMPLE -->
				<!-- end: SELECT BOXES -->		
			</div>	
			<!-- start: FOOTER -->
			<?php include('include/footer.php');?>
			<!-- end: FOOTER -->		
			<!-- start: SETTINGS -->
			<?php include('include/setting.php');?>			
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
	</body>
</html>
<?php } ?>
