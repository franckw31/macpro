<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Secure session configuration
//ini_set('session.cookie_httponly', 1);
//ini_set('session.cookie_secure', 1);
//ini_set('session.use_only_cookies', 1);

// Add rate limiting
$timeout = 300;
$max_attempts = 3;

if (!isset($_SESSION['reset_attempts'])) {
    $_SESSION['reset_attempts'] = 0;
    $_SESSION['last_attempt'] = time();
}

// Add CSRF protection
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!isset($_SESSION['csrf_token']) || !isset($_POST['csrf_token']) || 
        $_SESSION['csrf_token'] !== $_POST['csrf_token']) {
        die('CSRF token validation failed');
    }
}
$csrf_token = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf_token;

include(__DIR__ . '/include/config.php');  // Fix include path

// Fix email parameter syntax
$email = isset($_GET['email']) ? $_GET['email'] : '';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

//Load Composer's autoloader
require '/panel/reg/vendor/autoload.php';

$msg = "";
if (isset($_POST['submit'])) {
    // Check rate limiting
    if (time() - $_SESSION['last_attempt'] < $timeout && $_SESSION['reset_attempts'] >= $max_attempts) {
        $msg = "<div class='alert alert-warning'>Too many attempts. Please try again later.</div>";
    } else {
        $_SESSION['reset_attempts']++;
        $_SESSION['last_attempt'] = time();
        
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        
        if(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $msg = "<div class='alert alert-danger'>Invalid email format</div>";
        } else {
            $CodeReset = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            try {
                // Test database connection
                if (!$conx) {
                    throw new Exception("Database connection failed");
                }
                
                // Add logging
                error_log("Password reset attempted for email: " . $email);
                
                // Use prepared statements to prevent SQL injection
                $stmt = $conx->prepare("SELECT * FROM register WHERE email=?");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $stmt = $conx->prepare("UPDATE register SET CodeV=?, reset_expiry=? WHERE email=?");
                    $stmt->bind_param("sss", $CodeReset, $expiry, $email);
                    
                    if ($stmt->execute()) {
                        $mail = new PHPMailer(true);

                        try {
                            $mail->SMTPDebug = 0;
                            $mail->isSMTP();
                            $mail->Host = 'smtp.gmail.com';
                            $mail->SMTPAuth = true;
                            $mail->Username = 'wenger.franck@gmail.com';
                            $mail->Password = 'Mdp4*wengerfranck';
                            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                            $mail->Port = 587;

                            // Escape output for email template
                            $resetLink = htmlspecialchars("https://pviendez.com/reg/change-Password.php?Reset=" . urlencode($CodeReset));
                            
                            // Set expiration time for reset link
                            $mail->setFrom('admin@poker31.org', 'Eagle');
                            $mail->addAddress($email);
                            $mail->isHTML(true);
                            $mail->Subject = 'confirmation Inscription';
                            $mail->Body = sprintf(
                                '<p>Please click the link below to reset your password. This link will expire in 1 hour.</p>
                                <p><a href="%s">%s</a></p>',
                                $resetLink,
                                $resetLink
                            );

                            $mail->send();
                            $msg = "<div class='alert alert-success'>Password reset instructions have been sent to your email</div>";
                            
                            // Reset attempts on success
                            $_SESSION['reset_attempts'] = 0;
                        } catch (Exception $e) {
                            $msg = "<div class='alert alert-danger'>Email could not be sent. Please try again later.</div>";
                            error_log("Mailer Error: " . $mail->ErrorInfo);
                        }
                    }
                } else {
                    // Generic message for security
                    $msg = "<div class='alert alert-info'>If your email exists in our system, you will receive reset instructions shortly</div>";
                }
            } catch (Exception $e) {
                error_log("Error in password reset: " . $e->getMessage());
                $msg = "<div class='alert alert-danger'>An error occurred. Please try again later.</div>";
            }
        }
    }
}

// Add debug output for testing
if (isset($_GET['debug']) && $_GET['debug'] == 1) {
    echo "<!--";
    echo "Session attempts: " . $_SESSION['reset_attempts'] . "\n";
    echo "Last attempt: " . date('Y-m-d H:i:s', $_SESSION['last_attempt']) . "\n";
    echo "-->";
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
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <h2 class="title">Forget MDP</h2>
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
                        <a href="#" class="social-icon"></a>
                            <i class="fab fa-linkedin-in"></i>
                        </a>
                    </div>
                </form>
            </div>
        </div>

        
    </div>

    <!-- <script src="app.js"></script> -->
</body>

</html>
