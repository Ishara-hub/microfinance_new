<?php
include "db.php";

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $query = "UPDATE members SET status='inactive' WHERE id=?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        echo "<script>alert('✅ Member Deactivated Successfully!'); window.location.href='members.php';</script>";
    } else {
        echo "<script>alert('❌ Error: " . $stmt->error . "');</script>";
    }
}
?>
