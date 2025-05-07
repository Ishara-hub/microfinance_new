<?php
include "db.php";
require 'vendor/autoload.php';

$page_title = "Delinquency Report";
include 'header.php';

// Get filter parameters
$branch_id = $_GET['branch_id'] ?? '';
$loan_type = $_GET['loan_type'] ?? '';
$days_late = $_GET['days_late'] ?? '30';

// Base query for delinquent loans
$query = "SELECT 
            la.id AS loan_id,
            m.full_name AS member_name,
            b.name AS branch_name,
            lp.name AS product_name,
            la.loan_amount,
            la.interest_rate,
            la.installments,
            la.rental_value,
            la.created_at AS disbursement_date,
            DATEDIFF(CURDATE(), la.created_at) AS days_since_disbursement,
            (SELECT COUNT(*) FROM payments WHERE loan_id = la.id) AS payments_made,
            (SELECT MAX(payment_date) FROM payments WHERE loan_id = la.id) AS last_payment_date,
            DATEDIFF(CURDATE(), (SELECT MAX(payment_date) FROM payments WHERE loan_id = la.id)) AS days_late,
            (la.loan_amount + (la.loan_amount * la.interest_rate / 100)) - 
            (SELECT IFNULL(SUM(amount), 0) FROM payments WHERE loan_id = la.id) AS outstanding_balance
          FROM loan_applications la
          JOIN members m ON la.member_id = m.id
          JOIN branches b ON la.branch = b.id
          JOIN loan_products lp ON la.loan_product_id = lp.id
          WHERE la.status = 'Disbursed'
          AND DATEDIFF(CURDATE(), (SELECT MAX(payment_date) FROM payments WHERE loan_id = la.id)) > ?";

// Add conditions based on filters
$conditions = [];
$params = [$days_late];
$types = 'i';

if (!empty($branch_id)) {
    $conditions[] = "b.id = ?";
    $params[] = $branch_id;
    $types .= 'i';
}

if (!empty($loan_type)) {
    $conditions[] = "la.id LIKE ?";
    $params[] = "$loan_type%";
    $types .= 's';
}

if (!empty($conditions)) {
    $query .= " AND " . implode(" AND ", $conditions);
}

$query .= " ORDER BY days_late DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Calculate totals
$total_delinquent = $result->num_rows;
$total_outstanding = 0;
$rows = $result->fetch_all(MYSQLI_ASSOC);

foreach ($rows as $row) {
    $total_outstanding += $row['outstanding_balance'];
}
?>

<div class="container mt-4">
    <div class="card shadow">
        <div class="card-header bg-danger text-white">
            <h2 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i> Delinquency Report</h2>
        </div>
        <div class="card-body">
            <!-- Filter Form -->
            <form method="GET" class="mb-4">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Branch</label>
                        <select name="branch_id" class="form-select">
                            <option value="">All Branches</option>
                            <?php
                            $branches = $conn->query("SELECT * FROM branches WHERE status = 'active'");
                            while ($branch = $branches->fetch_assoc()) {
                                $selected = $branch['id'] == $branch_id ? 'selected' : '';
                                echo "<option value='{$branch['id']}' $selected>{$branch['name']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Loan Type</label>
                        <select name="loan_type" class="form-select">
                            <option value="">All Types</option>
                            <option value="BL" <?= $loan_type == 'BL' ? 'selected' : '' ?>>Business Loan</option>
                            <option value="ML" <?= $loan_type == 'ML' ? 'selected' : '' ?>>Micro Loan</option>
                            <option value="LL" <?= $loan_type == 'LL' ? 'selected' : '' ?>>Leasing Loan</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Days Late (Minimum)</label>
                        <select name="days_late" class="form-select">
                            <option value="7" <?= $days_late == '7' ? 'selected' : '' ?>>7+ days</option>
                            <option value="30" <?= $days_late == '30' ? 'selected' : '' ?>>30+ days</option>
                            <option value="60" <?= $days_late == '60' ? 'selected' : '' ?>>60+ days</option>
                            <option value="90" <?= $days_late == '90' ? 'selected' : '' ?>>90+ days</option>
                        </select>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-md-12 text-end">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="fas fa-filter me-1"></i> Filter
                        </button>
                        <a href="delinquency_report.php" class="btn btn-outline-secondary">
                            <i class="fas fa-sync-alt me-1"></i> Reset
                        </a>
                    </div>
                </div>
            </form>

            <!-- Summary -->
            <div class="alert alert-danger mb-4">
                <div class="row">
                    <div class="col-md-4">
                        <strong>Delinquent Loans:</strong> <?= $total_delinquent ?>
                    </div>
                    <div class="col-md-4">
                        <strong>Total Outstanding:</strong> Rs. <?= number_format($total_outstanding, 2) ?>
                    </div>
                    <div class="col-md-4">
                        <strong>Average Days Late:</strong> <?= $total_delinquent > 0 ? number_format(array_sum(array_column($rows, 'days_late')) / $total_delinquent, 1) : '0' ?> days
                    </div>
                </div>
            </div>

            <!-- Report Table -->
            <div class="table-responsive">
                <table class="table table-striped table-bordered" id="delinquencyTable">
                    <thead class="table-dark">
                        <tr>
                            <th>Loan ID</th>
                            <th>Member</th>
                            <th>Branch</th>
                            <th>Product</th>
                            <th>Disbursement Date</th>
                            <th>Last Payment</th>
                            <th>Days Late</th>
                            <th>Outstanding (Rs.)</th>
                            <th>Payments Made</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['loan_id']) ?></td>
                            <td><?= htmlspecialchars($row['member_name']) ?></td>
                            <td><?= htmlspecialchars($row['branch_name']) ?></td>
                            <td><?= htmlspecialchars($row['product_name']) ?></td>
                            <td><?= date('Y-m-d', strtotime($row['disbursement_date'])) ?></td>
                            <td><?= $row['last_payment_date'] ? date('Y-m-d', strtotime($row['last_payment_date'])) : 'Never' ?></td>
                            <td class="text-center <?= $row['days_late'] > 90 ? 'text-danger fw-bold' : ($row['days_late'] > 30 ? 'text-warning' : '') ?>">
                                <?= $row['days_late'] ?>
                            </td>
                            <td class="text-end"><?= number_format($row['outstanding_balance'], 2) ?></td>
                            <td class="text-center"><?= $row['payments_made'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>