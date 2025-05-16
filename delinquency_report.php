<?php
include "db.php";
require 'vendor/autoload.php';

// Set page title
$page_title = "Loan Arrears Report";
include 'header.php';

// Get filter parameters
$branch_id = $_GET['branch_id'] ?? '';
$loan_type = $_GET['loan_type'] ?? '';
$repayment_method = $_GET['repayment_method'] ?? '';
$credit_officer = $_GET['credit_officer_name'] ?? '';
$days_overdue = $_GET['days_overdue'] ?? '';
$date_as_of = $_GET['date_as_of'] ?? date('Y-m-d');

// Build base queries for each loan type with arrears calculation
$query_bl = "SELECT 
            la.id AS loan_id,
            la.disburse_date,
            m.initials AS member_name,
            m.nic AS member_nic,
            u.username AS credit_officer_name,
            b.name AS branch_name,
            lp.name AS product_name,
            la.loan_amount,
            lp.repayment_method,
            ld.installment_date AS due_date,
            ld.total_due,
            ld.paid_amount,
            ld.interest_due,
            ld.capital_due,
            ld.status,
            DATEDIFF('$date_as_of', ld.installment_date) AS days_overdue
          FROM loan_details ld
          JOIN loan_applications la ON ld.loan_application_id = la.id
          JOIN users u ON la.credit_officer = u.id
          JOIN members m ON la.member_id = m.id
          JOIN branches b ON la.branch = b.id
          JOIN loan_products lp ON la.loan_product_id = lp.id
          WHERE la.status = 'Disbursed' 
          AND ld.status = 'pending'
          AND DATEDIFF('$date_as_of', ld.installment_date) > 0";

$query_ml = "SELECT 
            mla.id AS loan_id,
            mla.disbursed_date AS disburse_date,
            m.initials AS member_name,
            m.nic AS member_nic,
            u.username AS credit_officer_name,
            b.name AS branch_name,
            lp.name AS product_name,
            mla.loan_amount,
            lp.repayment_method,
            mld.installment_date AS due_date,
            mld.total_due,
            mld.paid_amount,
            mld.interest_due,
            mld.capital_due,
            mld.status,
            DATEDIFF('$date_as_of', mld.installment_date) AS days_overdue
          FROM micro_loan_details mld
          JOIN micro_loan_applications mla ON mld.micro_loan_application_id = mla.id
          JOIN members m ON mla.member_id = m.id
          JOIN users u ON mla.credit_officer_id = u.id
          JOIN branches b ON mla.branch_id = b.id
          JOIN loan_products lp ON mla.loan_product_id = lp.id
          WHERE mla.status = 'Disbursed'
          AND mld.status = 'pending'
          AND DATEDIFF('$date_as_of', mld.installment_date) > 0";

$query_ll = "SELECT 
            ll.id AS loan_id,
            ll.created_at AS disburse_date,
            m.initials AS member_name,
            m.nic AS member_nic,
            u.username AS credit_officer_name,
            b.name AS branch_name,
            lp.name AS product_name,
            ll.loan_amount,
            lp.repayment_method,
            ld.installment_date AS due_date,
            ld.total_due,
            ld.paid_amount,
            ld.interest_due,
            ld.capital_due,
            ld.status,
            DATEDIFF('$date_as_of', ld.installment_date) AS days_overdue
          FROM lease_details ld
          JOIN lease_applications ll ON ld.lease_application_id = ll.id
          JOIN members m ON ll.member_id = m.id
          JOIN users u ON ll.credit_officer = u.id
          JOIN branches b ON ll.branch = b.id
          JOIN loan_products lp ON ll.loan_product_id = lp.id
          WHERE ll.status = 'Disbursed'
          AND ld.status = 'pending' 
          AND DATEDIFF('$date_as_of', ld.installment_date) > 0";

// Add days overdue filter
$query_bl .= " AND DATEDIFF('$date_as_of', ld.installment_date) >= ?";
$query_ml .= " AND DATEDIFF('$date_as_of', mld.installment_date) >= ?";
$query_ll .= " AND DATEDIFF('$date_as_of', ld.installment_date) >= ?";

// Initialize arrays for conditions and parameters
$conditions_bl = [];
$conditions_ml = [];
$conditions_ll = [];
$params = [$days_overdue];
$types = 'i';

// Apply branch filter if specified
if (!empty($branch_id)) {
    $conditions_bl[] = "b.id = ?";
    $conditions_ml[] = "b.id = ?";
    $conditions_ll[] = "b.id = ?";
    $params[] = $branch_id;
    $types .= 'i';
}
// Apply repayment method filter if specified
if (!empty($repayment_method)) {
    $conditions_bl[] = "lp.repayment_method = ?";
    $conditions_ml[] = "lp.repayment_method = ?";   
    $conditions_ll[] = "lp.repayment_method = ?";
    $params[] = $repayment_method;
    $types .= 's';
}

