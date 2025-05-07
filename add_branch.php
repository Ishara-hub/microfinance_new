<?php
include "db.php";
require 'vendor/autoload.php';

// Set page title
$page_title = "Add Branch";
// Include header
include 'header.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $location = $_POST['location'];

    $query = "INSERT INTO branches (name, location) VALUES (?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $name, $location);

    if ($stmt->execute()) {
        echo "<div class='alert alert-success'>✅ Branch Added Successfully!</div>";
    } else {
        echo "<div class='alert alert-danger'>❌ Error: " . $stmt->error . "</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Add Branch</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2>Add New Branch</h2>
        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Branch Name</label>
                <input type="text" name="name" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Location</label>
                <input type="text" name="location" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary">Add Branch</button>
        </form>
    </div>
</body>
</html>
