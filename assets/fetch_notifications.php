<?php
include "db.php";
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$query = "SELECT id, message, link, is_read, created_at FROM notifications 
          WHERE user_id = ? 
          ORDER BY created_at DESC 
          LIMIT 5";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$notifications = [];

while ($row = $result->fetch_assoc()) {
    $notifications[] = [
        'id' => $row['id'],
        'message' => htmlspecialchars($row['message']),
        'link' => $row['link'],
        'is_read' => (bool)$row['is_read'],
        'time' => time_elapsed_string($row['created_at'])
    ];
}

header('Content-Type: application/json');
echo json_encode(['notifications' => $notifications]);

$stmt->close();
$conn->close();

// Helper function to format time
function time_elapsed_string($datetime) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    if ($diff->d > 0) return $diff->d . ' days ago';
    if ($diff->h > 0) return $diff->h . ' hours ago';
    if ($diff->i > 0) return $diff->i . ' minutes ago';
    return 'Just now';
}
?>