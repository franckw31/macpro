<?php
echo "Current Timezone: " . date_default_timezone_get() . "\n";
echo "Now: " . time() . "\n";

$fin = "2025-12-17 16:53:41";
echo "Fin String: $fin\n";

date_default_timezone_set('UTC');
echo "UTC strtotime: " . strtotime($fin) . "\n";

date_default_timezone_set('Europe/Paris');
echo "Paris strtotime: " . strtotime($fin) . "\n";

date_default_timezone_set('UTC+2'); // Invalid
echo "UTC+2 (Invalid) strtotime: " . strtotime($fin) . "\n";
?>