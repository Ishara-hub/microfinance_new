<?php
include "db.php";
require 'vendor/autoload.php';

$page_title = "Loan Collection Report";
include 'header.php';

// Get filter parameters
$branch_id = $_GET['branch_id'] ?? '';
$loan_type = $_GET['loan_type'] ?? '';
$collected_by = $_GET['collected_by'] ?? '';
$repayment_method = $_GET['repayment_method'] ?? '';
$date_from = $_GET['date_from'] ?? date('Y-m-d');
$date_to = $_GET['date_to'] ?? date('Y-m-d');

// Base query for collections
$query_bl = "SELECT 
            p.id AS payment_id,
            p.payment_date,
            p.loan_id,
            p.amount,
            lp.repayment_method,
            p.installment_id,
            m.full_name AS member_name,
            b.name AS branch_name,
            u.username AS collected_by,
            la.loan_amount,
            la.installments
          FROM payments p
          JOIN loan_applications la ON p.loan_id = la.id
          JOIN members m ON la.member_id = m.id
          join loan_products lp ON la.loan_product_id = lp.id
          JOIN branches b ON la.branch = b.id
          JOIN users u ON la.credit_officer = u.id
          WHERE 1=1";
$query_ml = "SELECT 
            mlp.id AS payment_id,
            mlp.payment_date,
            mlp.loan_id,
            mlp.amount,
            lp.repayment_method,
            mlp.installment_id,
            m.full_name AS member_name,
            b.name AS branch_name,
            u.username AS collected_by,
            mla.loan_amount,
            mla.installments
        FROM micro_loan_payments mlp
        JOIN micro_loan_applications mla ON mlp.loan_id = mla.id
        JOIN members m ON mla.member_id = m.id
        join loan_products lp ON mla.loan_product_id = lp.id
        JOIN branches b ON mla.branch_id = b.id
        JOIN users u ON mla.credit_officer_id = u.id
        WHERE 1=1";


$query_ll = "SELECT 
            llp.id AS payment_id,
            llp.payment_date,
            llp.loan_id,
            llp.amount,
            lp.repayment_method,
            llp.installment_id,
            m.full_name AS member_name,
            b.name AS branch_name,
            u.username AS collected_by,
            ll.loan_amount,
            ll.installments
        FROM lease_loan_payments llp
        JOIN lease_applications ll ON llp.loan_id = ll.id
        JOIN members m ON ll.member_id = m.id
        join loan_products lp ON ll.loan_product_id = lp.id
        JOIN branches b ON ll.branch = b.id
        JOIN users u ON ll.credit_officer = u.id
        WHERE 1=1";

// Add conditions based on filters
$conditions_bl = [];
$conditions_ml = [];
$conditions_ll = [];
$params = [];
$types = '';

// Validate and format dates
if (!empty($date_from)) {
    $date_from = date('Y-m-d', strtotime($date_from));
}
if (!empty($date_to)) {
    $date_to = date('Y-m-d', strtotime($date_to));
}

// Apply date filters to payment dates (not loan application dates)
if (!empty($date_from) && !empty($date_to)) {
    // Ensure date_from is before date_to
    if (strtotime($date_from) > strtotime($date_to)) {
        // Swap dates if they're in wrong order
        $temp = $date_from;
        $date_from = $date_to;
        $date_to = $temp;
    }
    
    $conditions_bl[] = "p.payment_date BETWEEN ? AND ?";
    $conditions_ml[] = "mlp.payment_date BETWEEN ? AND ?";
    $conditions_ll[] = "llp.payment_date BETWEEN ? AND ?";
    array_push($params, $date_from, $date_to);
    $types .= 'ss';
} elseif (!empty($date_from)) {
    // Only start date provided
    $conditions_bl[] = "p.payment_date >= ?";
    $conditions_ml[] = "mlp.payment_date >= ?";
    $conditions_ll[] = "llp.payment_date >= ?";
    $params[] = $date_from;
    $types .= 's';
} elseif (!empty($date_to)) {
    // Only end date provided
    $conditions_bl[] = "p.payment_date <= ?";
    $conditions_ml[] = "mlp.payment_date <= ?";
    $conditions_ll[] = "llp.payment_date <= ?";
    $params[] = $date_to;
    $types .= 's';
}

// Apply filters to each query
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

// Apply collected by filter if specified
if (!empty($collected_by)) {
    $conditions_bl[] = "u.username = ?";
    $conditions_ml[] = "u.username = ?";
    $conditions_ll[] = "u.username = ?";
    $params[] = $collected_by;
    $types .= 's';
}

if (!empty($status)) {
    $conditions_bl[] = "la.status = ?";
    $conditions_ml[] = "mla.status = ?";
    $conditions_ll[] = "ll.status = ?";
    $params[] = $status;
    $types .= 's';
}

