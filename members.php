<?php
include "db.php";

$page_title = "Client Management";

// Handle search and filter
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';

// Build query with search & filter conditions
$query = "SELECT * FROM members WHERE 1";

if (!empty($search)) {
    $query .= " AND (full_name LIKE '%$search%' OR nic LIKE '%$search%' OR phone LIKE '%$search%' OR address LIKE '%$search%')";
}

if (!empty($status)) {
    $query .= " AND status = '$status'";
}

$result = $conn->query($query);

// Include header
include 'header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Members List</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2>Members List</h2>
        <a href="add_member_new.php" class="btn btn-primary mb-3">Add New Member</a>

        <!-- Search and Filter Form -->
        <form method="GET" class="row g-2 mb-3">
            <div class="col-md-4">
                <input type="text" name="search" class="form-control" placeholder="Search by Name, NIC, Phone..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-3">
                <select name="status" class="form-control">
                    <option value="">All Status</option>
                    <option value="active" <?= $status == 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $status == 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-success">Search</button>
                <a href="members.php" class="btn btn-secondary">Reset</a>
            </div>
        </form>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Full Name</th>
                    <th>NIC</th>
                    <th>Phone</th>
                    <th>Address</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()) { ?>
                    <tr>
                        <td><?= $row['id']; ?></td>
                        <td><?= $row['full_name']; ?></td>
                        <td><?= $row['nic']; ?></td>
                        <td><?= $row['phone']; ?></td>
                        <td><?= $row['address']; ?></td>
                        <td>
                            <?php if ($row['status'] == 'active') { ?>
                                <span class="badge bg-success">Active</span>
                            <?php } else { ?>
                                <span class="badge bg-danger">Inactive</span>
                            <?php } ?>
                        </td>
                        <td>
                            <a href="edit_member.php?id=<?= $row['id']; ?>" class="btn btn-warning btn-sm">Edit</a>
                            <?php if ($row['status'] == 'active') { ?>
                                <a href="inactivate_member.php?id=<?= $row['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to deactivate this member?')">Deactivate</a>
                            <?php } else { ?>
                                <a href="activate_member.php?id=<?= $row['id']; ?>" class="btn btn-success btn-sm">Activate</a>
                            <?php } ?>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</body>
</html>
<?php
// Include footer
include 'footer.php';
?>