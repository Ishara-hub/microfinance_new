<?php
include "db.php";

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $query = "UPDATE branches SET status = 'inactive' WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        header("Location: branches.php");
        exit();
    } else {
        echo "<div class='alert alert-danger'>❌ Error: " . $stmt->error . "</div>";
    }
}
?>
