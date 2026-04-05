<?php
session_start();
error_reporting(0);
include('include/config.php');
if(strlen($_SESSION['id']==0)) {
 header('location:logout.php');exit;
  } else{
$id=intval($_GET['id']);// get value
//$comp=$_GET['comp']);// get value
if(isset($_POST['submit']))
{
$joueurpass=$_POST['joueurpassword'];
$joueurcontact=$_POST['joueurcontactno'];
$joueurfnam=$_POST['joueurfname'];
$joueurlnam=$_POST['joueurlname'];
$joueurpreno=$_POST['joueurprenom'];
$joueuremai=$_POST['joueuremail'];
$sql=mysqli_query($con,"update  joueurs set password='$joueurpass',contactno='$joueurcontact',fname='$joueurfnam',lname='$joueurlnam',prenom='$joueurpreno',email='$joueuremai'where id='$id'");
$_SESSION['msg']="MAJ Ok !!";
} 
if(isset($_POST['submit2']))
{
$compet=$_POST['compet'];
echo $compet;
$sql2=mysqli_query($con,"INSERT INTO `competences-individu` (`id-indiv`, `id-comp`) VALUES ('$id', '$compet')");
//$sql=mysqli_query($con,"insert into competences(nom) values('$doctorspecilization')");
$_SESSION['msg']="Doctor Specialization added successfully !!";
}
?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<title>Admin | Edit Joueur</title>
		
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
		<script type="text/javascript">
function valid()
{
 if(document.adddoc.npass.value!= document.adddoc.cfpass.value)
{
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
data:'emailid='+$("#docemail").val(),
type: "POST",
success:function(data){
$("#email-availability-status").html(data);
$("#loaderIcon").hide();
},
error:function (){}
});
}
</script>
	</head>
	<body>
		<div id="app">		
<?php include('include/sidebar.php');?>
			<div class="app-content">
				
						<?php include('include/header.php');
						
						$sql0=mysqli_query($con,"select * from competences where id='$id'");
						
						
						while($row=mysqli_fetch_array($sql0))
                            {   
								$competrech=$row['nom'];
							} 
						?>
					
				<!-- end: TOP NAVBAR -->
				<div class="main-content" >
					<div class="wrap-content container" id="container">
						<!-- start: PAGE TITLE -->
						<section id="page-title">
							<div class="row">
								<div class="col-sm-8">
									<h1 class="mainTitle">Admin | Recherche par Compétence</h1>
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
													   <h3 class="panel-title">Recherche par Compétence</h3> 
												</div> 
												<div class="panel-body">
								<p style="color:red;"><?php echo htmlentities($_SESSION['msg']);?>
								<?php echo htmlentities($_SESSION['msg']="");?></p>	
											
														<div class="form-group">
															

								<?php  

								$id=intval($_GET['id']);
								$sql=mysqli_query($con,"select * from joueurs where id='$id'");
								echo $competrech;
								
								?>
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
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Competences</li>
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
                                  <!-- <th>Identifiant</th> -->
                                  <th>Nom </th>
                                  <th>Date de création</th>
								  <th>Voir</th>
                                      </tr>
                                    </thead>
                                    <tfoot>
                                      <tr>
                                  <!-- <th>Identifiant</th> -->
                                  <th>Nom </th>
                                  <th>Date de création</th>
								  <th>Voir</th>
                                        </tr>
                                    </tfoot>
                                    <tbody>
                                              <?php $ret=mysqli_query($con,"SELECT * FROM `competences-individu` WHERE `id-comp` = '$id'");
                              $cnt=1;
                              while($row=mysqli_fetch_array($ret))
                              {?>
						  
						          <?php
												  $id2=$row['id-indiv'];
													$sql2=mysqli_query($con,"SELECT * FROM `joueurs` WHERE `id` = '$id2'");
													while($row2=mysqli_fetch_array($sql2))
                                                       { ?>
												   <tr>
												   <!--    <td><?php echo $row2['pseudo'];?></td> -->
												       <td><?php echo $row2['prenom'];?></td>
													   <td><?php echo $row2['posting_date'];?></td>
													<!--   <td><?php echo $comp;?></td> -->
													  <?php } ?>
						  
						  
                               
								  <td>
                                     
                                     <a href="voir-individu.php?id=<?php echo $id2;?>" class="btn btn-transparent btn-xs" tooltip-placement="top" tooltip="Edit"><i class="fa fa-pencil"></i></a><i class="fas fa-edit"></i></a> 
                                     <a href="voir-individu.php?id=<?php echo $id2;?>" onClick="return confirm('Are you sure you want to delete?')"class="btn btn-transparent btn-xs tooltips" tooltip-placement="top" tooltip="Remove"><i class="fa fa-times fa fa-white"></i></a> 
												
								  </td>
                              </tr>
                              <?php $cnt=$cnt+1; }?>
                                      
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
		<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.0/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
        <script src="../js/scripts.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/simple-datatables@latest" crossorigin="anonymous"></script>
        <script src="../js/datatables-simple-demo.js"></script>
	</body>
</html>
<?php } ?>
