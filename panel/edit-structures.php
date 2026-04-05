<?php
session_start();
error_reporting(0);
include('include/config.php');
if(strlen($_SESSION['id']==0)) {
 header('location:logout.php');
  } else{
$id=intval($_GET['id']);// get value
if(isset($_POST['submit']))
{
$nom=$_POST['nom'];
$id_structure=$_POST['id-structure'];
$id_activite=$_POST['id-activite'];
$id_blinde=$_POST['id-blinde'];
$ante=$_POST['ante'];
$duree=$_POST['duree'];
$ordre=$_POST['ordre'];
$sql=mysqli_query($con,"UPDATE `structure` SET `nom` = '$nom',`id-structure` = '$id_structure',`id-activite` = '$id_activite',`id-blinde` = '$id_blinde', `ante` = '$ante', `duree` = '$duree', `ordre` = '$ordre' WHERE `structure`.`id` = '$id';");
$_SESSION['msg']="blinde MAJ avec succés !!";
} 
?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<title>Admin | Edit Structure</title>
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
									<h1 class="mainTitle">Admin | Gestion Des structures</h1>
								</div>
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
													<h5 class="panel-title">Modification</h5>
												</div>
												<div class="panel-body">
								                    <p style="color:red;"><?php echo htmlentities($_SESSION['msg']);?>
								                    <?php echo htmlentities($_SESSION['msg']="");?></p>	
													<form role="form" name="dcotorspcl" method="post" >
														<div class="form-group">
															<label for="exampleInputEmail1">
																Modifier Nom et Durée
															</label>
                                                            <?php 
                                                            $id=intval($_GET['id']);
	                                                        $sql=mysqli_query($con,"SELECT * FROM `structure` WHERE `id` = '$id'");
                                                            while($row=mysqli_fetch_array($sql))
                                                            {														
	                                                            ?>	
		                                                        <input type="text" name="id-structure" class="form-control" value="<?php echo $row['id-structure'];?>" >
		                                                        <input type="text" name="id-activite" class="form-control" value="<?php echo $row['id-activite'];?>" >
		                                                        <input type="text" name="nom" class="form-control" value="<?php echo $row['nom'];?>" >
		                                                        <input type="text" name="id-blinde" class="form-control" value="<?php echo $row['id-blinde'];?>" >
		                                                        <input type="text" name="ordre" class="form-control" value="<?php echo $row['ordre'];?>" >
		                                                        <input type="timestamp" name="duree" class="form-control" value="<?php echo $row['duree'];?>" >
		                                                        <input type="text" name="ante" class="form-control" value="<?php echo $row['ante'];?>" >
	                                                        <?php } ?>
														</div>
                                                    	<button type="submit" name="submit" class="btn btn-o btn-primary">
															Update
														</button>
													</form>
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
		</div>
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
