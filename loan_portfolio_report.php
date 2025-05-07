<?php
include "db.php";
require 'vendor/autoload.php';

// Set page title
$page_title = "Loan Portfolio Report";
include 'header.php';

// Get filter parameters
$branch_id = $_GET['branch_id'] ?? '';
$loan_type = $_GET['loan_type'] ?? '';
$status = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build base queries for each loan type
$query_bl = "SELECT 
            la.id AS loan_id,
            la.created_at AS application_date,
            m.initials AS member_name,
            m.nic AS member_nic,
            b.name AS branch_name,
            lp.name AS product_name,
            la.loan_amount,
            la.interest_rate,
            la.installments,
            la.rental_value,
            la.status,
            (SELECT COUNT(*) FROM payments WHERE loan_id = la.id) AS payments_made,
            (la.loan_amount + (la.loan_amount * la.interest_rate / 100)) - 
            (SELECT IFNULL(SUM(amount), 0) FROM payments WHERE loan_id = la.id) AS outstanding_balance
          FROM loan_applications la
          JOIN members m ON la.member_id = m.id
          JOIN branches b ON la.branch = b.id
          JOIN loan_products lp ON la.loan_product_id = lp.id
          WHERE la.id LIKE 'BL%'";

$query_ml = "SELECT 
            mla.id AS loan_id,
            mla.application_date,
            m.initials  AS member_name,
            m.nic AS member_nic,
            b.name AS branch_name,
            lp.name AS product_name,
            mla.loan_amount,
            mla.interest_rate,
            mla.installments,
            mla.rental_value,
            mla.status,
            (SELECT COUNT(*) FROM micro_loan_payments WHERE loan_id = mla.id) AS payments_made,
            (mla.loan_amount + (mla.loan_amount * mla.interest_rate / 100)) - 
            (SELECT IFNULL(SUM(amount), 0) FROM micro_loan_payments WHERE loan_id = mla.id) AS outstanding_balance
          FROM micro_loan_applications mla
          JOIN members m ON mla.member_id = m.id
          JOIN branches b ON mla.branch_id = b.id
          JOIN loan_products lp ON mla.loan_product_id = lp.id
          WHERE mla.id LIKE 'ML%'";

