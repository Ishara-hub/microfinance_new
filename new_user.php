<?php
include "db.php";
require 'vendor/autoload.php';

// Set page title
$page_title = "User Management";
// Include header
include 'header.php';

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Add new user
    if (isset($_POST['add_user'])) {
        $first_name = $_POST['first_name'];
        $last_name = $_POST['last_name'];
        $gender = $_POST['gender'];
        $civil_status = $_POST['civil_status'];
        $date_of_birth = $_POST['date_of_birth'];
        $username = $_POST['username'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $user_type = $_POST['user_type'];
        $address = $_POST['address'];
        $mobile_no = $_POST['mobile_no'];
        $status = 'active';

        $query = "INSERT INTO users (
            first_name, last_name, gender, civil_status, date_of_birth,
            username, password, user_type, address, 
            mobile_no,status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param(
            "sssssssssss", 
            $first_name, $last_name, $gender, $civil_status, $date_of_birth,
            $username, $password, $user_type, $address,
            $mobile_no, $status
        );

        if ($stmt->execute()) {
            $_SESSION['message'] = "✅ User added successfully!";
        } else {
            $_SESSION['message'] = "❌ Error: " . $stmt->error;
        }
        header("Location: new_user.php");
        exit();
    }
    
    // Update user
    if (isset($_POST['update_user'])) {
        $id = $_POST['user_id'];
        $first_name = $_POST['first_name'];
        $last_name = $_POST['last_name'];
        $gender = $_POST['gender'];
        $civil_status = $_POST['civil_status'];
        $date_of_birth = $_POST['date_of_birth'];
        $username = $_POST['username'];
        $user_type = $_POST['user_type'];
        $address = $_POST['address'];
        $mobile_no = $_POST['mobile_no'];
        $status = $_POST['status'];
        
        // Check if password is being updated
        if (!empty($_POST['password'])) {
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $query = "UPDATE users SET 
                first_name=?, last_name=?, gender=?, civil_status=?, date_of_birth=?,
                username=?, password=?, user_type=?, address=?,
                mobile_no=?, status=?
                WHERE id=?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param(
                "sssssssssssi", 
                $first_name, $last_name, $gender, $civil_status, $date_of_birth,
                $username, $password, $user_type, $address,
                $mobile_no, $status, $id
            );
        } else {
            $query = "UPDATE users SET 
                first_name=?, last_name=?, gender=?, civil_status=?, date_of_birth=?,
                username=?, user_type=?, address=?,
                mobile_no=?, status=?
                WHERE id=?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param(
                "ssssssssssssi", 
                $first_name, $last_name, $gender, $civil_status, $date_of_birth,
                $username, $user_type, $address,
                $mobile_no, $status, $id
            );
        }

        if ($stmt->execute()) {
            $_SESSION['message'] = "✅ User updated successfully!";
        } else {
            $_SESSION['message'] = "❌ Error: " . $stmt->error;
        }
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
    header("Location: new_user.php");
    exit();
}

// Fetch all users
$users = [];
$query = "SELECT id, first_name, last_name, mobile_no, username, status 
          FROM users ORDER BY status DESC, first_name, last_name";
$result = $conn->query($query);
if ($result) {
    $users = $result->fetch_all(MYSQLI_ASSOC);
}

// Fetch user for editing
$edit_user = null;
if (isset($_GET['edit'])) {
    $id = $_GET['edit'];
    $query = "SELECT * FROM users WHERE id=?";
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
        .form-section {
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #eee;
        }
        .form-section h5 {
            color: #0d6efd;
            margin-bottom: 1rem;
        }
        .required-field::after {
            content: " *";
            color: red;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-info">
                <?= $_SESSION['message']; ?>
                <?php unset($_SESSION['message']); ?>
            </div>
        <?php endif; ?>
        
        <h2 class="mb-4">User Management</h2>
        
        <!-- Add/Edit User Form -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
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
                    
                    <div class="row">
                        <!-- Personal Information Section -->
                        <div class="col-md-6">
                            <div class="form-section">
                                <h5>Personal Information</h5>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label required-field">First Name</label>
                                        <input type="text" name="first_name" class="form-control" 
                                               value="<?= $edit_user ? $edit_user['first_name'] : '' ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label required-field">Last Name</label>
                                        <input type="text" name="last_name" class="form-control" 
                                               value="<?= $edit_user ? $edit_user['last_name'] : '' ?>" required>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label required-field">Gender</label>
                                        <select name="gender" class="form-control" required>
                                            <option value="male" <?= ($edit_user && $edit_user['gender'] == 'male') ? 'selected' : '' ?>>Male</option>
                                            <option value="female" <?= ($edit_user && $edit_user['gender'] == 'female') ? 'selected' : '' ?>>Female</option>
                                            <option value="other" <?= ($edit_user && $edit_user['gender'] == 'other') ? 'selected' : '' ?>>Other</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label required-field">Civil Status</label>
                                        <select name="civil_status" class="form-control" required>
                                            <option value="single" <?= ($edit_user && $edit_user['civil_status'] == 'single') ? 'selected' : '' ?>>Single</option>
                                            <option value="married" <?= ($edit_user && $edit_user['civil_status'] == 'married') ? 'selected' : '' ?>>Married</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Date of Birth</label>
                                        <input type="date" name="date_of_birth" class="form-control" 
                                               value="<?= $edit_user ? $edit_user['date_of_birth'] : '' ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label required-field">Mobile No</label>
                                        <input type="tel" name="mobile_no" class="form-control" 
                                               value="<?= $edit_user ? $edit_user['mobile_no'] : '' ?>" required>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Address</label>
                                    <input type="text" name="address" class="form-control" 
                                           value="<?= $edit_user ? $edit_user['address'] : '' ?>">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Account Information Section -->
                        <div class="col-md-6">
                            <div class="form-section">
                                <h5>Account Information</h5>
                                
                                <div class="mb-3">
                                    <label class="form-label required-field">User Type</label>
                                    <select name="user_type" class="form-control" required>
                                        <option value="admin" <?= ($edit_user && $edit_user['user_type'] == 'admin') ? 'selected' : '' ?>>Admin</option>
                                        <option value="credit_officer" <?= ($edit_user && $edit_user['user_type'] == 'credit_officer') ? 'selected' : '' ?>>Credit Officer</option>
                                        <option value="staff" <?= ($edit_user && $edit_user['user_type'] == 'staff') ? 'selected' : '' ?>>Staff</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label required-field">Username</label>
                                    <input type="text" name="username" class="form-control" 
                                           value="<?= $edit_user ? $edit_user['username'] : '' ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label <?= !$edit_user ? 'required-field' : '' ?>">Password</label>
                                    <input type="password" name="password" class="form-control" 
                                           <?= !$edit_user ? 'required' : 'placeholder="Leave blank to keep current password"' ?>>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($edit_user): ?>
                    <div class="form-section">
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-control">
                                <option value="active" <?= ($edit_user && $edit_user['status'] == 'active') ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= ($edit_user && $edit_user['status'] == 'inactive') ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="d-flex justify-content-between">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> <?= $edit_user ? 'Update User' : 'Save User' ?>
                        </button>
                        
                        <?php if ($edit_user): ?>
                            <a href="new_user.php" class="btn btn-secondary">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Users List -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                User List
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>No</th>
                                <th>Name</th>
                                <th>Phone No</th>
                                <th>Username</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $index => $user): ?>
                                <tr class="<?= $user['status'] == 'inactive' ? 'inactive-user' : '' ?>">
                                    <td><?= $index + 1 ?></td>
                                    <td><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></td>
                                    <td><?= htmlspecialchars($user['mobile_no']) ?></td>
                                    <td><?= htmlspecialchars($user['username']) ?></td>
                                    <td>
                                        <span class="status-toggle badge bg-<?= $user['status'] == 'active' ? 'success' : 'secondary' ?>" 
                                              onclick="window.location.href='new_user.php?toggle_status=<?= $user['id'] ?>'">
                                            <?= ucfirst($user['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="new_user.php?edit=<?= $user['id'] ?>" class="btn btn-sm btn-primary">
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
    <script>
        // Add confirmation for status toggle
        document.querySelectorAll('.status-toggle').forEach(toggle => {
            toggle.addEventListener('click', function(e) {
                if (!confirm('Are you sure you want to change this user\'s status?')) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>