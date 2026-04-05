
<?php
session_start();



$message_status = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nom = htmlspecialchars(trim($_POST["nom"]));
    $email = filter_var(trim($_POST["email"]), FILTER_SANITIZE_EMAIL);
    $sujet = htmlspecialchars(trim($_POST["sujet"]));
    $message = htmlspecialchars(trim($_POST["message"]));
    $note = isset($_POST["note"]) ? (int)$_POST["note"] : 0;

    if (!empty($nom) && !empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL) && !empty($sujet) && !empty($message)) {
        
        $to = "axa.wenger@gmail.com"; // Changez l'email ici si nécessaire
        $subject = "[CardEvent App] Demande d'assistance: " . $sujet;
        $note_text = $note > 0 ? "$note/5 étoiles" : "Non renseignée";
        $body = "Nom: $nom\nEmail: $email\nSujet: $sujet\nNote de l'app: $note_text\n\nMessage:\n$message";

        // Utilisation du serveur SMTP du projet
        require_once 'serveur-smtp/send.php';
        $res = sendRealEmail($to, $subject, $body);
        
        if ($res['success']) {
            $message_status = "<div class='alert alert-success'>Votre message a bien été envoyé. Retour à l'application...</div>
            <script>
                setTimeout(function() {
                    window.location.href = 'cardevent://';
                }, 2000);
            </script>";
        } else {
            $message_status = "<div class='alert alert-danger'>Erreur lors de l'envoi du message : " . htmlspecialchars($res['message']) . "</div>";
        }
    } else {
        $message_status = "<div class='alert alert-danger'>Veuillez remplir tous les champs correctement.</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>CardEvent - Assistance</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: #0d1117;
            color: #ffffff;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: #161b22;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.3);
        }
        h1 {
            text-align: center;
            color: #00d2ff;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #c9d1d9;
        }
        input[type="text"], input[type="email"], textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #30363d;
            border-radius: 6px;
            background-color: #010409;
            color: #ffffff;
            font-size: 16px;
            box-sizing: border-box;
        }
        textarea {
            resize: vertical;
            min-height: 120px;
        }
        button {
            width: 100%;
            padding: 14px;
            border: none;
            background-color: #238636;
            color: white;
            font-size: 16px;
            font-weight: bold;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        button:hover {
            background-color: #2ea043;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 6px;
            text-align: center;
        }
        .alert-success { background-color: #1e4620; color: #2ea043; border: 1px solid #2ea043;}
        .alert-danger { background-color: #4a1c1d; color: #f85149; border: 1px solid #f85149; }
        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #58a6ff;
            text-decoration: none;
        }
        .back-link:hover { text-decoration: underline; }
        .rating {
            display: flex;
            flex-direction: row-reverse;
            justify-content: flex-end;
            margin-top: 5px;
        }
        .rating input {
            display: none;
        }
        .rating label {
            cursor: pointer;
            font-size: 32px;
            color: #30363d;
            padding: 0 4px;
            margin-bottom: 0;
            display: inline-block;
        }
        .rating label:before {
            content: '★';
        }
        .rating input:checked ~ label,
        .rating label:hover,
        .rating label:hover ~ label {
            color: #ffd700;
        }
    </style>
</head>
<body>

<div class="container">
    <h1>Assistance technique</h1>
    <p style="text-align:center; color:#8b949e; margin-bottom: 30px;">Besoin d'aide avec l'application CardEvent ? Contactez-nous en remplissant le formulaire ci-dessous.</p>

    <?= $message_status ?>

    <form method="POST" action="assist.php">
        <div class="form-group">
            <label for="nom">Nom ou Pseudo :</label>
            <input type="text" id="nom" name="nom" required>
        </div>

        <div class="form-group">
            <label for="email">Adresse E-mail :</label>
            <input type="email" id="email" name="email" required>
        </div>

        <div class="form-group">
            <label for="sujet">Sujet de votre demande :</label>
            <input type="text" id="sujet" name="sujet" placeholder="Mot de passe oublié, bug, question..." required>
        </div>

        <div class="form-group">
            <label>Votre note pour l'application :</label>
            <div class="rating">
                <input type="radio" id="star5" name="note" value="5"><label for="star5" title="5 étoiles"></label>
                <input type="radio" id="star4" name="note" value="4"><label for="star4" title="4 étoiles"></label>
                <input type="radio" id="star3" name="note" value="3"><label for="star3" title="3 étoiles"></label>
                <input type="radio" id="star2" name="note" value="2"><label for="star2" title="2 étoiles"></label>
                <input type="radio" id="star1" name="note" value="1"><label for="star1" title="1 étoile"></label>
            </div>
        </div>

        <div class="form-group">
            <label for="message">Votre message :</label>
            <textarea id="message" name="message" required></textarea>
        </div>

        <button type="submit">Envoyer la demande</button>
    </form>
    
    <a href="https://viendez.com" class="back-link">← Retour au site web</a>
</div>

</body>
</html>
