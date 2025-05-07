<?php
include "db.php";
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$notification_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : 'dashboard.php';

if ($notification_id) {
    $query = "UPDATE notifications SET is_read = TRUE 
              WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $notification_id, $user_id);
    $stmt->execute();
    $stmt->close();
}

header("Location: $redirect");
$conn->close();
?>