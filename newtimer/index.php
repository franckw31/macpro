<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database configuration
$db_config = [
    'host' => 'localhost',
    'dbname' => 'dbs9616600',
    'user' => 'root',
    'pass' => 'Kookies7*'
];

// Database connection function
function getDbConnection($config) {
    try {
        $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ];
        return new PDO($dsn, $config['user'], $config['pass'], $options);
    } catch(PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        return false;
    }
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);
    $conn = getDbConnection($db_config);
    
    if (!$conn) {
        echo json_encode(['success' => false, 'error' => 'Database connection failed']);
        exit;
    }
    
    switch ($data['action'] ?? '') {
        case 'save':
            try {
                $conn->beginTransaction();
                
                $stmt = $conn->prepare("INSERT INTO blind_structures (name) VALUES (?)");
                if (!$stmt->execute([$data['name']])) {
                    throw new Exception("Failed to save structure name");
                }
                $structureId = $conn->lastInsertId();
                
                $stmt = $conn->prepare("
                    INSERT INTO blind_levels 
                    (structure_id, level, small_blind, big_blind, ante, duration) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                
                foreach ($data['levels'] as $level) {
                    if (!$stmt->execute([
                        $structureId,
                        $level['level'],
                        $level['small_blind'],
                        $level['big_blind'],
                        $level['ante'],
                        $level['duration']
                    ])) {
                        throw new Exception("Failed to save blind level");
                    }
                }
                
                $conn->commit();
                echo json_encode(['success' => true, 'id' => $structureId]);
            } catch (Exception $e) {
                $conn->rollBack();
                error_log("Save error: " . $e->getMessage());
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            break;
            
        case 'load':
            try {
                if (!isset($data['id'])) {
                    throw new Exception("No structure ID provided");
                }
                
                $stmt = $conn->prepare("
                    SELECT * FROM blind_levels 
                    WHERE structure_id = ? 
                    ORDER BY level ASC
                ");
                $stmt->execute([$data['id']]);
                $levels = $stmt->fetchAll();
                
                if (empty($levels)) {
                    throw new Exception("No blind levels found");
                }
                
                echo json_encode(['success' => true, 'levels' => $levels]);
            } catch (Exception $e) {
                error_log("Load error: " . $e->getMessage());
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            break;
            
        case 'list':
            try {
                $stmt = $conn->query("
                    SELECT 
                        bs.id,
                        bs.name,
                        bs.created_at,
                        COUNT(bl.id) as level_count 
                    FROM blind_structures bs 
                    LEFT JOIN blind_levels bl ON bs.id = bl.structure_id 
                    GROUP BY bs.id, bs.name, bs.created_at
                    ORDER BY bs.created_at DESC
                ");
                
                $structures = $stmt->fetchAll();
                echo json_encode(['success' => true, 'structures' => $structures]);
            } catch (Exception $e) {
                error_log("List error: " . $e->getMessage());
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            break;

        case 'delete':
            try {
                if (!isset($data['id'])) {
                    throw new Exception("No structure ID provided");
                }
                
                $conn->beginTransaction();
                
                $stmt = $conn->prepare("DELETE FROM blind_levels WHERE structure_id = ?");
                $stmt->execute([$data['id']]);
                
                $stmt = $conn->prepare("DELETE FROM blind_structures WHERE id = ?");
                $stmt->execute([$data['id']]);
                
                $conn->commit();
                echo json_encode(['success' => true]);
            } catch (Exception $e) {
                $conn->rollBack();
                error_log("Delete error: " . $e->getMessage());
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            break;
            
        case 'rename':
            try {
                if (!isset($data['id']) || !isset($data['name'])) {
                    throw new Exception("Missing required data");
                }
                
                $stmt = $conn->prepare("UPDATE blind_structures SET name = ? WHERE id = ?");
                if (!$stmt->execute([$data['name'], $data['id']])) {
                    throw new Exception("Failed to rename structure");
                }
                
                echo json_encode(['success' => true]);
            } catch (Exception $e) {
                error_log("Rename error: " . $e->getMessage());
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
    exit;
}

// Change this line near the top of your file
$wsHost = "ws://192.168.1.166:8181"; // Use your actual local IP address
echo "<script>const WS_HOST = '$wsHost';</script>";
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Poker Timer</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <script src="https://code.responsivevoice.org/responsivevoice.js?key=RTEc1M0w" onload="try{ responsiveVoice.setDefaultVoice('French Female'); }catch(e){ console.warn('responsiveVoice load onload', e); }"></script>
    <style>
        /* Update the body style */
        body {
            background-image: url('bg.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            color: white;
            font-family: 'Roboto', sans-serif;
            margin: 0;
            padding: 20px;
            min-height: 100vh;
        }

        /* Update container style to ensure content remains readable */
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: rgba(30, 30, 30, 0.8); /* Changed opacity from 0.95 to 0.7 */
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px); /* Optional: adds a blur effect */
        }

        /* Global styles */
    .time-controls {
        display: flex;
        gap: 10px;
        justify-content: center;
        margin: 10px 0;
    }

    .time-controls button {
        flex: 1;
        max-width: 200px;
    }
    /* Timer display */
    .cardevent-display {
        font-size: 240px;
        font-weight: 400;
        color:rgb(255, 17, 0);
        text-align: center;
        margin: 5px 0;
        font-variant-numeric: tabular-nums;
        text-shadow: 0 2px 4px rgba(0,0,0,0.3);
    }

    /* Blind info */
    .blind-info {
        font-size: 90px;
        color:rgb(255, 255, 0);
        text-align: center; /* Alignement à gauche au lieu de center */
        margin: 5px 20px; /* Ajout d'une marge pour éviter que le texte ne colle au bord */
        text-shadow: 0 1px 2px rgba(0,0,0,0.2);
    }
    .blind-info-next {
        font-size: 32px;
        color: rgb(42, 164, 235);
        
        text-align: center;
        margin: 15px 0;
        text-shadow: 0 1px 2px rgba(0,0,0,0.2);
    }

    .resume-indicator {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 28px;
        margin-top: 10px;
        padding: 6px 12px;
        border-radius: 999px;
        background: rgba(60, 127, 255, 0.16);
        border: 1px solid rgba(144, 202, 249, 0.34);
        color: #b9dbff;
        font-size: 12px;
        letter-spacing: 0.04em;
        opacity: 0;
        transform: translateY(4px);
        transition: opacity 180ms ease, transform 180ms ease;
        pointer-events: none;
    }

    .resume-indicator.visible {
        opacity: 1;
        transform: translateY(0);
    }

    /* Controls */
    .controls {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
        margin: 20px 0;
    }

    /* Buttons */
    button {
        padding: 15px;
        font-size: 18px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        text-transform: uppercase;
        font-weight: 600;
        transition: all 0.3s ease;
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        touch-action: manipulation;
        -webkit-tap-highlight-color: transparent;
        min-height: 44px; /* Minimum touch target size */
    }

    button:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.3);
    }

    button:active {
        transform: translateY(0);
        box-shadow: 0 1px 2px rgba(0,0,0,0.2);
    }

    .start-btn { 
        background-color: #4CAF50; 
        color: white; 
    }

    .pause-btn { 
        background-color: #FFC107; 
        color: black; 
    }

    .reset-btn { 
        background-color: #F44336; 
        color: white; 
    }

    .edit-btn { 
        background-color: #2196F3; 
        color: white;
        width: 100%;
        margin-top: 10px;
    }

    button:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        transform: none;
    }

    /* Edit Panel */
    .edit-panel, .load-panel {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.9);
        padding: 16px;
        z-index: 1000;
        overflow-y: auto;
        overflow-x: hidden;
    }

    .edit-content, .load-content {
        background: linear-gradient(180deg, rgba(23, 27, 36, 0.98), rgba(11, 14, 20, 0.98));
        padding: 20px;
        border-radius: 24px;
        max-width: 760px;
        margin: 12px auto;
        box-shadow: 0 24px 60px rgba(0,0,0,0.45);
        width: min(100%, 760px);
        box-sizing: border-box;
        border: 1px solid rgba(255,255,255,0.08);
    }

    .edit-header {
        position: sticky;
        top: 0;
        z-index: 25;
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        margin: -20px -20px 18px;
        padding: 18px 20px 14px;
        background: linear-gradient(180deg, rgba(17, 22, 31, 0.98), rgba(17, 22, 31, 0.84));
        border-radius: 24px 24px 0 0;
        backdrop-filter: blur(14px);
        border-bottom: 1px solid rgba(255,255,255,0.06);
    }

    .edit-title-stack {
        display: flex;
        flex-direction: column;
        gap: 6px;
        min-width: 0;
    }

    .edit-title {
        margin: 0;
        color: #edf6ff;
        font-size: 26px;
        line-height: 1.15;
        font-weight: 700;
    }

    .edit-subtitle {
        color: rgba(255,255,255,0.62);
        font-size: 14px;
        line-height: 1.4;
    }

    .blind-editor {
        margin: 0;
        padding: 0;
        background: transparent;
        border: 0;
        box-shadow: none;
    }

    .blind-grid {
        display: grid;
        gap: 14px;
    }

    .blind-row {
        display: flex;
        flex-direction: column;
        gap: 14px;
        padding: 16px;
        position: relative;
        background: rgba(255,255,255,0.04);
        border-radius: 20px;
        border: 1px solid rgba(255,255,255,0.08);
        transition: transform 0.2s ease, border-color 0.2s ease, background 0.2s ease;
        width: 100%;
        box-sizing: border-box;
    }

    .blind-row:hover {
        background: rgba(255,255,255,0.06);
        border-color: rgba(144, 202, 249, 0.18);
    }

    .blind-row.highlighted {
        background: rgba(33, 150, 243, 0.12);
        border-color: rgba(80, 171, 255, 0.55);
        box-shadow: 0 0 0 1px rgba(80,171,255,0.28), 0 14px 26px rgba(0,0,0,0.22);
    }

    .blind-row-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }

    .blind-badge {
        display: inline-flex;
        align-items: center;
        min-height: 32px;
        padding: 0 12px;
        border-radius: 999px;
        background: rgba(24, 196, 255, 0.14);
        color: #8edbff;
        font-size: 13px;
        font-weight: 700;
        letter-spacing: 0.03em;
    }

    .blind-fields {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
    }

    .blind-field {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .blind-field-label {
        color: rgba(255,255,255,0.72);
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 0.03em;
        text-transform: uppercase;
    }

    .blind-row input {
        width: 100%;
        min-height: 50px;
        padding: 0 14px;
        background: rgba(0,0,0,0.28);
        border: 1px solid rgba(255,255,255,0.1);
        border-radius: 14px;
        color: white;
        font-size: 17px;
        font-weight: 600;
        box-sizing: border-box;
        appearance: none;
        -webkit-appearance: none;
        margin: 0;
        -moz-appearance: textfield;
        transition: all 0.2s ease;
    }

    .blind-row input:focus {
        outline: none;
        border-color: #2196F3;
        box-shadow: 0 0 0 3px rgba(33,150,243,0.22);
        background: rgba(0,0,0,0.44);
    }

    .blind-row input.invalid {
        border-color: #ff6b6b;
        box-shadow: 0 0 0 3px rgba(255, 107, 107, 0.18);
        background: rgba(90, 18, 18, 0.35);
    }

    .edit-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin: 18px 0 14px;
        flex-wrap: wrap;
    }

    .editor-validation-message {
        min-height: 22px;
        margin: 0;
        color: rgba(255,255,255,0.72);
        font-size: 13px;
        text-align: left;
        flex: 1;
    }

    .editor-validation-message.error {
        color: #ff8b8b;
    }

    .editor-validation-message.success {
        color: #7ee0a0;
    }

    .row-actions {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-shrink: 0;
    }

    .insert-btn, .remove-btn {
        width: 42px;
        height: 42px;
        min-width: 42px;
        min-height: 42px;
        color: white;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-size: 20px;
        font-weight: 700;
        transition: transform 0.18s ease, box-shadow 0.18s ease, background 0.18s ease;
        border: none;
        box-shadow: 0 8px 18px rgba(0,0,0,0.22);
    }

    .insert-btn:hover,
    .remove-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 12px 22px rgba(0,0,0,0.3);
    }

    .insert-btn {
        background: linear-gradient(180deg, #37d86c, #1faa4d);
    }

    .remove-btn {
        background: linear-gradient(180deg, #ff6969, #df3f3f);
    }

    /* Structure Items */
    .structure-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px;
        margin: 10px 0;
        background: rgba(255,255,255,0.1);
        border-radius: 8px;
        transition: background 0.3s ease;
    }

    .structure-item:hover {
        background: rgba(255,255,255,0.15);
    }

    .structure-info {
        flex: 1;
        font-size: 16px;
    }

    .structure-info div {
        color: #90CAF9;
        font-size: 14px;
        margin-top: 5px;
    }

    .actions {
        display: flex;
        gap: 10px;
    }

    .actions button {
        padding: 8px 16px;
        font-size: 14px;
    }

    /* Edit Actions */
    .edit-actions {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
        margin-top: 20px;
    }

    .edit-done-bar {
        display: none;
        flex-shrink: 0;
    }

    .edit-done-bar.visible {
        display: inline-flex;
    }

    .edit-done-btn {
        width: auto;
        min-height: 46px;
        margin: 0;
        padding: 0 16px;
        border-radius: 999px;
        background: #18c4ff;
        color: #061018;
        font-weight: 700;
        white-space: nowrap;
    }

    .keyboard-dismiss-input {
        position: fixed;
        opacity: 0;
        pointer-events: none;
        width: 1px;
        height: 1px;
        bottom: 0;
        left: 0;
        border: 0;
        padding: 0;
    }

    /* Responsive Design */
    @media (max-width: 480px) {
        body {
            padding: 10px;
        }

        .cardevent-display { 
            font-size: 80px; 
        }

        .blind-info { 
            font-size: 24px; 
        }

        .controls { 
            grid-template-columns: 1fr; 
        }

        .actions { 
            flex-direction: column; 
        }

        .edit-header {
            margin: -20px -20px 14px;
            padding: 14px 16px 12px;
            gap: 10px;
        }

        .edit-title {
            font-size: 20px;
        }

        .edit-subtitle {
            font-size: 13px;
        }

        .edit-done-btn {
            min-height: 42px;
            padding: 0 14px;
            font-size: 14px;
        }

        .edit-content, .load-content {
            margin: 8px auto;
            padding: 16px;
            border-radius: 20px;
        }

        .blind-fields {
            grid-template-columns: 1fr;
        }

        .edit-toolbar {
            align-items: stretch;
        }

        .editor-validation-message {
            width: 100%;
            order: 2;
        }
    }

    /* Dark mode optimization */
    @media (prefers-color-scheme: dark) {
        .blind-row input {
            background: #2A2A2A;
        }

        .structure-item {
            background: rgba(255,255,255,0.08);
        }

        .structure-item:hover {
            background: rgba(255,255,255,0.12);
        }
    }

    .structure-controls {
        display: flex;
        gap: 10px;
        justify-content: center;
        margin: 10px 0;
    }

    .structure-controls button {
        flex: 1;
        margin: 0;  /* Override any existing margin */
    }

    #clock {
        background: rgba(0, 0, 0, 0.5);
        padding: 5px 10px;
        border-radius: 5px;
        font-family: 'Roboto', sans-serif;
        font-weight: bold;
        z-index: 1000;
        font-size: 24px; /* Taille réduite de 48px à 24px */
        position: fixed;
        top: 10px;
        right: 10px;
        color: white;
        text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
    }

    /* Add or update these CSS rules in your style section */
    @media screen and (max-width: 768px) {
        .container {
            max-width: 100%;
            padding: 10px;
            margin: 0;
        }

        .cardevent-display {
            font-size: 120px; /* Smaller font for mobile */
        }

        .blind-info {
            font-size: 40px; /* Smaller font for mobile */
        }

        .blind-info-next {
            font-size: 24px;
        }

        .controls {
            grid-template-columns: 1fr; /* Stack buttons vertically */
            gap: 5px;
        }

        .time-controls {
            flex-direction: column;
            gap: 5px;
        }

        .time-controls button {
            max-width: 100%;
        }

        .structure-controls {
            flex-direction: column;
            gap: 5px;
        }

        .structure-controls button {
            width: 100%;
        }

        button {
            padding: 12px;
            font-size: 16px;
        }

        /* Edit panel adjustments */
        .edit-content {
            margin: 10px;
            padding: 10px;
            max-height: 80vh;
        }

        .blind-row {
            grid-template-columns: 1fr 1fr; /* 2 columns instead of 4 */
            gap: 5px;
        }

        .blind-headers {
            grid-template-columns: 1fr 1fr;
            font-size: 14px;
        }

        .blind-row input {
            padding: 6px;
            font-size: 14px;
        }

        .remove-btn {
            right: -25px;
            width: 20px;
            height: 20px;
        }

        /* Clock adjustment */
        #clock {
            font-size: 16px; /* Encore plus petit sur mobile */
            top: 5px;
            right: 5px;
        }

        /* Load panel adjustments */
        .structure-item {
            flex-direction: column;
            gap: 10px;
        }

        .actions {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 5px;
        }

        .actions button {
            padding: 8px;
            font-size: 12px;
        }
    }

    /* Add viewport meta tag if not present */
    @viewport {
        width: device-width;
        initial-scale: 1;
    }

    .level-btn {
        width: 40px;
        height: 40px;
        font-size: 24px;
        padding: 0;
        border-radius: 50%;
        background-color: #2196F3;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
    }

    .level-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    body.timer-modern {
        background: #000;
        background-image: radial-gradient(circle at top, rgba(0, 210, 255, 0.14), transparent 28%), radial-gradient(circle at bottom, rgba(255, 170, 0, 0.08), transparent 22%), linear-gradient(180deg, #050608 0%, #000 100%);
        padding: 0;
        overflow-x: hidden;
    }

    .timer-modern .container {
        max-width: 820px;
        min-height: 100vh;
        margin: 0 auto;
        padding: 18px 18px 120px;
        background: transparent;
        box-shadow: none;
        backdrop-filter: none;
        border-radius: 0;
    }

    .screen-shell {
        max-width: 760px;
        margin: 0 auto;
    }

    .topbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        margin-bottom: 6px;
    }

    .pill-btn,
    .icon-btn,
    .control-btn,
    .primary-control,
    .wide-action {
        border: 1px solid rgba(255,255,255,0.10);
        background: rgba(24,24,24,0.92);
        color: #f4f6fb;
        border-radius: 24px;
        text-transform: none;
        box-shadow: inset 0 1px 0 rgba(255,255,255,0.05), 0 10px 24px rgba(0,0,0,0.24);
    }

    .pill-btn {
        min-height: 76px;
        padding: 0 24px;
        font-size: 20px;
        font-weight: 500;
        color: #31c7ff;
        border-radius: 999px;
    }

    .title-stack {
        text-align: center;
        flex: 1;
    }

    .live-title {
        color: #18c4ff;
        font-size: 32px;
        font-weight: 700;
        line-height: 1.1;
        margin-bottom: 4px;
    }

    .live-subtitle {
        color: rgba(255,255,255,0.46);
        font-size: 15px;
        font-weight: 500;
    }

    .right-actions {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 10px;
        min-height: 76px;
    }

    .icon-btn {
        width: 52px;
        height: 52px;
        min-height: 52px;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 26px;
        color: #6b74ff;
        background: transparent;
        border: 0;
        box-shadow: none;
    }

    .icon-svg {
        width: 28px;
        height: 28px;
        display: inline-block;
    }

    .icon-svg svg,
    .control-icon svg,
    .action-icon svg {
        width: 100%;
        height: 100%;
        stroke: currentColor;
        fill: none;
        stroke-width: 1.9;
        stroke-linecap: round;
        stroke-linejoin: round;
    }

    .control-icon,
    .action-icon {
        width: 30px;
        height: 30px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .action-icon {
        width: 24px;
        height: 24px;
    }

    #clock {
        font-size: 14px;
        font-weight: 700;
        letter-spacing: 0.04em;
        color: #6b74ff;
    }

    #soundToggle.muted {
        opacity: 0.6;
    }

    .icon-btn.close {
        width: 44px;
        height: 44px;
        min-height: 44px;
        border-radius: 50%;
        background: #d1d1d1;
        color: #1d1d1d;
        font-size: 20px;
        font-weight: 900;
    }

    .hero {
        padding-top: 10px;
        text-align: center;
    }

    .timer-ring {
        --progress: 0;
        width: min(50vw, 360px);
        height: min(50vw, 360px);
        margin: 0 auto;
        border-radius: 50%;
        position: relative;
        background: conic-gradient(#12cfff calc(var(--progress) * 1turn), rgba(18, 207, 255, 0.22) 0);
        box-shadow: 0 0 14px rgba(18,207,255,0.26), 0 0 32px rgba(18,207,255,0.10);
        padding: 7px;
    }

    .timer-ring::before {
        content: '';
        position: absolute;
        inset: 7px;
        border-radius: 50%;
        background: #000;
        box-shadow: inset 0 0 40px rgba(18,207,255,0.08);
    }

    .timer-center {
        position: absolute;
        inset: 0;
        z-index: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 18px;
    }

    .level-line {
        color: rgba(255,255,255,0.56);
        font-size: clamp(14px, 1.9vw, 22px);
        letter-spacing: 0.16em;
        text-transform: uppercase;
        margin-bottom: 12px;
        font-weight: 500;
    }

    .cardevent-display {
        font-size: clamp(78px, 14vw, 150px);
        line-height: 1;
        font-weight: 500;
        color: #12cfff;
        margin: 0;
        text-shadow: 0 0 18px rgba(18, 207, 255, 0.26);
        font-variant-numeric: tabular-nums;
    }

    .blinds-block {
        margin-top: 26px;
    }

    .blind-info {
        margin: 0;
        font-size: clamp(36px, 6.8vw, 62px);
        line-height: 1;
        color: #ffd119;
        text-shadow: 0 0 14px rgba(255,209,25,0.14);
        font-weight: 700;
        text-align: center;
    }

    .blind-caption {
        margin-top: 12px;
        color: rgba(255,255,255,0.35);
        font-size: 24px;
        letter-spacing: 0.18em;
        text-transform: uppercase;
    }

    .blind-info-next {
        margin-top: 18px;
        font-size: 24px;
        color: rgba(255,255,255,0.58);
        text-align: center;
        font-weight: 700;
    }

    .pause-line {
        margin-top: 10px;
        font-size: 18px;
        text-align: center;
        color: #cf7a1e;
    }

    .control-dock {
        margin: 30px auto 0;
        max-width: 620px;
        padding: 14px;
        border-radius: 30px;
        background: rgba(20,20,20,0.94);
        border: 1px solid rgba(255,255,255,0.08);
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: 14px;
        align-items: stretch;
    }

    .control-btn,
    .primary-control {
        min-height: 66px;
        width: 100%;
        height: 100%;
        padding: 6px 6px;
        border-radius: 16px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 4px;
        font-size: 13px;
        font-weight: 700;
        background: #242424;
        color: #f3f5f9;
        text-align: center;
    }

    .control-dock .level-btn {
        width: 100%;
        height: 100%;
        min-height: 66px;
        border-radius: 16px;
        padding: 6px 6px;
        background: #242424;
    }

    .control-btn span:first-child,
    .primary-control span:first-child {
        font-size: 24px;
        line-height: 1;
    }

    .control-btn .control-icon,
    .primary-control .control-icon {
        width: 24px;
        height: 24px;
    }

    .control-btn small,
    .primary-control small {
        color: rgba(255,255,255,0.62);
        font-size: 10px;
        font-weight: 600;
    }

    .primary-control {
        background: rgba(114, 62, 17, 0.45);
        border-color: rgba(255, 149, 48, 0.45);
        color: #ff9a2f;
    }

    .primary-control small {
        color: #ffb260;
    }

    .action-dock {
        margin: 20px auto 0;
        max-width: 620px;
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: 12px;
    }

    .action-dock button {
        width: 100%;
        min-height: 62px;
        height: 100%;
        padding: 12px 10px;
        border-radius: 18px;
        font-size: 14px;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
    }

    .action-dock .control-btn {
        background: #242424;
        color: #f3f5f9;
        border: 1px solid rgba(255,255,255,0.08);
        box-shadow: inset 0 1px 0 rgba(255,255,255,0.05), 0 10px 24px rgba(0,0,0,0.24);
    }

    .wide-action {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        background: #202020;
    }

    .start-btn { background: #202020; color: #f4f6fb; }
    .pause-btn { background: rgba(114, 62, 17, 0.45); color: #ff9a2f; }
    .reset-btn { background: #2c2020; color: #ff8b8b; }
    .edit-btn { background: #1c2431; color: #b9dbff; }

    .utility-hidden { display: none !important; }

    @media (max-width: 768px) {
        .topbar { gap: 8px; }
        .pill-btn { min-height: 56px; padding: 0 16px; font-size: 16px; }
        .right-actions { min-height: 56px; gap: 4px; padding: 4px 8px; }
        .icon-btn { width: 40px; height: 40px; min-height: 40px; font-size: 20px; }
        .icon-btn.close { width: 36px; height: 36px; min-height: 36px; }
        .live-title { font-size: 22px; }
        .live-subtitle { font-size: 12px; }
        .blind-caption { font-size: 16px; }
        .blind-info-next { font-size: 16px; }
        .pause-line { font-size: 14px; }
        .resume-indicator { font-size: 11px; padding: 5px 10px; }
        .timer-ring { width: min(54vw, 280px); height: min(54vw, 280px); }
        .timer-center { padding: 14px; }
        .level-line { font-size: 12px; margin-bottom: 8px; }
        .cardevent-display { font-size: clamp(62px, 12vw, 104px); }
        .control-dock { gap: 8px; padding: 10px; margin-top: 24px; }
        .control-btn, .primary-control { min-height: 78px; border-radius: 16px; font-size: 14px; }
        .control-dock .level-btn { min-height: 78px; border-radius: 16px; }
        .control-btn span:first-child, .primary-control span:first-child { font-size: 24px; }
        .control-btn small, .primary-control small { font-size: 10px; }
        .action-dock { grid-template-columns: repeat(5, 1fr); gap: 8px; }
        .action-dock button { min-height: 58px; font-size: 11px; padding: 10px 6px; border-radius: 16px; }
    }
    </style>
</head>
<body class="timer-modern">
    <div class="container">
        <div class="screen-shell">
            <div class="topbar">
                <button class="pill-btn" type="button" onclick="window.location.href='/panel/quickview.php';">Retour</button>
                <div class="title-stack">
                    <div class="live-title">Live</div>
                    <div class="live-subtitle" id="timer-date-label">—</div>
                </div>
                <div class="pill-btn right-actions">
                    <button class="icon-btn" id="soundToggle" type="button" title="Son"><span class="icon-svg"><svg viewBox="0 0 24 24"><path d="M14 5l-5 4H5v6h4l5 4V5z"></path><path d="M18 9.5a4 4 0 0 1 0 5"></path><path d="M20.5 7a7.5 7.5 0 0 1 0 10"></path></svg></span></button>
                    <button class="icon-btn" type="button" title="Horloge"><span id="clock">--:--:--</span></button>
                    <button class="icon-btn close" type="button" onclick="window.location.href='/panel/quickview.php';" title="Fermer">✕</button>
                </div>
            </div>

            <section class="hero">
                <div class="timer-ring" id="timer-ring">
                    <div class="timer-center">
                        <div class="level-line">Niveau <span id="level">1</span> / <span id="level-total">19</span></div>
                        <div class="cardevent-display" id="cardevent">15:00</div>
                    </div>
                </div>

                <div class="blinds-block">
                    <div class="blind-info"><span id="blinds">25/50</span></div>
                    <div class="blind-caption">Blindes</div>
                    <div class="blind-info-next">→ <span id="next-blind">50/100</span></div>
                    <div class="pause-line" id="pause-info">Pause dans —</div>
                    <div class="resume-indicator" id="resume-indicator" aria-live="polite"></div>
                    <span id="ante" class="utility-hidden">0</span>
                </div>

                <div class="control-dock">
                    <button class="control-btn level-btn" id="prevLevelBtn"><span class="control-icon"><svg viewBox="0 0 24 24"><path d="M11 7l-5 5 5 5"></path><path d="M18 7l-5 5 5 5"></path></svg></span><small>Préc.</small></button>
                    <button class="control-btn edit-btn" id="minusMinBtn"><span class="control-icon"><svg viewBox="0 0 24 24"><path d="M6 12h12"></path></svg></span><small>-1 min</small></button>
                    <button class="primary-control start-btn" id="startPauseBtn"><span class="control-icon"><svg viewBox="0 0 24 24"><path d="M10 8v8"></path><path d="M14 8v8"></path></svg></span><small>Pause</small></button>
                    <button class="control-btn edit-btn" id="plusMinBtn"><span class="control-icon"><svg viewBox="0 0 24 24"><path d="M12 6v12"></path><path d="M6 12h12"></path></svg></span><small>+1 min</small></button>
                    <button class="control-btn level-btn" id="nextLevelBtn"><span class="control-icon"><svg viewBox="0 0 24 24"><path d="M13 7l5 5-5 5"></path><path d="M6 7l5 5-5 5"></path></svg></span><small>Suiv.</small></button>
                </div>

                <div class="action-dock">
                    <button class="wide-action reset-btn" id="resetBtn">ReStart Game</button>
                    <button class="edit-btn" id="loadFromDbBtn">Charger</button>
                    <button class="edit-btn" id="editBtn">Modifier</button>
                    <button class="edit-btn" id="saveToDbBtn">Enreg.</button>
                    <button class="control-btn" id="restartBlindsBtn">ReStart Blinde</button>
                </div>
            </section>
        </div>
    </div>

    <div class="edit-panel" id="editPanel">
    <div class="edit-content">
        <form id="blindEditorForm" novalidate>
            <div class="edit-header">
                <div class="edit-title-stack">
                    <h2 class="edit-title">Modifier les blindes</h2>
                    <div class="edit-subtitle">Ajuste chaque niveau, valide rapidement la saisie, puis enregistre la nouvelle structure.</div>
                </div>
                <div class="edit-done-bar" id="editDoneBar">
                    <button class="edit-done-btn" id="commitFieldBtn" type="button">Terminer</button>
                </div>
            </div>
            <div class="blind-editor" id="blindEditor"></div>
            <div class="edit-toolbar">
                <button class="edit-btn" id="addLevelBtn" type="button">+ Ajouter un niveau</button>
                <div class="editor-validation-message" id="editorValidationMessage"></div>
            </div>
            <div class="edit-actions">
                <button class="start-btn" id="saveEditBtn" type="submit">Enregistrer</button>
                <button class="reset-btn" id="cancelEditBtn" type="button">Annuler</button>
            </div>
        </form>
    </div>
</div>
    <input id="keyboardDismissInput" class="keyboard-dismiss-input" type="text" readonly aria-hidden="true" tabindex="-1">

    <div class="load-panel" id="loadPanel">
        <div class="load-content">
            <h2 style="color: #90CAF9;">Load Blind Structure</h2>
            <div id="structuresList"></div>
            <button class="reset-btn" id="closeLoadBtn">Close</button>
        </div>
    </div>
    <div style="text-align: center; margin-top: 10px; color: #90CAF9; font-size: 12px;">
    Click anywhere to enable sound notifications
</div>
        
    <audio id="levelSound" preload="auto">
        <source src="level-up.mp3" type="audio/mpeg">
    </audio>
    <audio id="endSound" preload="auto">
        <source src="end.mp3" type="audio/mpeg">
    </audio>
    <audio id="levelSound">
    <source src="data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1HOTgzLyspJyUjIiAfHx4dHRwcHBsaGhsZGhoZGhsaGxwaGxwbHBwcHR0dHh4dHh8eHh8fHyAhICEhISIjIiMkIyQkJSYlJiYnKCcpKSorKywtLS4vMDEyMzQ2Nzg5Ozw9P0BBQkNFRkdISUpLTE1OT1BRUVJTVFVVVlVWV1dYV1hXWFhZWFdYV1hXWFhXWFdYV1hYWFhYWVlaW1tcXV5fYGFiY2RlZmdoaWprbG1ub3BxcnN0dXZ3eHl6ent8fX5/gIGCg4SFhoeIiYqLjI2Oj5CRkpOUlZaXmJmam5ydnp+goaKjpKWmp6ipqqusra6vsLGys7S1tre4ubq7vL2+v8DBwsPExcbHyMnKy8zNzs/Q0dLT1NXW19jZ2tvc3d7f4OHi4+Tl5ufo6err7O3u7/Dx8vP09fb3+Pn6+/z9/v8AAQIDBAUGBwgJCgsMDQ4PEBESExQVFhcYGRobHB0eHyAhIiMkJSYnKCkqKywtLi8wMTIzNDU2Nzg5Ojs8PT4/QEFCQ0RFRkdISUpLTE1OT1BRUlNUVVZXWFlaW1xdXl9gYWJjZGVmZ2hpamtsbW5vcHFyc3R1dXZ3eHl6ent8fX5/gIGCg4SFhoeIiYqLjI2Oj5CRkpOUlZaXmJmam5ydnp+goaKjpKWmp6ipqqusra6vsLGys7S1tre4ubq7vL2+v8DBwsPExcbHyMnKy8zNzs/Q0dLT1NXW19jZ2tvc3d7f4OHi4+Tl5ufo6err7O3u7/Dx8vP09fb3+Pn6+/z9/v8=" type="audio/wav">
    </audio>
    <audio id="endSound">
    <source src="data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1HOTgzLyspJyUjIiAfHx4dHRwcHBsaGhsZGhoZGhsaGxwaGxwbHBwcHR0dHh4dHh8eHh8fHyAhICEhISIjIiMkIyQkJSYlJiYnKCcpKSorKywtLS4vMDEyMzQ2Nzg5Ozw9P0BBQkNFRkdISUpLTE1OT1BRUVJTVFVVVlVWV1dYV1hXWFhZWFdYV1hXWFhXWFdYV1hYWFhYWVlaW1tcXV5fYGFiY2RlZmdoaWprbG1ub3BxcnN0dXZ3eHl6ent8fX5/gIGCg4SFhoeIiYqLjI2Oj5CRkpOUlZaXmJmam5ydnp+goaKjpKWmp6ipqqusra6vsLGys7S1tre4ubq7vL2+v8DBwsPExcbHyMnKy8zNzs/Q0dLT1NXW19jZ2tvc3d7f4OHi4+Tl5ufo6err7O3u7/Dx8vP09fb3+Pn6+/z9/v8=" type="audio/wav">
    </audio>

    <script>
        // Initial blind structure
        let blindLevels = [
            { level: 1, small_blind: 100, big_blind: 200, ante: 0, duration: 1200 },
            { level: 2, small_blind: 200, big_blind: 400, ante: 0, duration: 1200 },
            { level: 3, small_blind: 300, big_blind: 600, ante: 0, duration: 1200 },
            { level: 4, small_blind: 400, big_blind: 800, ante: 0, duration: 1200 },
            { level: 5, small_blind: 500, big_blind: 1000, ante: 0, duration: 1200 },
            { level: 6, small_blind: 600, big_blind: 1200, ante: 0, duration: 1200 },
            { level: 7, small_blind: 800, big_blind: 1600, ante: 0, duration: 1200 },
            { level: 8, small_blind: 0, big_blind: 0, ante: 0, duration: 600 },
            { level: 9, small_blind: 1000, big_blind: 2000, ante: 0, duration: 1140 },
            { level: 10, small_blind: 1500, big_blind: 3000, ante: 0, duration: 1080 },
            { level: 11, small_blind: 2000, big_blind: 4000, ante: 0, duration: 1020 },            
            { level: 12, small_blind: 3000, big_blind: 6000, ante: 0, duration: 960 },
            { level: 13, small_blind: 4000, big_blind: 8000, ante: 0, duration: 900 },
            { level: 14, small_blind: 0, big_blind: 0, ante: 0, duration: 0 },
            { level: 15, small_blind: 5000, big_blind: 10000, ante: 0, duration: 900 },
            { level: 16, small_blind: 8000, big_blind: 16000, ante: 0, duration: 900 },
            { level: 17, small_blind: 10000, big_blind: 20000, ante: 0, duration: 900 },
            { level: 18, small_blind: 15000, big_blind: 30000, ante: 0, duration: 900 },
            { level: 19, small_blind: 20000, big_blind: 40000, ante: 0, duration: 3600 }
        
   ];

//    let blindLevels = [
//             { level: 1, small_blind: 100, big_blind: 200, ante: 0, duration: 900 },
//             { level: 2, small_blind: 200, big_blind: 400, ante: 0, duration: 900 },
//             { level: 3, small_blind: 300, big_blind: 600, ante: 0, duration: 900 },
//             { level: 4, small_blind: 400, big_blind: 800, ante: 0, duration: 900 },
//             { level: 5, small_blind: 500, big_blind: 1000, ante: 0, duration: 900 },
//             { level: 6, small_blind: 600, big_blind: 1200, ante: 0, duration: 900 },
//             { level: 7, small_blind: 800, big_blind: 1600, ante: 0, duration: 900 },
//             { level: 8, small_blind: 1000, big_blind: 2000, ante: 0, duration: 900 },
//             { level: 9, small_blind: 0, big_blind: 0, ante: 0, duration: 600 },
//             { level: 10, small_blind: 1500, big_blind: 3000, ante: 0, duration: 1020 },
//             { level: 11, small_blind: 2000, big_blind: 4000, ante: 0, duration: 1080 },            
//             { level: 12, small_blind: 3000, big_blind: 6000, ante: 0, duration: 1140 },
//             { level: 13, small_blind: 4000, big_blind: 8000, ante: 0, duration: 1200 },
//             { level: 14, small_blind: 5000, big_blind: 10000, ante: 0, duration: 1200 },
//             { level: 15, small_blind: 6000, big_blind: 12000, ante: 0, duration: 1200 },
//             { level: 16, small_blind: 8000, big_blind: 16000, ante: 0, duration: 1200 },
//             { level: 17, small_blind: 10000, big_blind: 20000, ante: 0, duration: 1200 },
//             { level: 18, small_blind: 15000, big_blind: 30000, ante: 0, duration: 1200 },
//             { level: 19, small_blind: 20000, big_blind: 40000, ante: 0, duration: 3600 }
        
//    ];

        let currentLevel = 0;
        let timeLeft = blindLevels[0].duration;
        let timerInterval;
        let isRunning = false;
        let ws;
        let isLocalUpdate = false;
        let timerEndsAt = null;
        let wakeLockSentinel = null;
        let resumeIndicatorTimeout = null;
        let speechVoice = null;
        let speechUnlocked = false;
        let responsiveVoiceName = 'French Female';

        function showResumeIndicator(message = 'Timer recalé après veille') {
            const indicator = document.getElementById('resume-indicator');
            if (!indicator) return;

            indicator.textContent = message;
            indicator.classList.add('visible');

            if (resumeIndicatorTimeout) {
                clearTimeout(resumeIndicatorTimeout);
            }

            resumeIndicatorTimeout = setTimeout(() => {
                indicator.classList.remove('visible');
            }, 2800);
        }

        async function requestWakeLock() {
            if (!('wakeLock' in navigator) || !isRunning) return;
            try {
                if (!wakeLockSentinel) {
                    wakeLockSentinel = await navigator.wakeLock.request('screen');
                    wakeLockSentinel.addEventListener('release', () => {
                        wakeLockSentinel = null;
                    });
                }
            } catch (error) {
                console.warn('Wake lock unavailable', error);
            }
        }

        async function releaseWakeLock() {
            if (!wakeLockSentinel) return;
            try {
                await wakeLockSentinel.release();
            } catch (error) {
                console.warn('Wake lock release failed', error);
            } finally {
                wakeLockSentinel = null;
            }
        }

        function normalizeRunningTimerState(referenceNow = Date.now()) {
            if (!isRunning) return;
            if (!timerEndsAt && timeLeft > 0) {
                timerEndsAt = referenceNow + (timeLeft * 1000);
            }
            while (timerEndsAt && currentLevel < blindLevels.length - 1 && timerEndsAt <= referenceNow) {
                currentLevel += 1;
                timerEndsAt += blindLevels[currentLevel].duration * 1000;
            }
            if (timerEndsAt && timerEndsAt <= referenceNow && currentLevel >= blindLevels.length - 1) {
                timeLeft = 0;
                stopTimer(false);
                return;
            }
            if (timerEndsAt) {
                timeLeft = Math.max(0, Math.ceil((timerEndsAt - referenceNow) / 1000));
            }
        }

        function startTimer(broadcast = true) {
            isRunning = true;
            normalizeRunningTimerState();
            if (!timerEndsAt) {
                timerEndsAt = Date.now() + (timeLeft * 1000);
            }
            updateButtonStates();
            requestWakeLock();
            
            clearInterval(timerInterval);
            timerInterval = setInterval(() => {
                const previousTimeLeft = timeLeft;
                normalizeRunningTimerState();
                if (timeLeft > 0) {
                    updateDisplay();
                    if (broadcast) saveTimerState();
                    if (previousTimeLeft > 30 && timeLeft <= 30) {
                        playSound('endSound');
                    }
                } else {
                    handleLevelEnd();
                }
            }, 1000);
            
            if (broadcast) saveTimerState();
        }

        function stopTimer(shouldPersist = true) {
            clearInterval(timerInterval);
            timerInterval = null;
            isRunning = false;
            timerEndsAt = null;
            releaseWakeLock();
            const startPauseBtn = document.getElementById('startPauseBtn');
            startPauseBtn.innerHTML = '<span class="control-icon"><svg viewBox="0 0 24 24"><path d="M8 6l10 6-10 6z"></path></svg></span><small>Démarrer</small>';
            startPauseBtn.className = 'primary-control start-btn';
            
            // Enable minute adjustment buttons
            document.getElementById('minusMinBtn').disabled = false;
            document.getElementById('plusMinBtn').disabled = false;
            
            // Enable level change buttons when cardevent is stopped
            updateLevelButtons();
            if (shouldPersist) saveTimerState();
        }

        function updateButtonStates() {
            const startPauseBtn = document.getElementById('startPauseBtn');
            if (startPauseBtn) {
                startPauseBtn.innerHTML = isRunning
                    ? '<span class="control-icon"><svg viewBox="0 0 24 24"><path d="M10 8v8"></path><path d="M14 8v8"></path></svg></span><small>Pause</small>'
                    : '<span class="control-icon"><svg viewBox="0 0 24 24"><path d="M8 6l10 6-10 6z"></path></svg></span><small>Démarrer</small>';
                startPauseBtn.className = isRunning ? 'primary-control pause-btn' : 'primary-control start-btn';
            }
        }

        function initAudio() {
    const sounds = ['levelSound', 'endSound'];
    sounds.forEach(soundId => {
        const sound = document.getElementById(soundId);
        if (sound) {
            sound.load();
            // Set volume to 0 and play/pause to initialize
            sound.volume = 0;
            sound.play().then(() => {
                sound.pause();
                sound.volume = 1;
            }).catch(() => {});
        }
    });
}

function getPreferredSpeechVoice() {
    if (!('speechSynthesis' in window)) return null;

    const voices = window.speechSynthesis.getVoices();
    if (!voices.length) return null;

    return voices.find((voice) => voice.lang && voice.lang.toLowerCase().startsWith('fr-fr'))
        || voices.find((voice) => voice.lang && voice.lang.toLowerCase().startsWith('fr'))
        || voices.find((voice) => voice.default)
        || voices[0]
        || null;
}

function getPreferredResponsiveVoice() {
    if (typeof responsiveVoice === 'undefined' || typeof responsiveVoice.getVoices !== 'function') {
        return 'French Female';
    }

    const voices = responsiveVoice.getVoices() || [];
    if (!voices.length) return 'French Female';

    const normalizedVoices = voices.map((voice) => typeof voice === 'string' ? { name: voice, lang: '' } : voice);
    const foundFemale = normalizedVoices.find((voice) => voice.name === 'French Female');
    const foundAmelie = normalizedVoices.find((voice) => (voice.name || '').includes('Amelie'));
    const foundFrench = normalizedVoices.find((voice) => {
        const name = (voice.name || '').toLowerCase();
        const lang = (voice.lang || '').toLowerCase();
        return lang.startsWith('fr') || name.includes('french');
    });
    const foundThomas = normalizedVoices.find((voice) => (voice.name || '').includes('Thomas'));

    return (foundFemale && foundFemale.name)
        || (foundAmelie && foundAmelie.name)
        || (foundFrench && foundFrench.name)
        || (foundThomas && foundThomas.name)
        || (normalizedVoices[0] && normalizedVoices[0].name)
        || 'French Female';
}

function speakWithResponsiveVoice(message) {
    if (typeof responsiveVoice === 'undefined' || typeof responsiveVoice.speak !== 'function') {
        return false;
    }

    try {
        responsiveVoiceName = getPreferredResponsiveVoice();
        responsiveVoice.setDefaultVoice(responsiveVoiceName);
        responsiveVoice.cancel();
        responsiveVoice.speak(message, responsiveVoiceName, {
            rate: 0.95,
            pitch: 1,
            volume: 1
        });
        return true;
    } catch (error) {
        console.log('ResponsiveVoice error:', error);
        return false;
    }
}

function unlockSpeechSynthesis() {
    if (speechUnlocked || !('speechSynthesis' in window)) return;

    try {
        window.speechSynthesis.cancel();
        window.speechSynthesis.resume();
        speechVoice = getPreferredSpeechVoice();

        const primer = new SpeechSynthesisUtterance(' ');
        primer.lang = speechVoice?.lang || 'fr-FR';
        primer.voice = speechVoice;
        primer.volume = 0.01;
        primer.rate = 1;
        primer.pitch = 1;

        window.speechSynthesis.speak(primer);
        window.speechSynthesis.cancel();
        speechUnlocked = true;
    } catch (error) {
        console.log('Speech unlock error:', error);
    }
}

        function playSound(soundId) {
    // Ne jouer le son que pour 'levelSound' (30 secondes restantes et changement de niveau) 
    // et 'endSound' (fin du tournoi)
    if (soundId !== 'levelSound' && soundId !== 'endSound') return;

    try {
        const sound = document.getElementById(soundId);
        if (sound) {
            sound.currentTime = 0;
            sound.play().catch(error => console.log('Playback prevented:', error));
        }
    } catch (e) {
        console.log('Sound error:', e);
    }
}

function speakAnnouncement(message) {
    const soundToggle = document.getElementById('soundToggle');
    if (soundToggle && soundToggle.classList.contains('muted')) return;
    if (!message) return;

    if (speakWithResponsiveVoice(message)) {
        speechUnlocked = true;
        return;
    }

    if (!('speechSynthesis' in window)) return;

    try {
        if (!speechUnlocked) {
            unlockSpeechSynthesis();
        }

        speechVoice = speechVoice || getPreferredSpeechVoice();
        window.speechSynthesis.cancel();
        window.speechSynthesis.resume();
        const utterance = new SpeechSynthesisUtterance(message);
        utterance.lang = speechVoice?.lang || 'fr-FR';
        utterance.voice = speechVoice;
        utterance.volume = 1;
        utterance.rate = 0.95;
        utterance.pitch = 1;
        window.speechSynthesis.speak(utterance);
    } catch (error) {
        console.log('Speech synthesis error:', error);
    }
}

// Add these functions to your JavaScript code
function saveTimerState() {
    const timerState = {
        currentLevel: currentLevel,
        timeLeft: timeLeft,
        isRunning: isRunning,
        lastUpdate: Date.now(),
        timerEndsAt: timerEndsAt
    };
    localStorage.setItem('timerState', JSON.stringify(timerState));

    // Send update to WebSocket server
    if (ws && ws.readyState === WebSocket.OPEN) {
        ws.send(JSON.stringify({
            type: 'update',
            data: timerState
        }));
    }
}

function loadTimerState() {
    const savedState = localStorage.getItem('timerState');
    if (savedState) {
        const state = JSON.parse(savedState);
        const now = Date.now();
        const previousStateSnapshot = JSON.stringify({
            currentLevel: state.currentLevel,
            timeLeft: state.timeLeft,
            isRunning: !!state.isRunning,
            timerEndsAt: state.timerEndsAt ?? null
        });
        
        currentLevel = state.currentLevel;
        isRunning = !!state.isRunning;
        timerEndsAt = state.timerEndsAt ?? null;
        
        if (isRunning) {
            timeLeft = Math.max(0, parseInt(state.timeLeft ?? 0, 10));
            normalizeRunningTimerState(now);
            if (isRunning && timeLeft > 0) {
                startTimer(false);
            } else {
                updateButtonStates();
            }
        } else {
            timeLeft = Math.max(0, parseInt(state.timeLeft ?? blindLevels[currentLevel].duration, 10));
            updateButtonStates();
        }
        
        updateDisplay();

        const normalizedStateSnapshot = JSON.stringify({
            currentLevel,
            timeLeft,
            isRunning,
            timerEndsAt: timerEndsAt ?? null
        });

        if (normalizedStateSnapshot !== previousStateSnapshot) {
            saveTimerState();
            if (!!state.isRunning && document.visibilityState === 'visible') {
                showResumeIndicator(isRunning
                    ? 'Timer repris après veille'
                    : 'Tournoi recalé à la reprise');
            }
        }
    }
}

// Modify your toggleStartPause function to save state
function toggleStartPause() {
    if (isRunning) {
        stopTimer();
        speakAnnouncement('Pause du taymeur');
    } else {
        startTimer();
        speakAnnouncement('Reprise du taymeur');
    }
    updateLevelButtons();
    saveTimerState();
}

function handleLevelEnd() {
    if (currentLevel < blindLevels.length - 1) {
        currentLevel++;
        timeLeft = blindLevels[currentLevel].duration;
        timerEndsAt = Date.now() + (timeLeft * 1000);
        updateDisplay();
        saveTimerState();
        playSound('levelSound'); // Garder le son uniquement pour le changement de niveau
        speakAnnouncement(`Niveau ${blindLevels[currentLevel].level}`);
    } else {
        stopTimer();
        playSound('endSound'); // Garder le son pour la fin du tournoi
        speakAnnouncement('Fin du tournoi');
        alert('Tournoi terminé !');
    }
}

function initWebSocket() {
    ws = new WebSocket(WS_HOST);

    ws.onopen = function() {
        console.log('Connected to WebSocket server');
    };
    
    ws.onmessage = function(event) {
        const message = JSON.parse(event.data);
        if (message.type === 'sync') {
            syncTimerState(message.data);
        }
    };

    ws.onclose = function() {
        // Try to reconnect in 5 seconds
        setTimeout(initWebSocket, 5000);
    };
}

function syncTimerState(state) {
    // Only update if we're not the source of the change
    if (!isLocalUpdate) {
        currentLevel = state.currentLevel;
        timeLeft = state.timeLeft;
        isRunning = !!state.isRunning;
        timerEndsAt = state.timerEndsAt ?? null;
        
        // Update UI
        normalizeRunningTimerState();
        updateDisplay();
        
        // Update cardevent state
        if (isRunning && !timerInterval) {
            startTimer(false);
        } else if (!isRunning && timerInterval) {
            stopTimer(false);
        }
    }
}

        // Modifier la fonction resetTimer pour mettre à jour le bouton
        function resetTimer() {
            if (confirm("Êtes-vous sûr de vouloir réinitialiser le cardevent ?")) {
                stopTimer(false);
                currentLevel = 0;
                timeLeft = blindLevels[0].duration;
                timerEndsAt = null;
                updateDisplay();
                speakAnnouncement('Démarrage de la partie');
                startTimer();
            }
        }

        // Time adjustment function
        function adjustTime(minutes) {
            if (!isRunning) {
                const newTime = timeLeft + (minutes * 60);
                // Ensure time doesn't go below 0
                timeLeft = Math.max(0, newTime);
                updateDisplay();
            }
        }

        function restartBlinds() {
            // Garde le niveau actuel, réinitialise le temps et relance le niveau
            if (isRunning) {
                stopTimer(false);
            }
            timeLeft = blindLevels[currentLevel].duration;
            timerEndsAt = null;
            updateDisplay();
            speakAnnouncement(`Redémarrage du niveau ${blindLevels[currentLevel].level}`);
            startTimer();
        }

        function changeLevel(direction) {
            if (!isRunning) {
                const newLevel = currentLevel + direction;
                if (newLevel >= 0 && newLevel < blindLevels.length) {
                    currentLevel = newLevel;
                    timeLeft = blindLevels[currentLevel].duration;
                    timerEndsAt = null;
                    updateDisplay();
                    updateLevelButtons();
                    saveTimerState();
                }
            }
        }

        function updateLevelButtons() {
            const prevBtn = document.getElementById('prevLevelBtn');
            const nextBtn = document.getElementById('nextLevelBtn');
            
            if (prevBtn) {
                prevBtn.disabled = isRunning || currentLevel === 0;
            }
            if (nextBtn) {
                nextBtn.disabled = isRunning || currentLevel === blindLevels.length - 1;
            }
        }

        function getStructureValidationResult(structure) {
            if (!Array.isArray(structure) || structure.length === 0) {
                return { valid: false, message: 'La structure de blindes est vide.' };
            }

            for (let index = 0; index < structure.length; index++) {
                const level = structure[index];
                const levelNumber = index + 1;

                if (!Number.isFinite(level.small_blind) || level.small_blind < 0) {
                    return { valid: false, message: `Niveau ${levelNumber} : la petite blinde est invalide.`, rowIndex: index, field: 'small-blind' };
                }

                if (!Number.isFinite(level.big_blind) || level.big_blind < 0) {
                    return { valid: false, message: `Niveau ${levelNumber} : la grosse blinde est invalide.`, rowIndex: index, field: 'big-blind' };
                }

                if (level.big_blind > 0 && level.big_blind < level.small_blind) {
                    return { valid: false, message: `Niveau ${levelNumber} : la grosse blinde doit être supérieure ou égale à la petite blinde.`, rowIndex: index, field: 'big-blind' };
                }

                if (!Number.isFinite(level.ante) || level.ante < 0) {
                    return { valid: false, message: `Niveau ${levelNumber} : l'ante est invalide.`, rowIndex: index, field: 'ante' };
                }

                if (!Number.isFinite(level.duration) || level.duration < 0) {
                    return { valid: false, message: `Niveau ${levelNumber} : la durée est invalide.`, rowIndex: index, field: 'duration' };
                }

                if (level.small_blind === 0 && level.big_blind === 0 && level.duration === 0) {
                    continue;
                }

                if (level.duration <= 0) {
                    return { valid: false, message: `Niveau ${levelNumber} : la durée doit être supérieure à 0.`, rowIndex: index, field: 'duration' };
                }
            }

            return { valid: true, message: 'Structure valide.' };
        }

        function validateStructure(structure) {
            const result = getStructureValidationResult(structure);
            if (!result.valid) {
                alert(result.message);
                return false;
            }

            return true;
        }

        function parseBlindEditorInteger(value) {
            if (typeof value !== 'string') return Number.NaN;
            const normalizedValue = value.replace(/[^0-9]/g, '').trim();
            if (!normalizedValue.length) return Number.NaN;
            return Number.parseInt(normalizedValue, 10);
        }

        function normalizeBlindEditorInput(input) {
            if (!(input instanceof HTMLInputElement)) return;
            input.value = input.value.replace(/[^0-9]/g, '');
        }

        function getEditedStructureFromEditor() {
            const rows = document.querySelectorAll('.blind-row');

            return Array.from(rows).map((row, index) => ({
                level: index + 1,
                small_blind: parseBlindEditorInteger(row.querySelector('.small-blind').value),
                big_blind: parseBlindEditorInteger(row.querySelector('.big-blind').value),
                ante: parseBlindEditorInteger(row.querySelector('.ante').value),
                duration: parseBlindEditorInteger(row.querySelector('.duration').value) * 60
            }));
        }

        function updateBlindEditorValidation() {
            const validationMessage = document.getElementById('editorValidationMessage');
            const saveEditBtn = document.getElementById('saveEditBtn');
            const rows = document.querySelectorAll('.blind-row');

            rows.forEach((row) => {
                row.querySelectorAll('input').forEach((input) => input.classList.remove('invalid'));
            });

            if (!rows.length) {
                if (validationMessage) {
                    validationMessage.textContent = '';
                    validationMessage.className = 'editor-validation-message';
                }
                if (saveEditBtn) saveEditBtn.disabled = true;
                return { valid: false };
            }

            const structure = getEditedStructureFromEditor();
            const result = getStructureValidationResult(structure);

            if (!result.valid && Number.isInteger(result.rowIndex)) {
                const row = rows[result.rowIndex];
                const input = row ? row.querySelector(`.${result.field}`) : null;
                if (input) {
                    input.classList.add('invalid');
                }
            }

            if (validationMessage) {
                validationMessage.textContent = result.message || '';
                validationMessage.className = `editor-validation-message ${result.valid ? 'success' : 'error'}`.trim();
            }

            if (saveEditBtn) {
                saveEditBtn.disabled = !result.valid;
            }

            return result;
        }

        function commitActiveBlindEditorField() {
            const blindEditor = document.getElementById('blindEditor');
            const activeElement = document.activeElement;

            if (blindEditor && activeElement instanceof HTMLElement && blindEditor.contains(activeElement)) {
                activeElement.blur();
            }
        }

        function updateEditDoneBarVisibility() {
            const blindEditor = document.getElementById('blindEditor');
            const editDoneBar = document.getElementById('editDoneBar');
            const activeElement = document.activeElement;

            if (!blindEditor || !editDoneBar) return;

            const shouldShow = activeElement instanceof HTMLElement
                && activeElement.tagName === 'INPUT'
                && blindEditor.contains(activeElement);

            editDoneBar.classList.toggle('visible', shouldShow);
        }

        function applyEditedStructure(newStructure) {
            const previousLevel = Math.min(currentLevel, newStructure.length - 1);
            blindLevels = newStructure;
            currentLevel = Math.max(0, previousLevel);

            if (!isRunning) {
                timeLeft = blindLevels[currentLevel].duration;
                timerEndsAt = null;
            } else {
                timeLeft = Math.min(timeLeft, blindLevels[currentLevel].duration);
                timerEndsAt = Date.now() + (timeLeft * 1000);
            }

            updateDisplay();
            updateLevelButtons();
            saveTimerState();
            hideEditPanel();
        }

        // Structure management functions
function renderBlindEditor() {
    const blindEditor = document.getElementById('blindEditor');
    if (!blindEditor) return;

    const rows = blindLevels.map((level, index) => `
                <div class="blind-row" data-level="${index + 1}">
                    <div class="blind-row-top">
                        <span class="blind-badge">Niveau ${index + 1}</span>
                        <div class="row-actions">
                            <button class="insert-btn" type="button" onclick="insertLevelAt(${index})">+</button>
                            ${index > 0 ? `<button class="remove-btn" type="button" onclick="removeLevel(${index})">×</button>` : ''}
                        </div>
                    </div>
                    <div class="blind-fields">
                        <label class="blind-field">
                            <span class="blind-field-label">Petite blinde</span>
                            <input type="text" value="${level.small_blind}" inputmode="numeric" enterkeyhint="done" pattern="[0-9]*" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" class="small-blind">
                        </label>
                        <label class="blind-field">
                            <span class="blind-field-label">Grosse blinde</span>
                            <input type="text" value="${level.big_blind}" inputmode="numeric" enterkeyhint="done" pattern="[0-9]*" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" class="big-blind">
                        </label>
                        <label class="blind-field">
                            <span class="blind-field-label">Ante</span>
                            <input type="text" value="${level.ante}" inputmode="numeric" enterkeyhint="done" pattern="[0-9]*" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" class="ante">
                        </label>
                        <label class="blind-field">
                            <span class="blind-field-label">Durée (min)</span>
                            <input type="text" value="${level.duration / 60}" inputmode="numeric" enterkeyhint="done" pattern="[0-9]*" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" class="duration">
                        </label>
                    </div>
                </div>
    `).join('');

    blindEditor.innerHTML = `<div class="blind-grid">${rows}</div>`;
    updateBlindEditorValidation();
}

function addLevel() {
            const currentRow = document.querySelector('.blind-row.highlighted');
            let insertIndex = blindLevels.length;
            
            if (currentRow) {
                insertIndex = parseInt(currentRow.dataset.level);
            }

            const prevLevel = insertIndex > 0 ? blindLevels[insertIndex - 1] : 
                           blindLevels[0] || { small_blind: 25, big_blind: 50, ante: 0, duration: 900 };
            const nextLevel = insertIndex < blindLevels.length ? blindLevels[insertIndex] : null;
            
            // Calculate new blind values based on adjacent levels
            let smallBlind, bigBlind;
            if (prevLevel && nextLevel) {
                // Insert between two levels - average the values
                smallBlind = Math.round((prevLevel.small_blind + nextLevel.small_blind) / 2);
                bigBlind = Math.round((prevLevel.big_blind + nextLevel.big_blind) / 2);
            } else if (prevLevel) {
                // Insert at end - increment by last step size or default
                const step = blindLevels.length > 1 ? 
                    blindLevels[blindLevels.length - 1].small_blind - blindLevels[blindLevels.length - 2].small_blind :
                    25;
                smallBlind = prevLevel.small_blind + step;
                bigBlind = prevLevel.big_blind + (step * 2);
            } else {
                // First level
                smallBlind = 25;
                bigBlind = 50;
            }

            const newLevel = {
                level: insertIndex + 1,
                small_blind: Math.max(25, smallBlind),
                big_blind: Math.max(50, bigBlind),
                ante: prevLevel ? prevLevel.ante : 0,
                duration: prevLevel ? prevLevel.duration : 900
            };

            blindLevels.splice(insertIndex, 0, newLevel);
            
            // Re-number all levels
            blindLevels.forEach((level, i) => level.level = i + 1);
            renderBlindEditor();
            
            // Highlight the new row
            const newRow = document.querySelector(`.blind-row[data-level="${newLevel.level}"]`);
            if (newRow) {
                newRow.classList.add('highlighted');
                newRow.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }

        function removeLevel(index) {
            if (index > 0 && index < blindLevels.length) {
                blindLevels.splice(index, 1);
                blindLevels.forEach((level, i) => level.level = i + 1);
                renderBlindEditor();
            }
        }

        function insertLevelAt(index) {
            const prevLevel = blindLevels[index];
            const nextLevel = blindLevels[index + 1];
            
            // Calculate new blind values based on adjacent levels
            let smallBlind, bigBlind;
            if (prevLevel && nextLevel) {
                smallBlind = Math.round((prevLevel.small_blind + nextLevel.small_blind) / 2);
                bigBlind = Math.round((prevLevel.big_blind + nextLevel.big_blind) / 2);
            } else if (prevLevel) {
                smallBlind = prevLevel.small_blind * 2;
                bigBlind = prevLevel.big_blind * 2;
            } else {
                smallBlind = 25;
                bigBlind = 50;
            }

            const newLevel = {
                level: index + 2, // Insert after current index
                small_blind: smallBlind,
                big_blind: bigBlind,
                ante: prevLevel ? prevLevel.ante : 0,
                duration: prevLevel ? prevLevel.duration : 900
            };

            blindLevels.splice(index + 1, 0, newLevel);
            
            // Re-number all levels
            blindLevels.forEach((level, i) => level.level = i + 1);
            renderBlindEditor();
            
            // Highlight the new row
            const newRow = document.querySelector(`.blind-row[data-level="${newLevel.level}"]`);
            if (newRow) {
                newRow.classList.add('highlighted');
                newRow.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }

        // Database functions
        async function saveToDatabase() {
            const name = prompt("Enter a name for this blind structure:");
            if (!name) return;

            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        action: 'save',
                        name: name,
                        levels: blindLevels
                    })
                });

                const result = await response.json();
                if (result.success) {
                    alert('Structure saved successfully!');
                } else {
                    throw new Error(result.error);
                }
            } catch (error) {
                console.error('Save error:', error);
                alert('Error saving structure: ' + error.message);
            }
        }

        async function showLoadPanel() {
            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({action: 'list'})
                });

                const result = await response.json();
                if (!result.success) {
                    throw new Error(result.error);
                }

                const structures = result.structures || [];
                const html = structures.map(s => `
                    <div class="structure-item">
                        <div class="structure-info">
                            ${s.name} (${new Date(s.created_at).toLocaleDateString()})
                            <div>Levels: ${s.level_count}</div>
                        </div>
                        <div class="actions">
                            <button class="edit-btn" onclick="loadStructure(${s.id})">Load</button>
                            <button class="edit-btn" onclick="renameStructure(${s.id}, '${s.name}')">Rename</button>
                            <button class="reset-btn" onclick="deleteStructure(${s.id}, '${s.name}')">Delete</button>
                        </div>
                    </div>
                `).join('');

                document.getElementById('structuresList').innerHTML = 
                    structures.length ? html : '<div class="structure-item">No saved structures</div>';
                document.getElementById('loadPanel').style.display = 'block';
            } catch (error) {
                console.error('Load error:', error);
                alert('Error loading structures: ' + error.message);
            }
        }

        async function loadStructure(id) {
            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({action: 'load', id: id})
                });

                const result = await response.json();
                if (!result.success) {
                    throw new Error(result.error);
                }

                blindLevels = result.levels;
                currentLevel = 0;
                timeLeft = blindLevels[0].duration;
                updateDisplay();
                document.getElementById('loadPanel').style.display = 'none';
            } catch (error) {
                console.error('Load error:', error);
                alert('Error loading structure: ' + error.message);
            }
        }

        async function deleteStructure(id, name) {
            if (!confirm(`Are you sure you want to delete "${name}"?`)) {
                return;
            }

            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({action: 'delete', id: id})
                });

                const result = await response.json();
                if (result.success) {
                    alert('Structure deleted successfully!');
                    showLoadPanel();
                } else {
                    throw new Error(result.error);
                }
            } catch (error) {
                console.error('Delete error:', error);
                alert('Error deleting structure: ' + error.message);
            }
        }

        async function renameStructure(id, oldName) {
            const newName = prompt("Enter new name:", oldName);
            if (!newName || newName === oldName) return;

            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        action: 'rename',
                        id: id,
                        name: newName
                    })
                });

                const result = await response.json();
                if (result.success) {
                    alert('Structure renamed successfully!');
                    showLoadPanel();
                } else {
                    throw new Error(result.error);
                }
            } catch (error) {
                console.error('Rename error:', error);
                alert('Error renaming structure: ' + error.message);
            }
        }

        // Event listeners
        document.addEventListener('DOMContentLoaded', () => {
    // Initialize all buttons and displays
    updateDisplay();
    
    // Main control buttons
    const startPauseBtn = document.getElementById('startPauseBtn');
    const resetBtn = document.getElementById('resetBtn');
    
    if (startPauseBtn) startPauseBtn.addEventListener('click', toggleStartPause); // Supprimé initAudio
    if (resetBtn) resetBtn.addEventListener('click', resetTimer);

    // Time adjustment buttons
    const minusMinBtn = document.getElementById('minusMinBtn');
    const plusMinBtn = document.getElementById('plusMinBtn');
    const restartBlindsBtn = document.getElementById('restartBlindsBtn');
    
    if (minusMinBtn) minusMinBtn.addEventListener('click', () => adjustTime(-1));
    if (plusMinBtn) plusMinBtn.addEventListener('click', () => adjustTime(1));
    if (restartBlindsBtn) restartBlindsBtn.addEventListener('click', restartBlinds);

    // Structure management buttons
    const editBtn = document.getElementById('editBtn');
    const saveToDbBtn = document.getElementById('saveToDbBtn');
    const loadFromDbBtn = document.getElementById('loadFromDbBtn');
    const closeLoadBtn = document.getElementById('closeLoadBtn');
    const addLevelBtn = document.getElementById('addLevelBtn');
    const saveEditBtn = document.getElementById('saveEditBtn');
    const cancelEditBtn = document.getElementById('cancelEditBtn');
    const blindEditor = document.getElementById('blindEditor');
    const commitFieldBtn = document.getElementById('commitFieldBtn');
    const editPanel = document.getElementById('editPanel');
    
    if (editBtn) editBtn.addEventListener('click', showEditPanel);
    if (saveToDbBtn) saveToDbBtn.addEventListener('click', saveToDatabase);
    if (loadFromDbBtn) loadFromDbBtn.addEventListener('click', showLoadPanel);
    if (closeLoadBtn) closeLoadBtn.addEventListener('click', () => {
        document.getElementById('loadPanel').style.display = 'none';
    });
    if (addLevelBtn) addLevelBtn.addEventListener('click', addLevel);
    if (blindEditor) {
        const refreshEditorValidation = () => updateBlindEditorValidation();
        blindEditor.addEventListener('input', (event) => {
            if (event.target instanceof HTMLInputElement) {
                normalizeBlindEditorInput(event.target);
            }
            refreshEditorValidation();
        });
        blindEditor.addEventListener('change', refreshEditorValidation);
        blindEditor.addEventListener('focusout', refreshEditorValidation);
        blindEditor.addEventListener('focusin', updateEditDoneBarVisibility);
        blindEditor.addEventListener('focusout', () => {
            window.setTimeout(updateEditDoneBarVisibility, 0);
        });
        blindEditor.addEventListener('keyup', (event) => {
            if (event.target instanceof HTMLInputElement) {
                normalizeBlindEditorInput(event.target);
                refreshEditorValidation();
            }
        });
        blindEditor.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' && event.target instanceof HTMLInputElement) {
                normalizeBlindEditorInput(event.target);
                event.target.blur();
            }
        });
    }
    if (commitFieldBtn) {
        commitFieldBtn.addEventListener('click', () => {
            commitActiveBlindEditorField();
            window.requestAnimationFrame(() => {
                updateBlindEditorValidation();
                updateEditDoneBarVisibility();
            });
        });
    }
    if (editPanel) {
        editPanel.addEventListener('touchend', (event) => {
            if (!(event.target instanceof HTMLElement)) return;
            if (event.target.closest('.blind-row input, .row-actions, .edit-actions, #addLevelBtn, #commitFieldBtn')) return;

            commitActiveBlindEditorField();
            window.requestAnimationFrame(() => {
                updateBlindEditorValidation();
                updateEditDoneBarVisibility();
            });
        }, { passive: true });
    }
    
    if (saveEditBtn) {
        saveEditBtn.addEventListener('click', () => {
            commitActiveBlindEditorField();

            window.requestAnimationFrame(() => {
                const validationResult = updateBlindEditorValidation();
                const newStructure = getEditedStructureFromEditor();

                if (validationResult.valid && validateStructure(newStructure)) {
                    applyEditedStructure(newStructure);
                } else if (validationResult && Number.isInteger(validationResult.rowIndex)) {
                    const invalidRow = document.querySelectorAll('.blind-row')[validationResult.rowIndex];
                    const invalidField = invalidRow ? invalidRow.querySelector(`.${validationResult.field}`) : null;
                    if (invalidField) {
                        invalidField.focus();
                    }
                }
            });
        });
    }
    
    if (cancelEditBtn) cancelEditBtn.addEventListener('click', hideEditPanel);

    if (typeof responsiveVoice !== 'undefined') {
        responsiveVoiceName = getPreferredResponsiveVoice();
        try {
            responsiveVoice.setDefaultVoice(responsiveVoiceName);
        } catch (error) {
            console.log('ResponsiveVoice init error:', error);
        }
    }

    if ('speechSynthesis' in window) {
        window.speechSynthesis.onvoiceschanged = () => {
            speechVoice = getPreferredSpeechVoice();
        };
        speechVoice = getPreferredSpeechVoice();
    }

    // Initialiser l'audio et déverrouiller la voix au premier geste utilisateur, surtout sur iPhone/Safari
    const unlockAudioAndSpeech = () => {
        initAudio();
        if (typeof responsiveVoice !== 'undefined') {
            try {
                responsiveVoiceName = getPreferredResponsiveVoice();
                responsiveVoice.setDefaultVoice(responsiveVoiceName);
            } catch (error) {
                console.log('ResponsiveVoice unlock error:', error);
            }
        }
        unlockSpeechSynthesis();
    };
    document.addEventListener('touchstart', unlockAudioAndSpeech, { once: true, passive: true });
    document.addEventListener('click', unlockAudioAndSpeech, { once: true });

    // Load saved cardevent state when page loads
    loadTimerState();
    
    // Add window event listeners for visibility changes
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            loadTimerState();
            if (isRunning) requestWakeLock();
        } else {
            saveTimerState();
        }
    });

    window.addEventListener('pagehide', saveTimerState);

    // Initialize WebSocket connection
    initWebSocket();

    // Level change buttons
    const prevLevelBtn = document.getElementById('prevLevelBtn');
    const nextLevelBtn = document.getElementById('nextLevelBtn');
    
    if (prevLevelBtn) prevLevelBtn.addEventListener('click', () => changeLevel(-1));
    if (nextLevelBtn) nextLevelBtn.addEventListener('click', () => changeLevel(1));

    const soundToggle = document.getElementById('soundToggle');
    if (soundToggle) {
        soundToggle.addEventListener('click', () => {
            const muted = soundToggle.classList.toggle('muted');
            soundToggle.innerHTML = muted
                ? '<span class="icon-svg"><svg viewBox="0 0 24 24"><path d="M14 5l-5 4H5v6h4l5 4V5z"></path><path d="M19 9l-8 8"></path><path d="M11 9l8 8"></path></svg></span>'
                : '<span class="icon-svg"><svg viewBox="0 0 24 24"><path d="M14 5l-5 4H5v6h4l5 4V5z"></path><path d="M18 9.5a4 4 0 0 1 0 5"></path><path d="M20.5 7a7.5 7.5 0 0 1 0 10"></path></svg></span>';
            document.querySelectorAll('audio').forEach((audio) => {
                audio.muted = muted;
            });
        });
    }
    
    // Update initial state of buttons
    updateLevelButtons();
});