// Apply credit officer filter if specified
if (!empty($credit_officer)) {
    $conditions_bl[] = "u.username = ?";
    $conditions_ml[] = "u.username = ?";
    $conditions_ll[] = "u.username = ?";
    $params[] = $credit_officer;
    $types .= 's';
}
// Add conditions to each query if they exist
if (!empty($conditions_bl)) {
    $query_bl .= " AND " . implode(" AND ", $conditions_bl);
}
if (!empty($conditions_ml)) {
    $query_ml .= " AND " . implode(" AND ", $conditions_ml);
}
if (!empty($conditions_ll)) {
    $query_ll .= " AND " . implode(" AND ", $conditions_ll);
}

// Handle loan type filter
if (!empty($loan_type)) {
    if ($loan_type == 'BL') {
        $query = $query_bl;
    } elseif ($loan_type == 'ML') {
        $query = $query_ml;
    } elseif ($loan_type == 'LL') {
        $query = $query_ll;
    }
    
    $query .= " ORDER BY days_overdue DESC";
    
    // Prepare and execute single query
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        die("Error preparing query: " . $conn->error);
    }
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    if (!$stmt->execute()) {
        die("Error executing query: " . $stmt->error);
    }
    $result = $stmt->get_result();
    $rows = $result->fetch_all(MYSQLI_ASSOC);
} else {
    // For UNION case, execute each query separately and merge results in PHP
    $rows = [];
    
    // Execute BL query
    $stmt_bl = $conn->prepare($query_bl);
    if ($stmt_bl) {
        if (!empty($params)) {
            $stmt_bl->bind_param($types, ...$params);
        }
        if ($stmt_bl->execute()) {
            $rows = array_merge($rows, $stmt_bl->get_result()->fetch_all(MYSQLI_ASSOC));
        }
    }
    
    // Execute ML query
    $stmt_ml = $conn->prepare($query_ml);
    if ($stmt_ml) {
        if (!empty($params)) {
            $stmt_ml->bind_param($types, ...$params);
        }
        if ($stmt_ml->execute()) {
            $rows = array_merge($rows, $stmt_ml->get_result()->fetch_all(MYSQLI_ASSOC));
        }
    }
    
    // Execute LL query
    $stmt_ll = $conn->prepare($query_ll);
    if ($stmt_ll) {
        if (!empty($params)) {
            $stmt_ll->bind_param($types, ...$params);
        }
        if ($stmt_ll->execute()) {
            $rows = array_merge($rows, $stmt_ll->get_result()->fetch_all(MYSQLI_ASSOC));
        }
    }
    
    // Sort results by days_overdue descending
    usort($rows, function($a, $b) {
        return $b['days_overdue'] - $a['days_overdue'];
    });
}

// Calculate totals for summary
$total_loans = 0;
$total_amount = 0;
$total_outstanding = 0;
$total_overdue = 0;

$grouped_loans = [];

// Group by loan ID to count unique loans
foreach ($rows as $row) {
    if (!isset($grouped_loans[$row['loan_id']])) {
        $grouped_loans[$row['loan_id']] = $row;
        $total_loans++;
        $total_amount += $row['loan_amount'];
    }
    $total_outstanding += $row['total_due'];
    $total_overdue += $row['days_overdue'];
}

$avg_overdue = $total_loans > 0 ? $total_overdue / $total_loans : 0;
?>