$query_ll = "SELECT 
            ll.id AS loan_id,
            ll.created_at AS application_date,
            m.initials  AS member_name,
            m.nic AS member_nic,
            b.name AS branch_name,
            lp.name AS product_name,
            ll.loan_amount,
            ll.interest_rate,
            ll.installments,
            ll.rental_value,
            ll.status,
            (SELECT COUNT(*) FROM lease_loan_payments WHERE loan_id = ll.id) AS payments_made,
            (ll.loan_amount + (ll.loan_amount * ll.interest_rate / 100)) - 
            (SELECT IFNULL(SUM(amount), 0) FROM lease_loan_payments WHERE loan_id = ll.id) AS outstanding_balance
          FROM lease_applications ll
          JOIN members m ON ll.member_id = m.id
          JOIN branches b ON ll.branch = b.id
          JOIN loan_products lp ON ll.loan_product_id = lp.id
          WHERE ll.id LIKE 'LL%'";

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
    
    $query .= " ORDER BY application_date DESC";
} else {
    // Combine all queries if no loan type filter
    $query = "($query_bl) UNION ALL ($query_ml) UNION ALL ($query_ll) ORDER BY application_date DESC";
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



// Calculate totals for summary
$total_loans = $result->num_rows;
$total_amount = 0;
$total_interest = 0;
$total_outstanding = 0;
$total_rental = 0;

$rows = $result->fetch_all(MYSQLI_ASSOC);
foreach ($rows as $row) {
    $total_amount += $row['loan_amount'];
    $total_interest += $row['interest_rate'];
    $total_outstanding += $row['outstanding_balance'];
    $total_rental += $row['rental_value'];
}
$avg_interest = $total_loans > 0 ? $total_interest / $total_loans : 0;
?>

<div class="container mt-4">
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="mb-0"><i class="fas fa-book me-2"></i> Loan Portfolio Report</h2>
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
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">All Statuses</option>
                            <option value="Pending" <?= $status == 'Pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="Approved" <?= $status == 'Approved' ? 'selected' : '' ?>>Approved</option>
                            <option value="Disbursed" <?= $status == 'Disbursed' ? 'selected' : '' ?>>Disbursed</option>
                            <option value="Completed" <?= $status == 'Completed' ? 'selected' : '' ?>>Completed</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Date Range</label>
                        <div class="input-group">
                            <input type="date" name="date_from" class="form-control" value="<?= $date_from ?>">
                            <span class="input-group-text">to</span>
                            <input type="date" name="date_to" class="form-control" value="<?= $date_to ?>">
                        </div>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-md-12 text-end">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="fas fa-filter me-1"></i> Filter
                        </button>
                        <a href="loan_portfolio_report.php" class="btn btn-outline-secondary">
                            <i class="fas fa-sync-alt me-1"></i> Reset
                        </a>
                    </div>
                </div>
            </form>

            <!-- Report Summary -->
            <div class="alert alert-info mb-4">
                <div class="row">
                    <div class="col-md-4">
                        <strong>Total Loans:</strong> <?= $total_loans ?>
                    </div>
                    <div class="col-md-4">
                        <strong>Total Amount:</strong> Rs. <?= number_format($total_amount, 2) ?>
                    </div>
                    <div class="col-md-4">
                        <strong>Avg. Interest Rate:</strong> <?= number_format($avg_interest, 2) ?>%
                    </div>
                </div>
            </div>

            <!-- Report Table -->
            <div class="table-responsive">
                <table class="table table-striped table-bordered table-hover" id="reportTable">
                    <thead class="table-dark">
                        <tr>
                            <th>Loan ID</th>
                            <th>Date</th>
                            <th>Member</th>
                            <th>NIC</th>
                            <th>Branch</th>
                            <th>Product</th>
                            <th>Amount (Rs.)</th>
                            <th>Interest (%)</th>
                            <th>Installments</th>
                            <th>Rental (Rs.)</th>
                            <th>Payments</th>
                            <th>Outstanding (Rs.)</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
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
                            <td><?= date('Y-m-d', strtotime($row['application_date'])) ?></td>
                            <td><?= htmlspecialchars($row['member_name']) ?></td>
                            <td><?= htmlspecialchars($row['member_nic']) ?></td>
                            <td><?= htmlspecialchars($row['branch_name']) ?></td>
                            <td><?= htmlspecialchars($row['product_name']) ?></td>
                            <td class="text-end"><?= number_format($row['loan_amount'], 2) ?></td>
                            <td class="text-end"><?= number_format($row['interest_rate'], 2) ?></td>
                            <td class="text-center"><?= $row['installments'] ?></td>
                            <td class="text-end"><?= number_format($row['rental_value'], 2) ?></td>
                            <td class="text-center"><?= $row['payments_made'] ?></td>
                            <td class="text-end"><?= number_format($row['outstanding_balance'], 2) ?></td>
                            <td>
                                <span class="badge 
                                    <?= $row['status'] == 'Disbursed' ? 'bg-success' : 
                                       ($row['status'] == 'Pending' ? 'bg-warning text-dark' : 
                                       ($row['status'] == 'Approved' ? 'bg-info' : 'bg-secondary')) ?>">
                                    <?= $row['status'] ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="table-active">
                            <th colspan="6" class="text-end">Totals:</th>
                            <th class="text-end"><?= number_format($total_amount, 2) ?></th>
                            <th></th>
                            <th></th>
                            <th class="text-end"><?= number_format($total_rental, 2) ?></th>
                            <th></th>
                            <th class="text-end"><?= number_format($total_outstanding, 2) ?></th>
                            <th></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript Libraries -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#reportTable').DataTable({
        dom: 'Bfrtip',
        buttons: [
            'excel', 'pdf', 'print'
        ],
        pageLength: 25,
        order: [[1, 'desc']]
    });

    // Export button handler
    $('#exportBtn').click(function() {
        $('#reportTable').DataTable().button('excel').trigger();
    });
});
</script>

<?php
include 'footer.php';
?>