// Also make sure this function is defined at the top level of your script
function updateDisplay() {
    const minutes = Math.floor(Math.max(0, timeLeft) / 60);
    const seconds = Math.max(0, timeLeft) % 60;
    const timerDisplay = document.getElementById('cardevent');
    if (timerDisplay) {
        timerDisplay.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
    }
    
    const currentBlinds = blindLevels[currentLevel];
    const levelElement = document.getElementById('level');
    const blindsElement = document.getElementById('blinds');
    const nextBlindElement = document.getElementById('next-blind');
    
    if (levelElement) levelElement.textContent = currentBlinds.level;
    const levelTotalElement = document.getElementById('level-total');
    if (levelTotalElement) levelTotalElement.textContent = blindLevels.length;
    if (blindsElement) blindsElement.textContent = `${currentBlinds.small_blind}/${currentBlinds.big_blind}`;
    
    if (nextBlindElement) {
        if (currentLevel < blindLevels.length - 1) {
            const nextBlinds = blindLevels[currentLevel + 1];
            nextBlindElement.textContent = `${nextBlinds.small_blind}/${nextBlinds.big_blind}`;
        } else {
            nextBlindElement.textContent = 'Tournament End';
        }
    }

    // Update minute adjustment buttons state
    const minusMinBtn = document.getElementById('minusMinBtn');
    const plusMinBtn = document.getElementById('plusMinBtn');
    if (minusMinBtn) minusMinBtn.disabled = isRunning;
    if (plusMinBtn) plusMinBtn.disabled = isRunning;

    const timerRing = document.getElementById('timer-ring');
    if (timerRing && currentBlinds && currentBlinds.duration) {
        const progress = Math.max(0, Math.min(1, (currentBlinds.duration - timeLeft) / currentBlinds.duration));
        timerRing.style.setProperty('--progress', progress.toFixed(4));
    }

    const pauseInfo = document.getElementById('pause-info');
    if (pauseInfo) {
        let remaining = 0;
        let foundPause = false;
        for (let index = currentLevel; index < blindLevels.length; index++) {
            const level = blindLevels[index];
            if (index === currentLevel) {
                remaining += timeLeft;
            } else {
                remaining += level.duration;
            }
            if ((level.small_blind === 0 && level.big_blind === 0) && index >= currentLevel) {
                foundPause = true;
                break;
            }
        }
        if (foundPause) {
            const hours = Math.floor(remaining / 3600);
            const mins = Math.floor((remaining % 3600) / 60);
            pauseInfo.textContent = `Pause dans ${hours > 0 ? hours + 'h ' : ''}${mins}m`;
        } else {
            pauseInfo.textContent = 'Pas de pause prévue';
        }
    }
}

function hideEditPanel() {
    const editPanel = document.getElementById('editPanel');
    if (editPanel) {
        editPanel.style.display = 'none';
    }
    updateEditDoneBarVisibility();
}

function showEditPanel() {
    const editPanel = document.getElementById('editPanel');
    if (editPanel) {
        renderBlindEditor();
        editPanel.style.display = 'block';
    }
    updateEditDoneBarVisibility();
}

function updateClock() {
    const now = new Date();
    const hours = now.getHours().toString().padStart(2, '0');
    const minutes = now.getMinutes().toString().padStart(2, '0');
    document.getElementById('clock').textContent = `${hours}:${minutes}`;

    const dateLabel = document.getElementById('timer-date-label');
    if (dateLabel) {
        const formatted = new Intl.DateTimeFormat('fr-FR', { weekday: 'long', day: 'numeric', month: 'numeric' }).format(now);
        dateLabel.textContent = formatted.charAt(0).toUpperCase() + formatted.slice(1);
    }
}

setInterval(updateClock, 1000);
updateClock(); // Exécution immédiate
    </script>
</body>
</html>