<div class="container mt-4">
    <div class="card shadow">
        <div class="card-header bg-danger text-white">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i> Loan Arrears Report</h2>
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
                        <label class="form-label">Repayment Method</label>
                        <select name="repayment_method" class="form-select">
                            <option value="">All Methods</option>
                            <option value="daily" <?= $repayment_method == 'daily' ? 'selected' : '' ?>>Daily</option>
                            <option value="weekly" <?= $repayment_method == 'weekly' ? 'selected' : '' ?>>Weekly</option>
                            <option value="monthly" <?= $repayment_method == 'monthly' ? 'selected' : '' ?>>Monthly</option>
                            <option value="bullet" <?= $repayment_method == 'bullet' ? 'selected' : '' ?>>Bullet</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Credit Officer</label>
                        <select name="credit_officer_name" class="form-select">
                            <option value="">All Officers</option>
                            <?php
                            $officers = $conn->query("SELECT DISTINCT u.username FROM users u 
                                                    JOIN loan_applications la ON u.id = la.credit_officer
                                                    JOIN micro_loan_applications mla ON u.id = mla.credit_officer_id
                                                    JOIN lease_applications ll ON u.id = ll.credit_officer
                                                    WHERE u.user_type = 'credit_officer'");
                            while ($officer = $officers->fetch_assoc()) {
                                $selected = $officer['username'] == $credit_officer ? 'selected' : '';
                                echo "<option value='{$officer['username']}' $selected>{$officer['username']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Minimum Days Overdue</label>
                        <select name="days_overdue" class="form-select">
                            <option value="1" <?= $days_overdue == 1 ? 'selected' : '' ?>>1+ days</option>
                            <option value="7" <?= $days_overdue == 7 ? 'selected' : '' ?>>7+ days</option>
                            <option value="30" <?= $days_overdue == 30 ? 'selected' : '' ?>>30+ days (default)</option>
                            <option value="60" <?= $days_overdue == 60 ? 'selected' : '' ?>>60+ days</option>
                            <option value="90" <?= $days_overdue == 90 ? 'selected' : '' ?>>90+ days</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">As of Date</label>
                        <input type="date" name="date_as_of" class="form-control" value="<?= $date_as_of ?>">
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-md-12 text-end">
                        <button type="submit" class="btn btn-danger me-2">
                            <i class="fas fa-filter me-1"></i> Filter
                        </button>
                        <a href="loan_arrears_report.php" class="btn btn-outline-secondary">
                            <i class="fas fa-sync-alt me-1"></i> Reset
                        </a>
                    </div>
                </div>
            </form>

            <!-- Report Summary -->
            <div class="alert alert-warning mb-4">
                <div class="row">
                    <div class="col-md-3">
                        <strong>Total Loans in Arrears:</strong> <?= $total_loans ?>
                    </div>
                    <div class="col-md-3">
                        <strong>Total Outstanding:</strong> Rs <?= number_format($total_outstanding, 2) ?>
                    </div>
                    <div class="col-md-3">
                        <strong>Avg. Days Overdue:</strong> <?= number_format($avg_overdue, 1) ?> days
                    </div>
                    <div class="col-md-3">
                        <strong>As of Date:</strong> <?= date('M d, Y', strtotime($date_as_of)) ?>
                    </div>
                </div>
            </div>

            <!-- Report Table -->
            <div class="table-responsive">
                <table class="table table-striped table-bordered table-hover" id="reportTable">
                    <thead class="table-dark">
                        <tr>
                            <th>Loan ID</th>
                            <th>Member</th>
                            <th>NIC</th>
                            <th>Branch</th>
                            <th>Product</th>
                            <th>Repayment Method</th>
                            <th>Due Date</th>
                            <th>Days Overdue</th>
                            <th>Total Due (Rs.)</th>
                            <th>Paid (Rs.)</th>
                            <th>Status</th>
                            <th>Credit Officer</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): 
                            $overdue_class = '';
                            if ($row['days_overdue'] >= 90) {
                                $overdue_class = 'bg-danger text-white';
                            } elseif ($row['days_overdue'] >= 60) {
                                $overdue_class = 'bg-warning text-dark';
                            } elseif ($row['days_overdue'] >= 30) {
                                $overdue_class = 'bg-info text-dark';
                            }
                        ?>
                        <tr>
                            <td>
                                <?php 
                                $loan_id = htmlspecialchars($row['loan_id']);
                                if (strpos($loan_id, 'BL') === 0) {
                                    $type = 'business';
                                } elseif (strpos($loan_id, 'ML') === 0) {
                                    $type = 'micro';
                                } elseif (strpos($loan_id, 'LL') === 0) {
                                    $type = 'lease';
                                } else {
                                    $type = 'unknown';
                                }
                                echo '<a href="loan_details.php?id=' . $loan_id . '">' . $loan_id . '</a>';
                                ?>
                            </td>
                            <td><?= htmlspecialchars($row['member_name']) ?></td>
                            <td><?= htmlspecialchars($row['member_nic']) ?></td>
                            <td><?= htmlspecialchars($row['branch_name']) ?></td>
                            <td><?= htmlspecialchars($row['product_name']) ?></td>
                            <td><?= ucfirst(htmlspecialchars($row['repayment_method'])) ?></td>
                            <td><?= date('Y-m-d', strtotime($row['due_date'])) ?></td>
                            <td class="<?= $overdue_class ?> text-center fw-bold"><?= $row['days_overdue'] ?></td>
                            <td class="text-end"><?= number_format($row['total_due'], 2) ?></td>
                            <td class="text-end"><?= number_format($row['paid_amount'], 2) ?></td>
                            <td>
                                <span class="badge bg-warning">
                                    <?= ucfirst($row['status']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($row['credit_officer_name']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="table-active">
                            <th colspan="7" class="text-end">Totals:</th>
                            <th></th>
                            <th class="text-end"><?= number_format($total_outstanding, 2) ?></th>
                            <th colspan="3"></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>