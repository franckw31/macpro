<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
error_reporting(0);

// Sidebar uses $con from include/config.php
$idmembresession = isset($_SESSION['id']) ? $_SESSION['id'] : '';
$droits = null;
if (!empty($idmembresession) && isset($con)) {
    $res_droits = mysqli_query($con, "SELECT `droits` FROM `membres` WHERE `id-membre` = " . intval($idmembresession));
    if ($res_droits) {
        $row_droits = mysqli_fetch_assoc($res_droits);
        $droits = isset($row_droits['droits']) ? $row_droits['droits'] : null;
    }
}

if (strlen($idmembresession) == 0) {
    header('location:logout.php');
    exit;
}

$is_admin_sidebar = ($idmembresession == 265 || $droits === '2');

function render_admin_menu()
{
    ?>
    <div class="sidebar app-aside" id="sidebar">
      <div class="sidebar-container perfect-scrollbar">
        <nav>
          <div class="navbar-title"><span>.</span></div>
          <ul class="main-navigation-menu">
            <li>
              <a href="/panel/dashboard.php">
                <div class="item-content"><div class="item-media"><i class="ti-home"></i></div><div class="item-inner"><span class="title"> Menu Administrateurs</span></div></div>
              </a>
            </li>

            <li>
              <a href="javascript:void(0)">
                <div class="item-content"><div class="item-media"><i class="ti-user"></i></div><div class="item-inner"><span class="title"> Membres </span><i class="icon-arrow"></i></div></div>
              </a>
              <ul class="sub-menu">
                <li><a href="/panel/creation-membre.php"><span class="title"> Ajouter Membre</span></a></li>
                <li><a href="/panel/liste-membres-container.php"><span class="title"> Gérer les Membres </span></a></li>
                <li><a href="/panel/recherche-competences.php"><span class="title"> Recherche par Compétence </span></a></li>
              </ul>
            </li>

            <li>
              <a href="javascript:void(0)">
                <div class="item-content"><div class="item-media"><i class="ti-calendar"></i></div><div class="item-inner"><span class="title"> Activités </span><i class="icon-arrow"></i></div></div>
              </a>
              <ul class="sub-menu">
                <li><a href="/panel/ajout-activite.php"><span class="title"> Créer une Activité </span></a></li>
                <li><a href="/panel/prochaines-activites.php"><span class="title"> Prochaines Activités </span></a></li>
                <li><a href="/panel/liste-activites.php"><span class="title"> Gérer les Activités </span></a></li>
                <li><a href="/panel/liste-membres-part.php"><span class="title"> Résultats (activité en cours) </span></a></li>
                <li><a href="/panel/liste-participations-container.php"><span class="title"> Gérer les participations </span></a></li>
              </ul>
            </li>

            <li>
              <a href="javascript:void(0)">
                <div class="item-content"><div class="item-media"><i class="ti-star"></i></div><div class="item-inner"><span class="title"> Competences / Loisirs </span><i class="icon-arrow"></i></div></div>
              </a>
              <ul class="sub-menu">
                <li><a href="/panel/ajout-competences.php"><span class="title"> Gestion des Competences </span></a></li>
                <li><a href="/panel/ajout-loisirs.php"><span class="title"> Gestion des Loisirs </span></a></li>
                <li><a href="/panel/ajout-collection.php"><span class="title"> Gestion des Collections </span></a></li>
                <li><a href="/panel/ajout-blindes.php"><span class="title"> Gestion des Blindes </span></a></li>
                <li><a href="/panel/ajout-structures.php"><span class="title"> Gestion des Structures </span></a></li>
              </ul>
            </li>

            <li>
              <a href="javascript:void(0)">
                <div class="item-content"><div class="item-media"><i class="fa fa-flag-checkered"></i></div><div class="item-inner"><span class="title"> Challenges </span><i class="icon-arrow"></i></div></div>
              </a>
              <ul class="sub-menu">
                <li><a href="/panel/ajout-challenge.php"><span class="title"> Ajouter challenge</span></a></li>
                <li><a href="/panel/gestion-challenge.php"><span class="title"> Gérer les challenges </span></a></li>
              </ul>
            </li>

            <li>
              <a href="/panel/quick-geo.php">
                <div class="item-content"><div class="item-media"><i class="ti-search"></i></div><div class="item-inner"><span class="title"> Quick Géoloc. </span></div></div>
              </a>
            </li>
            <li>
              <a href="/panel/quick-part.php">
                <div class="item-content"><div class="item-media"><i class="ti-search"></i></div><div class="item-inner"><span class="title"> Quick Particip. </span></div></div>
              </a>
            </li>

            <li>
              <a href="/panel/tables.php">
                <div class="item-content"><div class="item-media"><i class="fa fa-table"></i></div><div class="item-inner"><span class="title"> Tables de poker </span></div></div>
              </a>
            </li>

            <li>
              <a href="/panel/qrcodes.php">
                <div class="item-content"><div class="item-media"><i class="fa fa-qrcode"></i></div><div class="item-inner"><span class="title"> QR Code </span></div></div>
              </a>
            </li>
            <li>
              <a href="/panel/tombolas.php">
                <div class="item-content"><div class="item-media"><i class="fa fa-ticket"></i></div><div class="item-inner"><span class="title"> Tombolas </span></div></div>
              </a>
            </li>
            <li>
              <a href="/tts/index.php">
                <div class="item-content"><div class="item-media"><i class="fa fa-microphone"></i></div><div class="item-inner"><span class="title"> Générateur MP3 (TTS) </span></div></div>
              </a>
            </li>
            <li>
              <a href="/panel/passeport.php">
                <div class="item-content"><div class="item-media"><i class="ti-id-badge"></i></div><div class="item-inner"><span class="title"><i class="fa fa-passport"></i> Passeport </span></div></div>
              </a>
            </li>
            <li>
              <a href="/panel/quickview.php">
                <div class="item-content"><div class="item-media"><i class="ti-id-badge"></i></div><div class="item-inner"><span class="title"><i class="fa fa-passport"></i> Cardevent </span></div></div>
              </a>
            </li>
            <li>
              <a href="/logs.php">
                <div class="item-content"><div class="item-media"><i class="fa fa-list"></i></div><div class="item-inner"><span class="title"> Logs d'activité </span></div></div>
              </a>
            </li>
          </ul>
        </nav>
      </div>
    </div>
    <?php
}

