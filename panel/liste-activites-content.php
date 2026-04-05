<!-- debut content -->
<?php include('/panel/include/config.php'); ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/simple-datatables@latest/dist/style.css" />
<div class="main-content">
    <div class="wrap-content container" id="container">
        <div class="container-fluid container-fullw bg-white">
            <div class="col-md-12">
                <div class="row margin-top-30">
                    <div class="panel-white">
                        <div class="panel-body">
                            <main>
                                <div class="container-fluid px-4">
                                    <h1 class="mt-4">Liste des activites</h1>
                                    <ol class="breadcrumb mb-4">
                                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a>
                                        </li>
                                        <li class="breadcrumb-item active">Liste des activites
                                        </li>
                                    </ol>
                                    <table id="datatablesSimple">
                                        <thead>
                                            <tr>
                                                <th>Code</th>
                                                <th>Titre</th>
                                                <th>Ville</th>
                                                <th>Organisateur</th>
                                                <th>Date </th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php $ret = mysqli_query($con, "select * from activite ORDER by id-activite");
                                            $cnt = 1;
                                            while ($row = mysqli_fetch_array($ret)) { ?>
                                            <tr>
                                                <td>
                                                    <?php echo $row['id-activite']; ?>
                                                </td>
                                                <td>
                                                    <?php echo $row['titre-activite']; ?>
                                                </td>
                                                <td>
                                                    <?php echo $row['ville']; ?>
                                                </td>
                                                <td>
                                                    <?php echo $row['id-membre']; ?>
                                                </td>
                                                <td>
                                                    <?php echo $row['date_depart']; ?>
                                                </td>
                                                <td>
                                                    <a href="voir-activite.php?id=<?php echo $row['id-activite']; ?>">
                                                        <i class="fas fa-edit"></i></a>
                                                    <a href="liste-activites.php?id=<?php echo $row['id-activite']; ?>"
                                                        onClick="return confirm('Do you really want to delete');"><i
                                                            class="fa fa-trash" aria-hidden="true"></i></a>
                                                </td>
                                            </tr>
                                            <?php $cnt = $cnt + 1;
                                            } ?>
                                        </tbody>
                                    </table>
                                </div>
                            </main>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/simple-datatables@latest" crossorigin="anonymous"></script>
<script src="../js/datatables-simple-demo.js"></script>
<!-- fin content -->