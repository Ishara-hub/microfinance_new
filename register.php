<?php
include "db.php";
require 'vendor/autoload.php';

// Set page title
$page_title = "User management";
// Include header
include 'header.php';

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Add new user
    if (isset($_POST['add_user'])) {
        $username = $_POST['username'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $role = $_POST['role'];
        $status = 'active'; // New users are active by default

        $query = "INSERT INTO users (username, password, role, status) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssss", $username, $password, $role, $status);

        if ($stmt->execute()) {
            $_SESSION['message'] = "✅ User added successfully!";
        } else {
            $_SESSION['message'] = "❌ Error: " . $stmt->error;
        }
        header("Location: user_management.php");
        exit();
    }
    
    // Update user
    if (isset($_POST['update_user'])) {
        $id = $_POST['user_id'];
        $username = $_POST['username'];
        $role = $_POST['role'];
        $status = $_POST['status'];
        
        // Check if password is being updated
        if (!empty($_POST['password'])) {
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $query = "UPDATE users SET username=?, password=?, role=?, status=? WHERE id=?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ssssi", $username, $password, $role, $status, $id);
        } else {
            $query = "UPDATE users SET username=?, role=?, status=? WHERE id=?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("sssi", $username, $role, $status, $id);
        }

        if ($stmt->execute()) {
            $_SESSION['message'] = "✅ User updated successfully!";
        } else {
            $_SESSION['message'] = "❌ Error: " . $stmt->error;
        }
        header("Location: user_management.php");
        exit();
    }
}

// Handle status toggle
if (isset($_GET['toggle_status'])) {
    $id = $_GET['toggle_status'];
    
    // Get current status
    $query = "SELECT status FROM users WHERE id=?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    $new_status = ($user['status'] == 'active') ? 'inactive' : 'active';
    
    $query = "UPDATE users SET status=? WHERE id=?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("si", $new_status, $id);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = "✅ User status updated successfully!";
    } else {
        $_SESSION['message'] = "❌ Error: " . $stmt->error;
    }
    header("Location: user_management.php");
    exit();
}

// Fetch all users
$users = [];
$query = "SELECT id, username, role, status FROM users ORDER BY status DESC, role, username";
$result = $conn->query($query);
if ($result) {
    $users = $result->fetch_all(MYSQLI_ASSOC);
}

// Fetch user for editing
$edit_user = null;
if (isset($_GET['edit'])) {
    $id = $_GET['edit'];
    $query = "SELECT id, username, role, status FROM users WHERE id=?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_user = $result->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>User Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .inactive-user {
            opacity: 0.7;
            background-color: #f8f9fa;
        }
        .status-toggle {
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-info">
                <?= $_SESSION['message']; ?>
                <?php unset($_SESSION['message']); ?>
            </div>
        <?php endif; ?>
        
        <h2 class="mb-4">User Management</h2>
        
        <!-- Add/Edit User Form -->
        <div class="card mb-4">
            <div class="card-header">
                <?= $edit_user ? 'Edit User' : 'Add New User' ?>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?php if ($edit_user): ?>
                        <input type="hidden" name="user_id" value="<?= $edit_user['id'] ?>">
                        <input type="hidden" name="update_user" value="1">
                    <?php else: ?>
                        <input type="hidden" name="add_user" value="1">
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" 
                               value="<?= $edit_user ? $edit_user['username'] : '' ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" 
                               <?= $edit_user ? 'placeholder="Leave blank to keep current password"' : 'required' ?>>
                        <?php if ($edit_user): ?>
                            <small class="text-muted">Only enter a password if you want to change it</small>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select name="role" class="form-control" required>
                            <option value="admin" <?= ($edit_user && $edit_user['role'] == 'admin') ? 'selected' : '' ?>>Admin</option>
                            <option value="customer" <?= ($edit_user && $user['role'] == 'customer') ? 'selected' : '' ?>>Customer</option>
                            <option value="credit_officer" <?= ($edit_user && $user['role'] == 'credit_officer') ? 'selected' : '' ?>>Credit Officer</option>
                        </select>
                    </div>
                    
                    <?php if ($edit_user): ?>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control" required>
                            <option value="active" <?= ($edit_user && $edit_user['status'] == 'active') ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= ($edit_user && $edit_user['status'] == 'inactive') ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <button type="submit" class="btn btn-primary">
                        <?= $edit_user ? 'Update User' : 'Add User' ?>
                    </button>
                    
                    <?php if ($edit_user): ?>
                        <a href="user_management.php" class="btn btn-secondary">Cancel</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        
        <!-- Users List -->
        <div class="card">
            <div class="card-header">
                User List
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr class="<?= $user['status'] == 'inactive' ? 'inactive-user' : '' ?>">
                                    <td><?= $user['id'] ?></td>
                                    <td><?= htmlspecialchars($user['username']) ?></td>
                                    <td>
                                        <?php 
                                            $badge_class = '';
                                            switch ($user['role']) {
                                                case 'admin': $badge_class = 'bg-danger'; break;
                                                case 'credit_officer': $badge_class = 'bg-warning text-dark'; break;
                                                default: $badge_class = 'bg-primary';
                                            }
                                        ?>
                                        <span class="badge <?= $badge_class ?>">
                                            <?= ucfirst(str_replace('_', ' ', $user['role'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-toggle badge bg-<?= $user['status'] == 'active' ? 'success' : 'secondary' ?>" 
                                              onclick="window.location.href='user_management.php?toggle_status=<?= $user['id'] ?>'">
                                            <?= ucfirst($user['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="user_management.php?edit=<?= $user['id'] ?>" class="btn btn-sm btn-primary">
                                            <i class="bi bi-pencil"></i> Edit
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>