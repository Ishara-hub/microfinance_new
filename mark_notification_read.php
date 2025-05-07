<?php
include "db.php";
session_start();

if (isset($_GET['id'])) {
    $notification_id = intval($_GET['id']);
    $user_id = $_SESSION['user_id'];
    
    // Verify the notification belongs to the user
    $verify_query = "SELECT id FROM notifications WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($verify_query);
    $stmt->bind_param("ii", $notification_id, $user_id);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        // Mark as read
        $update_query = "UPDATE notifications SET is_read = 1 WHERE id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("i", $notification_id);
        $update_stmt->execute();
    }
    
    // Redirect to the notification link
    header("Location: " . urldecode($_GET['redirect']));
    exit();
}
?>