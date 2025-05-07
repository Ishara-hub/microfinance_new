<?php
session_start();
include "db.php";

// Get filter parameters
$account_id = $_GET['account_id'] ?? '';
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-t');

// Build base query
$query = "
    SELECT 
        gl.transaction_date,
        gl.reference,
        gl.journal_desc,
        gl.account_code,
        gl.account_name,
        gl.sub_account_code,
        gl.sub_account_name,
        gl.debit,
        gl.credit,
        gl.entry_desc
    FROM 
        general_ledger gl
    WHERE 
        gl.transaction_date BETWEEN ? AND ?
";

$params = [$date_from, $date_to];
$types = 'ss';

// Add account filter if specified
if (!empty($account_id)) {
    $query .= " AND gl.account_id = ?";
    $params[] = $account_id;
    $types .= 'i';
}

$query .= " ORDER BY gl.transaction_date, gl.reference";

// Prepare and execute query
$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Get accounts for dropdown
$accounts = $conn->query("SELECT * FROM chart_of_accounts WHERE is_active = 1 ORDER BY account_code");

include 'header.php';
?>

<div class="container mt-4">
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <div class="d-flex justify-content-between align-items-center">
                <h2><i class="fas fa-book me-2"></i>General Ledger</h2>
                <div>
                    <button class="btn btn-light btn-sm" onclick="window.print()">
                        <i class="fas fa-print me-1"></i> Print
                    </button>
                    <button class="btn btn-light btn-sm ms-2" id="exportBtn">
                        <i class="fas fa-file-export me-1"></i> Export
                    </button>
                </div>
            </div>
        </div>
        <div class="card-body">
            <!-- Filter Form -->
            <form method="GET" class="mb-4">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Account</label>
                        <select name="account_id" class="form-select">
                            <option value="">All Accounts</option>
                            <?php while($account = $accounts->fetch_assoc()): ?>
                                <option value="<?= $account['id'] ?>" <?= $account_id == $account['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($account['account_code'].' - '.$account['account_name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">From Date</label>
                        <input type="date" name="date_from" class="form-control" value="<?= $date_from ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">To Date</label>
                        <input type="date" name="date_to" class="form-control" value="<?= $date_to ?>">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="fas fa-filter me-1"></i> Filter
                        </button>
                        <a href="general_ledger.php" class="btn btn-outline-secondary">
                            <i class="fas fa-sync-alt me-1"></i> Reset
                        </a>
                    </div>
                </div>
            </form>

            <!-- Report Period -->
            <?php if (!empty($account_id)): ?>
                <?php 
                $account_info = $conn->query("
                    SELECT account_code, account_name 
                    FROM chart_of_accounts 
                    WHERE id = $account_id
                ")->fetch_assoc();
                ?>
                <div class="alert alert-info mb-4">
                    <strong>Account:</strong> <?= htmlspecialchars($account_info['account_code'].' - '.$account_info['account_name']) ?>
                    <br>
                    <strong>Period:</strong> <?= date('m/d/Y', strtotime($date_from)) ?> to <?= date('m/d/Y', strtotime($date_to)) ?>
                </div>
            <?php else: ?>
                <div class="alert alert-info mb-4">
                    <strong>Period:</strong> <?= date('m/d/Y', strtotime($date_from)) ?> to <?= date('m/d/Y', strtotime($date_to)) ?>
                </div>
            <?php endif; ?>

            <!-- General Ledger Table -->
            <div class="table-responsive">
                <table class="table table-striped table-bordered" id="ledgerTable">
                    <thead class="table-dark">
                        <tr>
                            <th>Date</th>
                            <th>Reference</th>
                            <th>Account</th>
                            <th>Sub Account</th>
                            <th class="text-end">Debit</th>
                            <th class="text-end">Credit</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $running_balance = 0;
                        while($row = $result->fetch_assoc()): 
                            $running_balance += ($row['debit'] - $row['credit']);
                        ?>
                        <tr>
                            <td><?= date('m/d/Y', strtotime($row['transaction_date'])) ?></td>
                            <td><?= htmlspecialchars($row['reference']) ?></td>
                            <td><?= htmlspecialchars($row['account_code'].' - '.$row['account_name']) ?></td>
                            <td>
                                <?= $row['sub_account_code'] ? 
                                    htmlspecialchars($row['sub_account_code'].' - '.$row['sub_account_name']) : 
                                    'N/A' ?>
                            </td>
                            <td class="text-end"><?= number_format($row['debit'], 2) ?></td>
                            <td class="text-end"><?= number_format($row['credit'], 2) ?></td>
                            <td><?= htmlspecialchars($row['entry_desc']) ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#ledgerTable').DataTable({
        dom: 'Bfrtip',
        buttons: [
            'excel', 'pdf', 'print'
        ],
        order: [[0, 'asc']],
        pageLength: 50
    });

    // Export button handler
    $('#exportBtn').click(function() {
        $('#ledgerTable').DataTable().button('excel').trigger();
    });
});
</script>

<?php include 'footer.php'; ?>