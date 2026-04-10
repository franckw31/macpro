<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include('include/config.php');

header('Content-Type: application/json');

try {
    if (!$conn) {
        throw new Exception("Database connection failed");
    }

    $date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

    // Validate date format
    if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $date)) {
        throw new Exception("Invalid date format");
    }

    $query = "SELECT `id-activite` as id, 
                     `titre-activite` as title,
                     `id-activite` as activity_id,
                     `buyin`,
                     `rake`,
                     `recave`,
                     `date_depart` as start_date,
                     `end_date`,
                     `ville`
              FROM activite 
              WHERE DATE(date_depart) = ?
              ORDER BY date_depart ASC";

    if (!($stmt = mysqli_prepare($conn, $query))) {
        throw new Exception("Prepare failed: " . mysqli_error($conn));
    }

    if (!mysqli_stmt_bind_param($stmt, 's', $date)) {
        throw new Exception("Binding parameters failed: " . mysqli_stmt_error($stmt));
    }

    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Execute failed: " . mysqli_stmt_error($stmt));
    }

    $result = mysqli_stmt_get_result($stmt);
    if ($result === false) {
        throw new Exception("Getting result failed: " . mysqli_error($conn));
    }

    $events = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $events[] = $row;
    }

    echo json_encode(['success' => true, 'data' => $events]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'date' => $date ?? null
    ]);
}

if (isset($stmt)) {
    mysqli_stmt_close($stmt);
}
if (isset($conn)) {
    mysqli_close($conn);
}
