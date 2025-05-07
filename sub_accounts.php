<?php
// Start session at the very beginning
session_start();

include "db.php";

// Validate account_id before including header
$account_id = $_GET['account_id'] ?? 0;

if (!$account_id || !is_numeric($account_id)) {
    $_SESSION['error'] = "Invalid account ID";
    header("Location: chart_of_accounts.php");
    exit();
}

// Get parent account info
$parent_account = $conn->query("
    SELECT coa.*, ac.name as category_name 
    FROM chart_of_accounts coa
    JOIN account_categories ac ON coa.category_id = ac.id
    WHERE coa.id = $account_id
")->fetch_assoc();

// Check if parent account exists
if (!$parent_account) {
    $_SESSION['error'] = "Account not found";
    header("Location: chart_of_accounts.php");
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_sub_account'])) {
        $sub_account_code = $_POST['sub_account_code'];
        $sub_account_name = $_POST['sub_account_name'];
        $description = $_POST['description'];
        
        $stmt = $conn->prepare("INSERT INTO sub_accounts (parent_account_id, sub_account_code, sub_account_name, description) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $account_id, $sub_account_code, $sub_account_name, $description);
        $stmt->execute();
        
        $_SESSION['success'] = "Sub account added successfully";
        header("Location: sub_accounts.php?account_id=$account_id");
        exit();
    }
}

// Now include header after all potential redirects
include 'header.php';

// Fetch sub accounts
$sub_accounts = $conn->query("
    SELECT * FROM sub_accounts 
    WHERE parent_account_id = $account_id
    ORDER BY sub_account_code
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title><?= $page_title ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-light">
    <div class="container mt-4">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h2>
                    <i class="fas fa-list-ol me-2"></i>Sub Accounts for: 
                    <?= htmlspecialchars($parent_account['account_code']) ?> - 
                    <?= htmlspecialchars($parent_account['account_name']) ?>
                </h2>
                <p class="mb-0">Category: <?= htmlspecialchars($parent_account['category_name']) ?></p>
            </div>
            <div class="card-body">
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
                <?php endif; ?>

                <!-- Add Sub Account Form -->
                <form method="POST" class="mb-4">
                    <div class="row g-3">
                        <div class="col-md-2">
                            <label class="form-label">Sub Account Code</label>
                            <input type="text" name="sub_account_code" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Sub Account Name</label>
                            <input type="text" name="sub_account_name" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Description</label>
                            <input type="text" name="description" class="form-control">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" name="add_sub_account" class="btn btn-primary">
                                <i class="fas fa-plus me-1"></i> Add Sub Account
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Sub Accounts Table -->
                <div class="table-responsive">
                    <table class="table table-striped table-bordered">
                        <thead class="table-dark">
                            <tr>
                                <th>Code</th>
                                <th>Sub Account Name</th>
                                <th>Description</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($sub = $sub_accounts->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($sub['sub_account_code']) ?></td>
                                <td><?= htmlspecialchars($sub['sub_account_name']) ?></td>
                                <td><?= htmlspecialchars($sub['description']) ?></td>
                                <td>
                                    <span class="badge <?= $sub['is_active'] ? 'bg-success' : 'bg-secondary' ?>">
                                        <?= $sub['is_active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="edit_sub_account.php?id=<?= $sub['id'] ?>" class="btn btn-sm btn-warning">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="delete_sub_account.php?id=<?= $sub['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-3">
                    <a href="chart_of_accounts.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Back to Chart of Accounts
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
<?php
include 'footer.php';
ob_end_flush();
?>