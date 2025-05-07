<?php
include "db.php";
include 'header.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_account'])) {
        $category_id = $_POST['category_id'];
        $account_code = $_POST['account_code'];
        $account_name = $_POST['account_name'];
        $description = $_POST['description'];
        
        $stmt = $conn->prepare("INSERT INTO chart_of_accounts (category_id, account_code, account_name, description) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $category_id, $account_code, $account_name, $description);
        $stmt->execute();
    }
}

// Fetch all accounts with category names
$accounts = $conn->query("
    SELECT coa.*, ac.name as category_name 
    FROM chart_of_accounts coa
    JOIN account_categories ac ON coa.category_id = ac.id
    ORDER BY coa.account_code
");

// Fetch categories for dropdown
$categories = $conn->query("SELECT * FROM account_categories ORDER BY name");
?>

<div class="container mt-4">
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <h2><i class="fas fa-book me-2"></i>Chart of Accounts</h2>
        </div>
        <div class="card-body">
            <!-- Add Account Form -->
            <form method="POST" class="mb-4">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Category</label>
                        <select name="category_id" class="form-select" required>
                            <option value="">Select Category</option>
                            <?php while($cat = $categories->fetch_assoc()): ?>
                                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Account Code</label>
                        <input type="text" name="account_code" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Account Name</label>
                        <input type="text" name="account_name" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Description</label>
                        <input type="text" name="description" class="form-control">
                    </div>
                    <div class="col-md-12 text-end">
                        <button type="submit" name="add_account" class="btn btn-primary">
                            <i class="fas fa-plus me-1"></i> Add Account
                        </button>
                    </div>
                </div>
            </form>

            <!-- Accounts Table -->
            <div class="table-responsive">
                <table class="table table-striped table-bordered">
                    <thead class="table-dark">
                        <tr>
                            <th>Code</th>
                            <th>Account Name</th>
                            <th>Category</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($account = $accounts->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($account['account_code']) ?></td>
                            <td><?= htmlspecialchars($account['account_name']) ?></td>
                            <td><?= htmlspecialchars($account['category_name']) ?></td>
                            <td><?= htmlspecialchars($account['description']) ?></td>
                            <td>
                                <span class="badge <?= $account['is_active'] ? 'bg-success' : 'bg-secondary' ?>">
                                    <?= $account['is_active'] ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>
                            <td>
                                <a href="sub_accounts.php?account_id=<?= $account['id'] ?>" class="btn btn-sm btn-info" title="Sub Accounts">
                                    <i class="fas fa-list"></i>
                                </a>
                                <a href="edit_account.php?id=<?= $account['id'] ?>" class="btn btn-sm btn-warning">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="delete_account.php?id=<?= $account['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>