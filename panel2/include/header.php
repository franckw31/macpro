<?php error_reporting(0);?>


<header class="navbar navbar-default navbar-static-top">
<link rel="icon" type="image/png" href="/panel/assets/images/toulouse.jfif">
					<!-- start: NAVBAR HEADER -->
	<div class="navbar-header">
		<a href="/index.php" class="sidebar-mobile-toggler pull-left hidden-md hidden-lg btn btn-navbar sidebar-toggle" data-toggle-class="app-slide-off" data-toggle-target="#app" data-toggle-click-outside="#sidebar">
			<i class="ti-align-justify"></i>
		</a>
		<a class="navbar-brand" href="/index.php">
			<img src="/panel/assets/images/toulouse.jfif" height="120" width="120" >
			<!-- <h2 style="padding-top:20%; color:#fff ">Admin</h2> --> 
		</a>
		<a href="#" class="sidebar-toggler pull-right visible-md visible-lg" data-toggle-class="app-sidebar-closed" data-toggle-target="#app">
			<i class="ti-align-justify"></i>
		</a>
		<a class="pull-right menu-toggler visible-xs-block" id="menu-toggler" data-toggle="collapse" href=".navbar-collapse">
			<span class="sr-only">Toggle navigation</span>
			<i class="ti-view-grid"></i>
		</a>
	</div>
					<!-- end: NAVBAR HEADER -->
					<!-- start: NAVBAR COLLAPSE -->
	<div class="navbar-collapse collapse">
		<div style="position: absolute; width: 100%; left: 0; text-align: left; pointer-events: none;">
			<h1 style="display: inline-block; margin-top: 10px; margin-left: 20px; pointer-events: auto;"><span class="badge badge-success" style="font-size: 0.5em; vertical-align: middle;">Beta-0504</span> Gestion</h1>
		</div>
		<ul class="nav navbar-right" style="display: flex; align-items: center;">
					<!-- start: MESSAGES DROPDOWN -->
			<?php
			$id=$_SESSION['id'];
			$sql=mysqli_query($con,"SELECT * FROM `membres` WHERE `id-membre` = '$id'");
			while($row=mysqli_fetch_array($sql))
				{                                                        
				?>    
				<li class="current-user" style="padding: 0 10px;">
					<a href="/panel/voir-membre.php?id=<?php echo $id;?>">
						<img src="../images/faces/<?php  echo $row['photo'];?>" width="50" height="50" style="border-radius: 5px;">
					</a>
				</li>
				<li class="dropdown current-user">
					<a href class="dropdown-toggle" data-toggle="dropdown">
						<span class="username"> <?php  echo $row['pseudo'];?>
						<i class="ti-angle-down"></i></span>
					</a>
					<ul class="dropdown-menu dropdown-dark">
						<li>
							<a href="/panel/voir-membre.php?id=<?php  echo $id;?>">
								Modifier Informations
							</a>
						</li>
						<li>
							<a href="/panel/logout.php">
								Logout
							</a>
						</li>
					</ul>
				</li>
				<?php
				}
				?>
							<!-- end: USER OPTIONS DROPDOWN -->
		</ul>
						<!-- start: MENU TOGGLER FOR MOBILE DEVICES -->
		<div class="close-handle visible-xs-block menu-toggler" data-toggle="collapse" href=".navbar-collapse">
			<div class="arrow-left"></div>
			<div class="arrow-right"></div>
		</div>
						<!-- end: MENU TOGGLER FOR MOBILE DEVICES -->
	</div>
					<!-- end: NAVBAR COLLAPSE -->
</header>
