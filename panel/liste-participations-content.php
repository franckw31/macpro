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
                                    <h1 class="mt-4">Liste des participations</h1>
                                    <ol class="breadcrumb mb-4">
                                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a>
                                        </li>
                                        <li class="breadcrumb-item active">Liste des participations
                                        </li>
                                    </ol>
                                    <table id="datatablesSimple">
                                        <thead>
                                            <tr>
                                                <th>Code</th>
                                                <th>Membre</th>
                                                <th>Ordre</th>
                                                <th>Statut</th>
                                                <th>activite</th>
                                                <th>commentaire</th>
                                                <th>Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php $ret = mysqli_query($con, "select * from participation ORDER by ds");
                                            $cnt = 1;
                                            while ($row = mysqli_fetch_array($ret)) { ?>
                                                <tr>
                                                    <td>
                                                        <?php echo $row['id-participation']; ?>
                                                    </td>
                                                    <td>
                                                        <?php echo $row['id-membre']; ?>
                                                    </td>
                                                    <td>
                                                        <?php echo $row['ordre']; ?>
                                                    </td>
                                                    <td>
                                                        <?php echo $row['option']; ?>
                                                    </td>
                                                    <td>
                                                        <?php echo $row['id-activite']; ?>
                                                    </td>
                                                    <td>
                                                        <?php echo $row['commentaire']; ?>
                                                    </td>
                                                    <td>
                                                        <?php echo $row['ds']; ?>
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
