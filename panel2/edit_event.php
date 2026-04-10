<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include ('include/config.php');

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$duplicate = isset($_GET['duplicate']) ? true : false;

// Fetch event data
$query = "SELECT * FROM events WHERE id = $id";
$result = mysqli_query($conn, $query);
$event = mysqli_fetch_assoc($result);

if (!$event && !$duplicate) {
    die("Événement non trouvé");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $start_date = mysqli_real_escape_string($conn, $_POST['start_date']);
    $end_date = !empty($_POST['end_date']) ? mysqli_real_escape_string($conn, $_POST['end_date']) : $start_date;

    $start_date = date('Y-m-d H:i:s', strtotime($start_date));
    $end_date = date('Y-m-d H:i:s', strtotime($end_date));

    if ($duplicate) {
        $query = "INSERT INTO events (title, description, start_date, end_date) 
                  VALUES ('$title', '$description', '$start_date', '$end_date')";
    } else {
        $query = "UPDATE events SET 
                  title = '$title',
                  description = '$description',
                  start_date = '$start_date',
                  end_date = '$end_date'
                  WHERE id = $id";
    }
    
    if (!mysqli_query($conn, $query)) {
        die("Error: " . mysqli_error($conn));
    }

    header('Location: agenda.php');
    exit();
}

// For duplicate, modify the title
if ($duplicate) {
    $event['title'] = 'Copie de ' . $event['title'];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <title><?php echo $duplicate ? 'Dupliquer' : 'Modifier'; ?> l'événement</title>
    <style>
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; }
        .form-group input, .form-group textarea { width: 100%; padding: 8px; }
        .button-group { margin-top: 20px; }
        .delete-btn { background-color: #ff4444; color: white; }
    </style>
</head>
<body>
    <h2><?php echo $duplicate ? 'Dupliquer' : 'Modifier'; ?> l'événement</h2>
    <form method="POST">
        <div class="form-group">
            <label>Titre :</label>
            <input type="text" name="title" required value="<?php echo htmlspecialchars($event['title']); ?>">
        </div>
        <div class="form-group">
            <label>Description :</label>
            <textarea name="description"><?php echo htmlspecialchars($event['description']); ?></textarea>
        </div>
        <div class="form-group">
            <label>Date de début :</label>
            <input type="datetime-local" name="start_date" required 
                   value="<?php echo date('Y-m-d\TH:i', strtotime($event['start_date'])); ?>">
        </div>
        <div class="form-group">
            <label>Date de fin :</label>
            <input type="datetime-local" name="end_date" 
                   value="<?php echo date('Y-m-d\TH:i', strtotime($event['end_date'])); ?>">
        </div>
        <div class="button-group">
            <button type="submit"><?php echo $duplicate ? 'Créer une copie' : 'Mettre à jour'; ?></button>
            <?php if (!$duplicate): ?>
                <a href="delete_event.php?id=<?php echo $id; ?>" 
                   onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet événement ?')" 
                   class="delete-btn">Supprimer</a>
            <?php endif; ?>
        </div>
    </form>
</body>
</html>
