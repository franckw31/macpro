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
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.9);
            padding: 20px;
            z-index: 1000;
            overflow-y: auto;
            overflow-x: hidden;
        }

        .edit-content, .load-content {
            background: #1E1E1E;
            padding: 20px;
            border-radius: 15px;
            max-width: 600px;
            margin: 20px auto;
            box-shadow: 0 4px 8px rgba(0,0,0,0.4);
            width: calc(100% - 40px);
            box-sizing: border-box;
        }

    /* Blind Editor */
    .blind-headers {
        display: grid;
        grid-template-columns: repeat(4, minmax(80px, 1fr));
        gap: 8px;
        margin: 8px 0;
        font-weight: bold;
        color: #90CAF9;
        padding: 0 8px;
        font-size: 14px;
    }

    .blind-editor {
        margin: 20px 0;
        padding: 15px;
        background: rgba(30, 30, 30, 0.9);
        border-radius: 10px;
        border: 1px solid rgba(255,255,255,0.1);
        box-shadow: 0 4px 8px rgba(0,0,0,0.3);
    }

    .blind-row {
        display: grid;
        grid-template-columns: repeat(4, minmax(80px, 1fr));
        gap: 8px;
        margin: 6px 0;
        padding: 8px;
        position: relative;
        align-items: center;
        background: rgba(255,255,255,0.05);
        border-radius: 6px;
        transition: all 0.2s ease;
        width: 100%;
        box-sizing: border-box;
    }

    .blind-row:hover {
        background: rgba(255,255,255,0.08);
    }

    .blind-row input {
        width: 100%;
        padding: 8px;
        background: rgba(0,0,0,0.3);
        border: 1px solid rgba(255,255,255,0.1);
        border-radius: 6px;
        color: white;
        font-size: 14px;
        box-sizing: border-box;
        -webkit-appearance: none;
        margin: 0;
        -moz-appearance: textfield;
        transition: all 0.2s ease;
    }

    .blind-row input:focus {
        outline: none;
        border-color: #2196F3;
        box-shadow: 0 0 0 2px rgba(33,150,243,0.3);
        background: rgba(0,0,0,0.5);
    }

    .blind-row input:focus {
        outline: none;
        border-color: #2196F3;
        box-shadow: 0 0 0 2px rgba(33,150,243,0.2);
    }

    .blind-row.highlighted {
        background-color: rgba(33, 150, 243, 0.2);
        border-radius: 4px;
        box-shadow: 0 0 0 2px rgba(33,150,243,0.5);
    }

    /* Remove Button */
        .row-actions {
            display: flex;
            gap: 5px;
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
        }

    .insert-btn, .remove-btn {
        width: 32px;
        height: 32px;
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-size: 18px;
        font-weight: bold;
        transition: all 0.3s ease;
        min-width: 44px;
        min-height: 44px;
        border: none;
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    }

    .insert-btn:hover {
        transform: scale(1.1);
        box-shadow: 0 4px 8px rgba(0,0,0,0.3);
    }

    .remove-btn:hover {
        transform: scale(1.1);
        box-shadow: 0 4px 8px rgba(0,0,0,0.3);
    }

    .insert-btn {
        background-color: #4CAF50;
    }

    .insert-btn:hover {
        background-color: #388E3C;
    }

    .remove-btn {
        background-color: #F44336;
    }

    .remove-btn:hover {
        background: #D32F2F;
        transform: scale(1.1);
    }

    .insert-btn {
        background-color: #4CAF50;
        width: 32px;
        height: 32px;
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-size: 18px;
        font-weight: bold;
        transition: all 0.3s ease;
        min-width: 44px;
        min-height: 44px;
        border: none;
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    }

    .insert-btn:hover {
        background-color: #388E3C;
        transform: scale(1.1);
        box-shadow: 0 4px 8px rgba(0,0,0,0.3);
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

        .blind-row {
            grid-template-columns: 1fr;
        }

        .remove-btn {
            right: 0;
            top: 50%;
            transform: translateY(-50%);
        }

        .edit-content, .load-content {
            margin: 10px;
            padding: 15px;
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

    .edit-panel {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.9);
        padding: 20px;
        z-index: 1000;
    }

    .edit-content {
        background: #1E1E1E;
        padding: 20px;
        border-radius: 15px;
        max-width: 600px;
        margin: 20px auto;
        max-height: 90vh;
        overflow-y: auto;
    }

    .blind-editor {
        margin: 20px 0;
    }

    .blind-row {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 10px;
        margin: 10px 0;
        position: relative;
    }

    .blind-row input {
        width: 100%;
        padding: 8px;
        background: #333;
        border: 1px solid #555;
        border-radius: 4px;
        color: white;
    }

    .remove-btn {
        position: absolute;
        right: -30px;
        top: 50%;
        transform: translateY(-50%);
        width: 24px;
        height: 24px;
        background: #F44336;
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
    }

    .blind-headers {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 10px;
        margin-bottom: 15px;
        color: #90CAF9;
        font-weight: bold;
    }

    .blind-row {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 10px;
        margin: 10px 0;
        position: relative;
    }

    .blind-row input {
        width: 100%;
        padding: 8px;
        background: #333;
        border: 1px solid #555;
        border-radius: 4px;
        color: white;
    }

    .remove-btn {
        position: absolute;
        right: -30px;
        top: 50%;
        transform: translateY(-50%);
        width: 24px;
        height: 24px;
        background: #F44336;
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
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
    </style>
</head>
<body>
    <div class="container">
        <div class="blind-info" style="display: flex; align-items: center; gap: 10px;">
            <button class="level-btn" id="prevLevelBtn">-</button>
            N<span id="level">1</span>
            <button class="level-btn" id="nextLevelBtn">+</button>
        </div>
        
        <div class="cardevent-display" id="cardevent">15:00</div>
        
        <div class="blind-info"><span id="blinds">25/50</span></div>
        <div class="blind-info-next">Next: <span id="next-blind">50/100</span></div>
        
        <div class="controls">
            <button class="start-btn" id="startPauseBtn">Start</button>
            <button class="reset-btn" id="resetBtn">Reset</button>
        </div>
        <div class="time-controls">
            <button class="edit-btn" id="minusMinBtn">- 2 Min</button>
            <button class="edit-btn" id="restartBlindsBtn">Restart Blinde</button>
            <button class="edit-btn" id="plusMinBtn">+ 2 Min</button>
        </div>
        
        <div class="structure-controls">
            <button class="edit-btn" id="loadFromDbBtn">Load Structure</button>
            <button class="edit-btn" id="editBtn">Edit Blinds</button>
            <button class="edit-btn" id="saveToDbBtn">Save Structure</button>
        </div>
    </div>

    <div class="edit-panel" id="editPanel">
    <div class="edit-content">
        <h2 style="color: #90CAF9;">Edit Blind Structure</h2>
        <div class="blind-editor" id="blindEditor"></div>
        <button class="edit-btn" id="addLevelBtn">+ Add Level</button>
        <div class="edit-actions">
            <button class="start-btn" id="saveEditBtn">Save Changes</button>
            <button class="reset-btn" id="cancelEditBtn">Cancel</button>
        </div>
    </div>
</div>

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

    <div style="position: fixed; top: 10px; right: 10px; font-size: 48px; color: white; text-shadow: 2px 2px 4px rgba(0,0,0,0.5);" id="clock"></div>

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
                console.log('Disconnected from WebSocket server');
                // Try to reconnect in 5 seconds
                setTimeout(initWebSocket, 5000);
            };
        }

        function syncTimerState(state) {
            if (!isLocalUpdate) {
                clearInterval(timerInterval);
                currentLevel = state.currentLevel;
                timeLeft = state.timeLeft;
                isRunning = state.isRunning;
                
                if (isRunning) {
                    startTimer(false); // false means don't broadcast
                }
                
                updateDisplay();
            }
            isLocalUpdate = false;
        }

        function saveTimerState() {
            if (ws && ws.readyState === WebSocket.OPEN) {
                isLocalUpdate = true;
                ws.send(JSON.stringify({
                    type: 'update',
                    data: {
                        currentLevel,
                        timeLeft,
                        isRunning,
                        blindLevels
                    }
                }));
            }
        }

        function startTimer(broadcast = true) {
            isRunning = true;
            updateButtonStates();
            
            clearInterval(timerInterval);
            timerInterval = setInterval(() => {
                if (timeLeft > 0) {
                    timeLeft--;
                    updateDisplay();
                    if (broadcast) saveTimerState();
                    if (timeLeft === 30) {
                        playSound('endSound');
                    }
                } else {
                    handleLevelEnd();
                }
            }, 1000);
            
            if (broadcast) saveTimerState();
        }

        function stopTimer() {
            clearInterval(timerInterval);
            timerInterval = null;
            isRunning = false;
            const startPauseBtn = document.getElementById('startPauseBtn');
            startPauseBtn.textContent = 'Start';
            startPauseBtn.className = 'start-btn';
            
            // Enable minute adjustment buttons
            document.getElementById('minusMinBtn').disabled = false;
            document.getElementById('plusMinBtn').disabled = false;
            
            // Enable level change buttons when cardevent is stopped
            updateLevelButtons();
        }

        function updateButtonStates() {
            const startPauseBtn = document.getElementById('startPauseBtn');
            if (startPauseBtn) {
                startPauseBtn.textContent = isRunning ? 'Pause' : 'Start';
                startPauseBtn.className = isRunning ? 'pause-btn' : 'start-btn';
            }
        }

        // Timer functions
        function updateDisplay() {
            const minutes = Math.floor(Math.max(0, timeLeft) / 60);
            const seconds = Math.max(0, timeLeft) % 60;
            document.getElementById('cardevent').textContent = 
                `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            
            const currentBlinds = blindLevels[currentLevel];
            document.getElementById('level').textContent = currentBlinds.level;
            document.getElementById('blinds').textContent = 
                `${currentBlinds.small_blind}/${currentBlinds.big_blind}`;
            document.getElementById('ante').textContent = currentBlinds.ante;
            
            if (currentLevel < blindLevels.length - 1) {
                const nextBlinds = blindLevels[currentLevel + 1];
                document.getElementById('next-blind').textContent = 
                    `${nextBlinds.small_blind}/${nextBlinds.big_blind}`;
            } else {
                document.getElementById('next-blind').textContent = 'Tournament End';
            }

            // Update minute adjustment buttons state
            document.getElementById('minusMinBtn').disabled = isRunning;
            document.getElementById('plusMinBtn').disabled = isRunning;

            updateLevelButtons();
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

// Add these functions to your JavaScript code
function saveTimerState() {
    const timerState = {
        currentLevel: currentLevel,
        timeLeft: timeLeft,
        isRunning: isRunning,
        lastUpdate: Date.now()
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
        const timePassed = Math.floor((Date.now() - state.lastUpdate) / 1000);
        
        currentLevel = state.currentLevel;
        
        if (state.isRunning) {
            timeLeft = Math.max(0, state.timeLeft - timePassed);
            if (timeLeft > 0) {
                toggleStartPause(); // Start the cardevent
            }
        } else {
            timeLeft = state.timeLeft;
        }
        
        updateDisplay();
    }
}

// Modify your toggleStartPause function to save state
function toggleStartPause() {
    if (isRunning) {
        stopTimer();
    } else {
        startTimer();
    }
    updateLevelButtons();
    saveTimerState();
}

function handleLevelEnd() {
    if (currentLevel < blindLevels.length - 1) {
        currentLevel++;
        timeLeft = blindLevels[currentLevel].duration;
        updateDisplay();
        playSound('levelSound'); // Garder le son uniquement pour le changement de niveau
    } else {
        stopTimer();
        playSound('endSound'); // Garder le son pour la fin du tournoi
        alert('Tournament finished!');
    }
}

function initWebSocket() {
    ws = new WebSocket('ws://your-server:8080');
    
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
        isRunning = state.isRunning;
        
        // Update UI
        updateDisplay();
        
        // Update cardevent state
        if (isRunning && !timerInterval) {
            startTimer();
        } else if (!isRunning && timerInterval) {
            stopTimer();
        }
    }
}

        // Ajouter cette fonction pour basculer l'état et l'apparence du bouton
        function toggleStartPause() {
            const startPauseBtn = document.getElementById('startPauseBtn');
            if (isRunning) {
                stopTimer();
            } else {
                startTimer();
            }
            updateLevelButtons();
            saveTimerState();
        }

        // Modifier la fonction resetTimer pour mettre à jour le bouton
        function resetTimer() {
            if (confirm("Êtes-vous sûr de vouloir réinitialiser le cardevent ?")) {
                stopTimer();
                currentLevel = 0;
                timeLeft = blindLevels[0].duration;
                updateDisplay();
                
                // Reset button state
                const startPauseBtn = document.getElementById('startPauseBtn');
                if (startPauseBtn) {
                    startPauseBtn.textContent = 'Start';
                    startPauseBtn.className = 'start-btn';
                }
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
            // Garde le niveau actuel mais réinitialise le temps
            timeLeft = blindLevels[currentLevel].duration;
            updateDisplay();
        }

        function changeLevel(direction) {
            if (!isRunning) {
                const newLevel = currentLevel + direction;
                if (newLevel >= 0 && newLevel < blindLevels.length) {
                    currentLevel = newLevel;
                    timeLeft = blindLevels[currentLevel].duration;
                    updateDisplay();
                    updateLevelButtons();
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

        // Structure management functions
        function showEditPanel() {
    const editPanel = document.getElementById('editPanel');
    if (editPanel) {
        renderBlindEditor();
        editPanel.style.display = 'block';
    }
}

function renderBlindEditor() {
    const blindEditor = document.getElementById('blindEditor');
    if (!blindEditor) return;

    // Add headers
    const headers = `
        <div class="blind-headers">
            <div>Small Blind</div>
            <div>Big Blind</div>
            <div>Ante</div>
            <div>Duration (min)</div>
        </div>
    `;

    const rows = blindLevels.map((level, index) => `
                <div class="blind-row" data-level="${index + 1}">
                    <input type="number" value="${level.small_blind}" min="0" step="25" class="small-blind">
                    <input type="number" value="${level.big_blind}" min="0" step="50" class="big-blind">
                    <input type="number" value="${level.ante}" min="0" step="25" class="ante">
                    <input type="number" value="${level.duration / 60}" min="1" max="60" class="duration">
                    <div class="row-actions">
                        <button class="insert-btn" onclick="insertLevelAt(${index})">+</button>
                        ${index > 0 ? `<button class="remove-btn" onclick="removeLevel(${index})">×</button>` : ''}
                    </div>
                </div>
    `).join('');

    blindEditor.innerHTML = headers + rows;
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
    
    if (editBtn) editBtn.addEventListener('click', showEditPanel);
    if (saveToDbBtn) saveToDbBtn.addEventListener('click', saveToDatabase);
    if (loadFromDbBtn) loadFromDbBtn.addEventListener('click', showLoadPanel);
    if (closeLoadBtn) closeLoadBtn.addEventListener('click', () => {
        document.getElementById('loadPanel').style.display = 'none';
    });
    if (addLevelBtn) addLevelBtn.addEventListener('click', addLevel);
    
    if (saveEditBtn) {
        saveEditBtn.addEventListener('click', () => {
            const rows = document.querySelectorAll('.blind-row');
            const newStructure = Array.from(rows).map((row, index) => {
                const smallBlind = parseInt(row.querySelector('.small-blind').value) || 0;
                const bigBlind = parseInt(row.querySelector('.big-blind').value) || 0;
                const ante = parseInt(row.querySelector('.ante').value) || 0;
                const duration = (parseInt(row.querySelector('.duration').value) || 15) * 60;

                return {
                    level: index + 1,
                    small_blind: smallBlind,
                    big_blind: bigBlind,
                    ante: ante,
                    duration: duration
                };
            });

            if (validateStructure(newStructure)) {
                blindLevels = newStructure;
                currentLevel = 0;
                timeLeft = blindLevels[0].duration;
                updateDisplay();
                hideEditPanel();
            }
        });
    }
    
    if (cancelEditBtn) cancelEditBtn.addEventListener('click', hideEditPanel);

    // Initialiser l'audio uniquement pour les sons de niveau et de fin
    document.addEventListener('click', () => {
        const levelSound = document.getElementById('levelSound');
        const endSound = document.getElementById('endSound');
        if (levelSound) levelSound.load();
        if (endSound) endSound.load();
    }, { once: true });

    // Load saved cardevent state when page loads
    loadTimerState();
    
    // Add window event listeners for visibility changes
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            loadTimerState(); // Refresh cardevent state when tab becomes visible
        }
    });

    // Initialize WebSocket connection
    initWebSocket();

    // Level change buttons
    const prevLevelBtn = document.getElementById('prevLevelBtn');
    const nextLevelBtn = document.getElementById('nextLevelBtn');
    
    if (prevLevelBtn) prevLevelBtn.addEventListener('click', () => changeLevel(-1));
    if (nextLevelBtn) nextLevelBtn.addEventListener('click', () => changeLevel(1));
    
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

    updateLevelButtons();
}

function validateStructure(structure) {
    if (!Array.isArray(structure) || structure.length === 0) {
        alert('Invalid structure format');
        return false;
    }
    return true;
}

function hideEditPanel() {
    const editPanel = document.getElementById('editPanel');
    if (editPanel) {
        editPanel.style.display = 'none';
    }
}

function showEditPanel() {
    const editPanel = document.getElementById('editPanel');
    if (editPanel) {
        renderBlindEditor();
        editPanel.style.display = 'block';
    }
}

        function renderBlindEditor() {
            const blindEditor = document.getElementById('blindEditor');
            if (!blindEditor) return;

            // Add headers
            const headers = `
                <div class="blind-headers">
                    <div>Small Blind</div>
                    <div>Big Blind</div>
                    <div>Ante</div>
                    <div>Duration (min)</div>
                </div>
            `;

            const rows = blindLevels.map((level, index) => `
                <div class="blind-row" data-level="${index + 1}">
                    <input type="number" value="${level.small_blind}" min="0" step="25" class="small-blind">
                    <input type="number" value="${level.big_blind}" min="0" step="50" class="big-blind">
                    <input type="number" value="${level.ante}" min="0" step="25" class="ante">
                    <input type="number" value="${level.duration / 60}" min="1" max="60" class="duration">
                    <div class="row-actions">
                        <button class="insert-btn" onclick="insertLevelAt(${index})">+</button>
                        ${index > 0 ? `<button class="remove-btn" onclick="removeLevel(${index})">×</button>` : ''}
                    </div>
                </div>
            `).join('');

            blindEditor.innerHTML = headers + rows;
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
                level: index + 1,
                small_blind: smallBlind,
                big_blind: bigBlind,
                ante: prevLevel ? prevLevel.ante : 0,
                duration: prevLevel ? prevLevel.duration : 900
            };

            blindLevels.splice(index + 1, 0, newLevel);
            blindLevels.forEach((level, i) => level.level = i + 1);
            renderBlindEditor();
        }

document.addEventListener('DOMContentLoaded', () => {
    // ... existing code ...

    // Time adjustment buttons
    const minusMinBtn = document.getElementById('minusMinBtn');
    const plusMinBtn = document.getElementById('plusMinBtn');
    const restartBlindsBtn = document.getElementById('restartBlindsBtn');
    
    


    if (minusMinBtn) minusMinBtn.addEventListener('click', () => adjustTime(-1));
    if (plusMinBtn) plusMinBtn.addEventListener('click', () => adjustTime(1));
    if (restartBlindsBtn) restartBlindsBtn.addEventListener('click', restartBlinds);

    // ... rest of your existing code ...
});

function updateClock() {
    const now = new Date();
    const hours = now.getHours().toString().padStart(2, '0');
    const minutes = now.getMinutes().toString().padStart(2, '0');
    const seconds = now.getSeconds().toString().padStart(2, '0');
    document.getElementById('clock').textContent = `${hours}:${minutes}:${seconds}`;
}

setInterval(updateClock, 1000);
updateClock(); // Exécution immédiate
    </script>
    <footer style="position: fixed; bottom: 0; left: 0; right: 0; background: rgba(0,0,0,0.8); padding: 10px; text-align: center; z-index: 1000; display: flex; justify-content: center; gap: 40px;">
        <a href="#" id="footerAccueil" style="color: white; font-weight: bold; text-decoration: none; font-size: 16px;">Accueil</a>
        <a href="#" id="footerTimer" style="color: white; font-weight: bold; text-decoration: none; font-size: 16px;">Local Timer</a>
        <a href="#" id="footerRepartition" style="color: white; font-weight: bold; text-decoration: none; font-size: 16px;" tabindex="0">Répartition</a>
    </footer>
    <script>
    // Affichage dynamique du contenu principal (Raccourcis ou Répartition)
    document.addEventListener('DOMContentLoaded', function() {
        // Affichage contextuel du bloc Répartition du prizepool
        var btnRepartition = document.getElementById('footerRepartition');
        var btnAccueil = document.getElementById('footerAccueil');
        var btnTimer = document.getElementById('footerTimer');
        // Scroll uniquement vers la section Répartition du prizepool sans masquer d'autres blocs
        var btnRepartition = document.getElementById('footerRepartition');
        var btnAccueil = document.getElementById('footerAccueil');
        var btnTimer = document.getElementById('footerTimer');
        var prizepoolSection = document.getElementById('prizepool-section');
        if (btnRepartition && prizepoolSection) {
            btnRepartition.addEventListener('click', function(e) {
                e.preventDefault();
                prizepoolSection.scrollIntoView({behavior: 'smooth', block: 'center'});
            });
        }
        if (btnAccueil) {
            btnAccueil.addEventListener('click', function(e) {
                e.preventDefault();
                window.scrollTo({top: 0, behavior: 'smooth'});
            });
        }
        if (btnTimer) {
            btnTimer.addEventListener('click', function(e) {
                e.preventDefault();
                location.reload();
            });
        }
    });
    </script>
</body>
</html>
