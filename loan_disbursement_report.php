<?php
include "db.php";
require 'vendor/autoload.php';

$page_title = "Loan Disbursement Report";
include 'header.php';

// Get filter parameters
$branch_id = $_GET['branch_id'] ?? '';
$loan_type = $_GET['loan_type'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Base query for disbursed loans
$query_bl = "SELECT 
            la.id AS loan_id,
            la.created_at AS disbursement_date,
            m.full_name AS member_name,
            b.name AS branch_name,
            lp.name AS product_name,
            la.loan_amount,
            la.interest_rate,
            la.installments,
            la.status,
            u.username AS disbursed_by
          FROM loan_applications la
          JOIN members m ON la.member_id = m.id
          JOIN branches b ON la.branch = b.id
          JOIN loan_products lp ON la.loan_product_id = lp.id
          JOIN users u ON la.credit_officer = u.id
          WHERE la.status = 'Disbursed'";
$query_ml = "SELECT 
            mla.id AS loan_id,
            mla.application_date AS disbursement_date,
            m.full_name AS member_name,
            b.name AS branch_name,
            lp.name AS product_name,
            mla.loan_amount,
            mla.interest_rate,
            mla.installments,
            mla.status,
            u.username AS disbursed_by
            FROM micro_loan_applications mla
            JOIN members m ON mla.member_id = m.id
            JOIN branches b ON mla.branch_id = b.id
            JOIN loan_products lp ON mla.loan_product_id = lp.id
            JOIN users u ON mla.credit_officer_id = u.id
            WHERE mla.status = 'Disbursed'";

$query_ll = "SELECT 
            ll.id AS loan_id,
            ll.created_at AS disbursement_date,
            m.full_name AS member_name,
            b.name AS branch_name,
            lp.name AS product_name,
            ll.loan_amount,
            ll.interest_rate,
            ll.installments,
            ll.status,
            u.username AS disbursed_by
          FROM lease_applications ll
          JOIN members m ON ll.member_id = m.id
          JOIN branches b ON ll.branch = b.id
          JOIN loan_products lp ON ll.loan_product_id = lp.id
          JOIN users u ON ll.credit_officer = u.id
          WHERE ll.status = 'Disbursed'";

// Initialize arrays for conditions and parameters
$conditions_bl = [];
$conditions_ml = [];
$conditions_ll = [];
$params = [];
$types = '';

// Apply filters to each query
if (!empty($branch_id)) {
    $conditions_bl[] = "b.id = ?";
    $conditions_ml[] = "b.id = ?";
    $conditions_ll[] = "b.id = ?";
    $params[] = $branch_id;
    $types .= 'i';
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
    
    $query .= " ORDER BY disbursement_date DESC";
} else {
    // Combine all queries if no loan type filter
    $query = "($query_bl) UNION ALL ($query_ml) UNION ALL ($query_ll) ORDER BY disbursement_date DESC";
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
$total_amount = 0;
$total_loans = $result->num_rows;
$rows = $result->fetch_all(MYSQLI_ASSOC);

foreach ($rows as $row) {
    $total_amount += $row['loan_amount'];
}
?>

<div class="container mt-4">
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <h2 class="mb-0"><i class="fas fa-hand-holding-usd me-2"></i> Loan Disbursement Report</h2>
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
                        <a href="loan_disbursement_report.php" class="btn btn-outline-secondary">
                            <i class="fas fa-sync-alt me-1"></i> Reset
                        </a>
                    </div>
                </div>
            </form>

            <!-- Summary -->
            <div class="alert alert-info mb-4">
                <div class="row">
                    <div class="col-md-4">
                        <strong>Total Loans Disbursed:</strong> <?= $total_loans ?>
                    </div>
                    <div class="col-md-4">
                        <strong>Total Amount:</strong> Rs. <?= number_format($total_amount, 2) ?>
                    </div>
                    <div class="col-md-4">
                        <strong>Average Loan Size:</strong> Rs. <?= $total_loans > 0 ? number_format($total_amount / $total_loans, 2) : '0.00' ?>
                    </div>
                </div>
            </div>

            <!-- Report Table -->
            <div class="table-responsive">
                <table class="table table-striped table-bordered" id="disbursementTable">
                    <thead class="table-dark">
                        <tr>
                            <th>Loan ID</th>
                            <th>Disbursement Date</th>
                            <th>Member</th>
                            <th>Branch</th>
                            <th>Product</th>
                            <th>Amount (Rs.)</th>
                            <th>Interest (%)</th>
                            <th>Installments</th>
                            <th>Disbursed By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['loan_id']) ?></td>
                            <td><?= date('Y-m-d', strtotime($row['disbursement_date'])) ?></td>
                            <td><?= htmlspecialchars($row['member_name']) ?></td>
                            <td><?= htmlspecialchars($row['branch_name']) ?></td>
                            <td><?= htmlspecialchars($row['product_name']) ?></td>
                            <td class="text-end"><?= number_format($row['loan_amount'], 2) ?></td>
                            <td class="text-end"><?= number_format($row['interest_rate'], 2) ?></td>
                            <td class="text-center"><?= $row['installments'] ?></td>
                            <td><?= htmlspecialchars($row['disbursed_by']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>