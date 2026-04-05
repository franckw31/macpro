<?php
session_start();
require_once(__DIR__ . '/../config.php');

if (!isset($_SESSION['id'])) {
    die(json_encode(['error' => 'Not logged in']));
}

$current_user_id = $_SESSION['id'];

// Get all members except current user
$sql_all = "SELECT m.`id-membre` as id, m.`pseudo`, m.`photo`, 
        (SELECT COUNT(*) FROM `chat_messages` cm WHERE cm.sender_id = m.`id-membre` AND cm.receiver_id = $current_user_id AND cm.is_read = 0 AND cm.group_id IS NULL) as unread_count
        FROM `membres` m 
        WHERE m.`id-membre` != $current_user_id 
        ORDER BY m.`pseudo` ASC";
$result_all = mysqli_query($conx, $sql_all);

// Get IDs of people the user has chatted with
$sql_friends_ids = "SELECT DISTINCT CASE 
                        WHEN sender_id = $current_user_id THEN receiver_id 
                        ELSE sender_id 
                    END as friend_id 
                    FROM chat_messages 
                    WHERE (sender_id = $current_user_id OR receiver_id = $current_user_id) 
                    AND group_id IS NULL";
$res_friends_ids = mysqli_query($conx, $sql_friends_ids);
$friend_ids = [];
while ($row = mysqli_fetch_assoc($res_friends_ids)) {
    if ($row['friend_id']) $friend_ids[] = $row['friend_id'];
}

$friends = [];
$contacts = [];

while ($row = mysqli_fetch_assoc($result_all)) {
    if (empty($row['photo'])) {
        $row['photo'] = 'man.png';
    }
    
    if (in_array($row['id'], $friend_ids)) {
        $friends[] = $row;
    } else {
        $contacts[] = $row;
    }
}

// Get groups the user belongs to
$sql_groups = "SELECT g.id, g.name, g.created_by, g.activity_id,
               (SELECT COUNT(*) FROM `chat_messages` cm 
                JOIN `chat_group_members` gm2 ON cm.group_id = gm2.group_id
                WHERE cm.group_id = g.id 
                AND gm2.member_id = $current_user_id
                AND cm.sender_id != $current_user_id 
                AND cm.timestamp > gm2.last_read_at) as unread_count
               FROM `chat_groups` g 
               JOIN `chat_group_members` gm ON g.id = gm.group_id 
               WHERE gm.member_id = $current_user_id
               ORDER BY g.id DESC";
// Note: Group unread is harder without a per-user read marker. 
// For now, let's just focus on private messages unread count.
$result_groups = mysqli_query($conx, $sql_groups);

$groups = [];
while ($row = mysqli_fetch_assoc($result_groups)) {
    $groups[] = $row;
}

echo json_encode(['friends' => $friends, 'contacts' => $contacts, 'groups' => $groups]);
?>
