<?php
session_start();
if (isset($_SESSION['Email_Session'])) {
    header("Location: /index.html");
    die();
}

include('config.php');
require_once 'serveur-smtp/send.php';

$msg = "";
if (isset($_POST['submit'])) {
    $email = mysqli_real_escape_string($conx, $_POST['email']);
    $CodeReset = mysqli_real_escape_string($conx, md5(rand()));
    if (mysqli_num_rows(mysqli_query($conx, "SELECT * FROM register WHERE email='{$email}'")) > 0) {
        $query = mysqli_query($conx, "UPDATE register SET CodeV='{$CodeReset}' WHERE email='{$email}'");
        if ($query) {
            $subject = 'Réinitialisation de votre mot de passe';
            $body = '<p>Voici le lien de vérification pour changer votre mot de passe : <b><a href="http://poker31.org/reg/change-Password.php?Reset=' . $CodeReset . '">Cliquez ici</a></b></p>';
            
            $res = sendRealEmail($email, $subject, $body);
            
            if ($res['success']) {
                $msg = "<div class='alert alert-info'>Nous avons envoyé un lien de vérification à votre adresse email.</div>";
            } else {
                $msg = "<div class='alert alert-danger'>Erreur lors de l'envoi de l'email : " . $res['message'] . "</div>";
            }
        }
    } else {
        $msg = "<div class='alert alert-danger'>This email:'{$email}' don't found </div>";
    }
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
    <div class="container">
        <div class="forms-container">
            <div class="signin-signup" style="left: 50%;z-index:99;">
                <form action="" method="POST" class="sign-in-form">
                    <h2 class="title">Forget Password</h2>
                    <?php echo $msg ?>
                    <div class="input-field">
                        <i class="fas fa-user"></i>
                        <input type="text" name="email" placeholder="Email" />
                    </div>
                    <input type="submit" name="submit" value="Send" class="btn solid" />
                    <p class="social-text">Or Sign in with social platforms</p>
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

        
    </div>

    <script src="app.js"></script>
</body>

</html>