function render_user_menu()
{
    ?>
    <div class="sidebar app-aside" id="sidebar">
      <div class="sidebar-container perfect-scrollbar">
        <nav>
          <div class="navbar-title"><span>.</span></div>
          <ul class="main-navigation-menu">
            <li>
              <a href="/panel/dashboard.php">
                <div class="item-content"><div class="item-media"><i class="ti-home"></i></div><div class="item-inner"><span class="title"> Menu Utilisateur</span></div></div>
              </a>
            </li>

            <li>
              <a href="javascript:void(0)">
                <div class="item-content"><div class="item-media"><i class="ti-calendar"></i></div><div class="item-inner"><span class="title"> Activités </span><i class="icon-arrow"></i></div></div>
              </a>
              <ul class="sub-menu">
                
                <li><a href="/panel/prochaines-activites.php"><span class="title"> Prochaines Activités </span></a></li>
                
                <li><a href="/panel/liste-membres-part.php"><span class="title"> Résultats (activité en cours) </span></a></li>
              </ul>
            </li>

            <li>
              <a href="javascript:void(0)">
                <div class="item-content"><div class="item-media"><i class="ti-star"></i></div><div class="item-inner"><span class="title"> Competences / Loisirs </span><i class="icon-arrow"></i></div></div>
              </a>
              <ul class="sub-menu">
                <li><a href="/panel/ajout-competences.php"><span class="title"> Gestion des Competences </span></a></li>
                <li><a href="/panel/ajout-loisirs.php"><span class="title"> Gestion des Loisirs </span></a></li>
              </ul>
            </li>

            <li>
              <a href="/panel/quick-geo.php">
                <div class="item-content"><div class="item-media"><i class="ti-search"></i></div><div class="item-inner"><span class="title"> Quick Géoloc. </span></div></div>
              </a>
            </li>
            <li>
              <a href="/tts/index.php">
                <div class="item-content"><div class="item-media"><i class="fa fa-microphone"></i></div><div class="item-inner"><span class="title"> Générateur MP3 (TTS) </span></div></div>
              </a>
            </li>
            <li>
              <a href="/panel/passeport.php">
                <div class="item-content"><div class="item-media"><i class="ti-id-badge"></i></div><div class="item-inner"><span class="title"><i class="fa fa-passport"></i> Passeport </span></div></div>
              </a>
            </li>
            <li>
              <a href="/panel/quickview.php">
                <div class="item-content"><div class="item-media"><i class="ti-id-badge"></i></div><div class="item-inner"><span class="title"><i class="fa fa-passport"></i> Cardevent </span></div></div>
              </a>
            </li>
            
            <li>
              <a href="/panel/chat.php">
                <div class="item-content"><div class="item-media"><i class="fa fa-whatsapp"></i></div><div class="item-inner"><span class="title"> Chat Joueurs </span></div></div>
              </a>
            </li>
          </ul>
        </nav>
      </div>
    </div>
    <?php
}

if ($is_admin_sidebar) {
    render_admin_menu();
} else {
    render_user_menu();
}
?>