if (!empty($date_from) && !empty($date_to)) {
    $conditions_bl[] = "la.created_at BETWEEN ? AND ?";
    $conditions_ml[] = "mla.application_date BETWEEN ? AND ?";
    $conditions_ll[] = "ll.created_at BETWEEN ? AND ?";
    array_push($params, $date_from, $date_to);
    $types .= 'ss';
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

    $query .= " ORDER BY payment_date DESC";
} else {
    // Combine all queries if no loan type filter
    $query = "($query_bl) UNION ALL ($query_ml) UNION ALL ($query_ll) ORDER BY payment_date DESC";
}

// Prepare and execute the query
$stmt = $conn->prepare($query);
if (!$stmt) {
    die("Error preparing query: " . $conn->error);
}

// Only bind parameters if we have any
if (!empty($params)) {
    // For UNION queries, we need to bind parameters multiple times
    if (empty($loan_type)) {
        // For UNION queries, we need to bind the parameters for each part of the UNION
        // This is a limitation of prepared statements with UNION
        // So we'll use a different approach for UNION queries
        
        // First, build the full query with parameters inserted directly (with proper escaping)
        $full_query = $query;
        foreach ($params as $i => $param) {
            $full_query = str_replace('?', "'" . $conn->real_escape_string($param) . "'", $full_query);
        }
        
        // Then execute the query directly
        $result = $conn->query($full_query);
        if (!$result) {
            die("Error executing query: " . $conn->error);
        }
    } else {
        // For single queries, we can use prepared statements normally
        $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) {
            die("Error executing query: " . $stmt->error);
        }
        $result = $stmt->get_result();
    }
} else {
    // No parameters to bind, just execute
    if (!$stmt->execute()) {
        die("Error executing query: " . $stmt->error);
    }
    $result = $stmt->get_result();
}

// Calculate totals
$total_collected = 0;
$total_payments = $result->num_rows;
$rows = $result->fetch_all(MYSQLI_ASSOC);

foreach ($rows as $row) {
    $total_collected += $row['amount'];
}
?>

<div class="container mt-4">
    <div class="card shadow">
        <div class="card-header bg-success text-white">
            <h2 class="mb-0"><i class="fas fa-money-bill-wave me-2"></i> Loan Collection Report</h2>
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
                        <label class="form-label">Collected By</label>
                        <select name="collected_by" class="form-select">
                            <option value="">All Collectors</option>
                            <?php
                            $collectors = $conn->query("SELECT DISTINCT username FROM users WHERE user_type IN ('credit_officer', 'collector')");
                            while ($collector = $collectors->fetch_assoc()) {
                                $selected = $collector['username'] == $collected_by ? 'selected' : '';
                                echo "<option value='{$collector['username']}' $selected>{$collector['username']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Date From</label>
                        <input type="date" name="date_from" class="form-control" value="<?= $date_from ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Date To</label>
                        <input type="date" name="date_to" class="form-control" value="<?= $date_to ?>">
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-md-12 text-end">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="fas fa-filter me-1"></i> Filter
                        </button>
                        <a href="loan_collection_report.php" class="btn btn-outline-secondary">
                            <i class="fas fa-sync-alt me-1"></i> Reset
                        </a>
                    </div>
                </div>
            </form>

            <!-- Summary -->
            <div class="alert alert-success mb-4">
                <div class="row">
                    <div class="col-md-4">
                        <strong>Total Payments:</strong> <?= $total_payments ?>
                    </div>
                    <div class="col-md-4">
                        <strong>Total Collected:</strong> Rs. <?= number_format($total_collected, 2) ?>
                    </div>
                    <div class="col-md-4">
                        <strong>Average Payment:</strong> Rs. <?= $total_payments > 0 ? number_format($total_collected / $total_payments, 2) : '0.00' ?>
                    </div>
                </div>
            </div>

            <!-- Report Table -->
            <div class="table-responsive">
                <table class="table table-striped table-bordered" id="collectionTable">
                    <thead class="table-dark">
                        <tr>
                            <th>Payment Date</th>
                            <th>Receipt No</th>
                            <th>Loan ID</th>
                            <th>Member</th>
                            <th>Branch</th>
                            <th>Amount (Rs.)</th>
                            <th>Payment Method</th>
                            <th>Collected By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= date('Y-m-d', strtotime($row['payment_date'])) ?></td>
                            <td><?= htmlspecialchars($row['installment_id']) ?></td>
                            <td><?= htmlspecialchars($row['loan_id']) ?></td>
                            <td><?= htmlspecialchars($row['member_name']) ?></td>
                            <td><?= htmlspecialchars($row['branch_name']) ?></td>
                            <td class="text-end"><?= number_format($row['amount'], 2) ?></td>
                            <td><?= htmlspecialchars($row['repayment_method']) ?></td>
                            <td><?= htmlspecialchars($row['collected_by']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>