<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    // Test inclusion du fichier
    ob_start();
    include('liste-participants-activite.php');
    $output = ob_get_clean();
    echo "Page loaded successfully!<br>";
    echo strlen($output) . " bytes of content<br>";
} catch (Error $e) {
    echo "ERREUR: " . $e->getMessage() . "<br>";
    echo "Fichier: " . $e->getFile() . "<br>";
    echo "Ligne: " . $e->getLine() . "<br>";
    echo "<pre>";
    echo $e->getTraceAsString();
    echo "</pre>";
} catch (Exception $e) {
    echo "EXCEPTION: " . $e->getMessage() . "<br>";
    echo "Fichier: " . $e->getFile() . "<br>";
    echo "Ligne: " . $e->getLine() . "<br>";
    echo "<pre>";
    echo $e->getTraceAsString();
    echo "</pre>";
}
?>
