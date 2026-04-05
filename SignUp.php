<?php
session_start();
if (isset($_SESSION['Email_Session'])) {
    header("Location: /index.html");
    die();
}
include('config.php');
require_once 'serveur-smtp/send.php';

$msg = "";
$Error_Pass="";
if (isset($_POST['submit'])) {
    $pseudo = trim($_POST['pseudo'] ?? '');
    $email = trim($_POST['Email'] ?? '');
    $Password = $_POST['Password'] ?? '';
    $Confirm_Password = $_POST['Conf-Password'] ?? '';

    $Code = bin2hex(random_bytes(16));

    $stmt = $conx->prepare("SELECT 1 FROM membres WHERE email = ? LIMIT 1");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $msg = "<div class='alert alert-danger'>Cet email : '{$email}' existe déjà.</div>";
    } else {
        if ($Password === $Confirm_Password) {
            $hashedPassword = password_hash($Password, PASSWORD_DEFAULT);

            $insert = $conx->prepare("INSERT INTO membres (`pseudo`, `email`, `Password`, `CodeV`) VALUES (?, ?, ?, ?)");
            $insert->bind_param('ssss', $pseudo, $email, $hashedPassword, $Code);

            if ($insert->execute()) {
                $subject = 'Message de Poker31 - Vérification de compte';
                $body = '<p>Bienvenue sur Poker31 ! Voici votre lien de vérification : <b><a href="http://poker31.org/reg/index.php?Verification=' . $Code . '">Cliquez ici pour vérifier votre compte</a></b></p>';

                $res = sendRealEmail($email, $subject, $body);

                if ($res['success']) {
                    $msg = "<div class='alert alert-info'>Nous avons envoyé un lien de vérification à votre adresse email.</div>";
                } else {
                    $msg = "<div class='alert alert-danger'>Erreur lors de l'envoi de l'email : " . $res['message'] . "</div>";
                }
            } else {
                $msg = "<div class='alert alert-danger'>Une erreur est survenue lors de la création du compte.</div>";
            }
        } else {
            $msg = "<div class='alert alert-danger'>Password and Confirm Password is not match</div>";
            $Error_Pass='style="border:1px Solid red;box-shadow:0px 1px 11px 0px red"';
        }
    }

    $stmt->close();
}
?>






<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <script src="https://kit.fontawesome.com/64d58efce2.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="style.css" />
    <title>Sign in & Sign up Form</title>
    <style>
        .alert {
            padding: 1rem;
            border-radius: 5px;
            color: white;
            margin: 1rem 0;
            font-weight: 500;
            width: 65%;
        }

        .alert-success {
            background-color: #42ba96;
        }

        .alert-danger {
            background-color: #fc5555;
        }

        .alert-info {
            background-color: #2E9AFE;
        }

        .alert-warning {
            background-color: #ff9966;
        }
    </style>
</head>

<body>
    <div class="container sign-up-mode">
        <div class="forms-container">
            <div class="signin-signup">
                <form action="" method="POST" class="sign-up-form">
                    <h2 class="title">Nouveau</h2>
                    <?php echo $msg ?>
                    <div class="input-field">
                        <i class="fas fa-user"></i>
                        <input type="text" name="pseudo" placeholder="pseudo" value="<?php if (isset($_POST['pseudo'])) {
                                                                                                echo $pseudo;
                                                                                            } ?>" />
                    </div>
                    <div class="input-field">
                        <i class="fas fa-envelope"></i>
                        <input type="email" name="Email" placeholder="Email" value="<?php if (isset($_POST['Email'])) {
                                                                                        echo $email;
                                                                                    } ?>" />
                    </div>
                    <div class="input-field" <?php echo $Error_Pass?>>
                        <i class="fas fa-lock"></i>
                        <input type="password" name="Password" placeholder="Password" />
                    </div>
                    <div class="input-field" <?php echo $Error_Pass?>>
                        <i class="fas fa-lock"></i>
                        <input type="password" name="Conf-Password" placeholder="Confirm Password" />
                    </div>
                    <input type="submit" name="submit" class="btn" value="Création" />
                    <p class="social-text">Or Sign up with social platforms</p>
                    <div class="social-media">
                        <a href="#" class="social-icon">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="#" class="social-icon">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="#" class="social-icon">
                            <i class="fab fa-google"></i>
                        </a>
                        <a href="#" class="social-icon">
                            <i class="fab fa-linkedin-in"></i>
                        </a>
                    </div>
                </form>
            </div>
        </div>
        <div class="panels-container">
            <div class="panel left-panel">
            </div>
            <div class="panel right-panel">
                <div class="content">
                    <h3>Déja inscrit ?</h3>
                    <p>
                        Si vous avez deja un compte veuillez vous identifier.
                    </p>
                    <a href="index.php" class="btn transparent" id="sign-in-btn" style="padding:10px 20px;text-decoration:none">
                        Re-connexion
                                                                                </a>
                </div>
                <!-- <img src="img/register.svg" class="image" alt="" /> -->
            </div>
        </div>
    </div>
    </div>
</body>
</html>
