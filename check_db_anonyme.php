<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
include 'c:/Users/MSI/Desktop/www/panel/include/config.php';
try {
    $res = mysqli_query($con, "ALTER TABLE participation ADD COLUMN anonyme TINYINT(1) NOT NULL DEFAULT 0");
    echo "Column added successfully\n";
} catch (Exception $e) {
    echo "Error or already exists: " . $e->getMessage() . "\n";
}
$res = mysqli_query($con, "SHOW COLUMNS FROM participation LIKE 'anonyme'");
if(mysqli_num_rows($res) > 0) {
    echo "Column exists\n";
} else {
    echo "Column does not exist\n";
}
?>
