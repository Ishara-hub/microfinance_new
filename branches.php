<?php
include "db.php";
require 'vendor/autoload.php';

// Set page title
$page_title = "Branches List";
// Include header
include 'header.php';
$result = $conn->query("SELECT * FROM branches");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Branches List</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2>Branches List</h2>
        <a href="add_branch.php" class="btn btn-primary mb-3">Add New Branch</a>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Branch Name</th>
                    <th>Location</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()) { ?>
                    <tr>
                        <td><?= $row['id']; ?></td>
                        <td><?= $row['name']; ?></td>
                        <td><?= $row['location']; ?></td>
                        <td><?= ucfirst($row['status']); ?></td>
                        <td>
                            <?php if ($row['status'] == 'active') { ?>
                                <a href="deactivate_branch.php?id=<?= $row['id']; ?>" class="btn btn-danger btn-sm">Deactivate</a>
                            <?php } else { ?>
                                <span class="text-muted">Inactive</span>
                            <?php } ?>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</body>
</html>
