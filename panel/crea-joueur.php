<?php
session_start();
error_reporting(0);
include('include/config.php'); 
$gpseudo = intval($_GET['pseudo']); // get value
$gfname = intval($_GET['fname']);
$source = $_GET['sour'];

if (isset($_POST['submitcreaj'])) {
    $pseudo = $_POST['pseudo'];
    $fname = $_POST['fname'];

    // echo $pseudo."-".$fname."-";
    $sql2 = mysqli_query($con, "INSERT INTO `membres` (`pseudo`, `fname`) VALUES ('$pseudo', '$fname')");
};
?>
<table>
    <form method="post">
        <tr>
            <th>Pseudoo</th>
            <td>
                <input class="form-control" id="pseudo" name="pseudo" type="text" value="<?php echo $row['pseudo']; ?>">
            </td>
            <th>Prenom</th>
            <td>
                <input class="form-control" id="fname" name="fname" type="text" value="<?php echo $row['fname']; ?>">
            </td>
            <td colspan="2" style="text-align:center">
                <button type="submit" class="btn btn-primary-orange2 btn-block" name="submitcreaj">Création Rapide du
                    joueur
                </button>
            </td>
        </tr>
    </form>
</table>
<?php
?>
<!-- <script type="text/javascript">window.location.replace("<?php echo $source.$id_activite; ?>")</script>   -->