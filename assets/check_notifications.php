<?php
include "db.php";
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$query = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = FALSE";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();

header('Content-Type: application/json');
echo json_encode(['count' => (int)$data['count']]);

$stmt->close();
$conn->close();
?>