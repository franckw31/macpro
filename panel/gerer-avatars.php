<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include('include/config.php');

// Check if user is logged in
if (strlen($_SESSION['id']) == 0) {
    header('location:../login.php');
    exit;
}

$id = intval($_GET['id']); // get member ID

// Vérifier les permissions
$user_id = intval($_SESSION['id']);
$is_admin = (isset($_SESSION['role']) && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'superadmin'));
$is_self = ($user_id === $id);

if (!$is_self && !$is_admin) {
    header('location:voir-membre.php?id=' . $id);
    exit;
}

// Récupérer les infos du membre
$query = "SELECT `id-membre`, fname, lname, photo FROM membres WHERE `id-membre` = ?";
$stmt = mysqli_prepare($con, $query);
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$member = mysqli_fetch_assoc($result);

if (!$member) {
    header('location:voir-membre.php');
    exit;
}

// Récupérer la liste des avatars
$avatar_dir = '../images/faces/';
$avatars = array();
if (is_dir($avatar_dir)) {
    $avatar_files = glob($avatar_dir . '*.{jpg,jpeg,png,gif}', GLOB_BRACE);
    $avatar_files = array_filter($avatar_files, 'is_file');
    $avatars = array_map('basename', $avatar_files);
    sort($avatars);
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Galerie d'Avatars - <?php echo htmlspecialchars($member['fname'] . ' ' . $member['lname']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            min-height: 100vh;
            color: white;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .avatar-container {
            max-width: 900px;
            margin: 40px auto;
            padding: 30px;
            background: rgba(0, 0, 0, 0.5);
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }
        
        .header-section {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
            gap: 20px;
        }
        
        .member-info img {
            width: 100px;
            height: 100px;
            border-radius: 8px;
            object-fit: cover;
            border: 3px solid #00d2ff;
        }
        
        .member-info h2 {
            margin: 0;
            font-size: 24px;
        }
        
        .member-info p {
            margin: 5px 0 0 0;
            font-size: 14px;
            color: #aaa;
        }
        
        .avatars-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 15px;
            padding: 20px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            margin: 20px 0;
        }
        
        .avatar-item {
            cursor: pointer;
            text-align: center;
            border: 3px solid transparent;
            border-radius: 8px;
            padding: 8px;
            transition: all 0.3s ease;
            background: rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }
        
        .avatar-item:hover {
            border-color: #00d2ff;
            background: rgba(0, 210, 255, 0.1);
            transform: scale(1.05);
        }
        
        .avatar-item.selected {
            border-color: #00d2ff;
            background: rgba(0, 210, 255, 0.2);
            box-shadow: 0 0 15px rgba(0, 210, 255, 0.5);
        }
        
        .avatar-item img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 4px;
            display: block;
            margin: 0 auto 8px;
        }
        
        .avatar-item-name {
            font-size: 11px;
            color: #aaa;
            word-break: break-word;
        }
        
        .selected-badge {
            display: inline-block;
            padding: 2px 8px;
            background: #00d2ff;
            color: #000;
            border-radius: 4px;
            font-size: 10px;
            font-weight: bold;
            margin-top: 5px;
        }
        
        .buttons-section {
            display: flex;
            gap: 10px;
            margin-top: 30px;
        }
        
        .btn-back {
            background: #666;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        
        .btn-back:hover {
            background: #888;
            color: white;
        }
        
        .loading-spinner {
            display: none;
            margin-left: 10px;
        }
        
        .status-message {
            margin-top: 20px;
            padding: 15px;
            border-radius: 6px;
            display: none;
        }
        
        .status-message.success {
            background: rgba(76, 175, 80, 0.3);
            color: #4CAF50;
            border: 1px solid #4CAF50;
        }
        
        .status-message.error {
            background: rgba(244, 67, 54, 0.3);
            color: #F44336;
            border: 1px solid #F44336;
        }
    </style>
</head>
<body>
    <div class="avatar-container">
        <!-- Header avec infos du membre -->
        <div class="header-section">
            <div class="member-info" style="display: flex; align-items: center; gap: 20px;">
                <img src="../images/faces/<?php echo htmlspecialchars($member['photo']); ?>" alt="<?php echo htmlspecialchars($member['fname']); ?>">
                <div>
                    <h2><?php echo htmlspecialchars($member['fname'] . ' ' . $member['lname']); ?></h2>
                    <p><i class="fa fa-user"></i> ID #<?php echo $id; ?></p>
                </div>
            </div>
        </div>
        
        <!-- Titre de la section -->
        <div style="margin-bottom: 20px;">
            <h4 style="margin: 0;">Sélectionnez un avatar</h4>
            <p style="font-size: 13px; color: #aaa; margin-top: 5px;">Cliquez sur un avatar pour le choisir</p>
        </div>
        
        <!-- Grille d'avatars -->
        <div class="avatars-grid" id="avatarsGrid">
            <?php 
            foreach ($avatars as $avatar) {
                $is_selected = ($member['photo'] === $avatar) ? 'selected' : '';
                echo '<div class="avatar-item ' . $is_selected . '" data-avatar="' . htmlspecialchars($avatar, ENT_QUOTES) . '" onclick="selectAvatar(\'' . htmlspecialchars($avatar, ENT_QUOTES) . '\')">
                        <img src="../images/faces/' . htmlspecialchars($avatar, ENT_QUOTES) . '" alt="' . htmlspecialchars($avatar, ENT_QUOTES) . '">
                        <div class="avatar-item-name">' . htmlspecialchars($avatar) . '</div>';
                        
                if ($member['photo'] === $avatar) {
                    echo '<div class="selected-badge"><i class="fa fa-check"></i> Actuel</div>';
                }
                
                echo '</div>';
            }
            ?>
        </div>
        
        <!-- Message de statut -->
        <div id="statusMessage" class="status-message"></div>
        
        <!-- Boutons -->
        <div class="buttons-section">
            <a href="voir-membre.php?id=<?php echo $id; ?>" class="btn-back">
                <i class="fa fa-arrow-left"></i> Retour au profil
            </a>
            <div style="display: flex; align-items: center; margin-left: 10px;">
                <span id="savingSpinner" class="loading-spinner">
                    <i class="fa fa-spinner fa-spin"></i> Sauvegarde en cours...
                </span>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    function selectAvatar(avatarName) {
        // Afficher le spinner
        document.getElementById('savingSpinner').style.display = 'inline';
        
        // Envoyer la requête AJAX
        $.ajax({
            url: '../chat/update_member_photo.php',
            type: 'POST',
            data: {
                id_membre: <?php echo $id; ?>,
                photo: avatarName
            },
            dataType: 'json',
            success: function(response) {
                document.getElementById('savingSpinner').style.display = 'none';
                
                if (response.success) {
                    // Mettre à jour l'affichage visuel
                    document.querySelectorAll('.avatar-item').forEach(function(el) {
                        el.classList.remove('selected');
                        const badge = el.querySelector('.selected-badge');
                        if (badge) badge.remove();
                    });
                    
                    // Marquer l'avatar sélectionné
                    const selectedItem = document.querySelector('[data-avatar="' + avatarName + '"]');
                    if (selectedItem) {
                        selectedItem.classList.add('selected');
                        selectedItem.innerHTML += '<div class="selected-badge"><i class="fa fa-check"></i> Actuel</div>';
                    }
                    
                    // Mettre à jour la photo du header
                    const headerImg = document.querySelector('.member-info img');
                    if (headerImg) {
                        headerImg.src = '../images/faces/' + avatarName;
                    }
                    
                    // Afficher le message de succès
                    showStatus('Avatar changé avec succès!', 'success');
                } else {
                    showStatus('Erreur: ' + (response.message || 'Erreur inconnue'), 'error');
                }
            },
            error: function(xhr, status, error) {
                document.getElementById('savingSpinner').style.display = 'none';
                console.error('Erreur AJAX:', error);
                console.log('Status Code:', xhr.status);
                console.log('Status Text:', xhr.statusText);
                console.log('Response Text:', xhr.responseText);
                console.log('Response Text Length:', xhr.responseText.length);
                
                // Afficher les 500 premiers caractères
                const preview = xhr.responseText.substring(0, 500);
                console.log('Preview:', preview);
                
                // Essayer de parser la réponse en JSON pour avoir plus d'infos
                try {
                    const response = JSON.parse(xhr.responseText);
                    showStatus('Erreur: ' + (response.message || 'Erreur inconnue'), 'error');
                } catch(e) {
                    showStatus('Erreur serveur (réponse non-JSON): ' + xhr.responseText.substring(0, 100), 'error');
                }
            }
        });
    }
    
    function showStatus(message, type) {
        const statusEl = document.getElementById('statusMessage');
        statusEl.textContent = message;
        statusEl.className = 'status-message ' + type;
        statusEl.style.display = 'block';
        
        // Masquer le message après 4 secondes
        setTimeout(function() {
            statusEl.style.display = 'none';
        }, 4000);
    }
    </script>
</body>
</html>
