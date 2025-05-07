<?php
include "db.php";
require 'vendor/autoload.php';

// Set page title
$page_title = "Manage Payments";

// Include header
include 'header.php';


// Initialize messages
$success = isset($_GET['success']) ? $_GET['success'] : '';
$error = isset($_GET['error']) ? $_GET['error'] : '';

// Pagination configuration
$per_page = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $per_page;

// Filter parameters
$search_term = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d'); // Default to today
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d'); // Default to today
$payment_status = isset($_GET['status']) ? $_GET['status'] : 'all'; // Default to all statuses

// Base query
$query = "SELECT SQL_CALC_FOUND_ROWS 
            p.*, 
            COALESCE(la.id, mla.id, ll.id) AS loan_number, 
            m.full_name,
            CASE 
                WHEN p.loan_id IS NOT NULL THEN 'business_loan'
                WHEN p.micro_loan_id IS NOT NULL THEN 'micro_loan'
                WHEN p.lease_loan_id IS NOT NULL THEN 'leasing'
            END AS loan_type,
            'paid' AS status  -- Default status since original tables might not have it
          FROM (
              SELECT id, loan_id, NULL AS micro_loan_id, NULL AS lease_loan_id, amount, payment_date 
              FROM payments
              UNION ALL
              SELECT id, NULL AS loan_id, loan_id AS micro_loan_id, NULL AS lease_loan_id, amount, payment_date 
              FROM micro_loan_payments
              UNION ALL
              SELECT id, NULL AS loan_id, NULL AS micro_loan_id, loan_id AS lease_loan_id, amount, payment_date 
              FROM lease_loan_payments
          ) p
          LEFT JOIN loan_applications la ON p.loan_id = la.id
          LEFT JOIN micro_loan_applications mla ON p.micro_loan_id = mla.id
          LEFT JOIN lease_applications ll ON p.lease_loan_id = ll.id
          JOIN members m ON COALESCE(la.member_id, mla.member_id, ll.member_id) = m.id";

// Add filters
$where = ["p.payment_date BETWEEN ? AND ?"]; // Default filter for today's payments
$params = [$start_date, $end_date];
$types = 'ss';

if (!empty($search_term)) {
    $where[] = "(m.full_name LIKE ? OR la.id LIKE ? OR p.id LIKE ?)";
    $params[] = "%$search_term%";
    $params[] = "%$search_term%";
    $params[] = "%$search_term%";
    $types .= 'sss';
}

if ($payment_status !== 'all') {
    $where[] = "p.status = ?";
    $params[] = $payment_status;
    $types .= 's';
}

$query .= " WHERE " . implode(" AND ", $where);
$query .= " ORDER BY p.payment_date DESC LIMIT ?, ?";
$params[] = $offset;
$params[] = $per_page;
$types .= 'ii';

// Prepare and execute query
$stmt = $conn->prepare($query);
if ($types) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get total count
$total_result = $conn->query("SELECT FOUND_ROWS()");
$total_payments = $total_result->fetch_row()[0];
$total_pages = ceil($total_payments / $per_page);

// Calculate statistics for today's payments only
$stats_query = "SELECT 
                COUNT(*) as count,
                SUM(amount) as total_amount,
                AVG(amount) as avg_amount
                FROM payments
                WHERE payment_date BETWEEN ? AND ?";
$stats_stmt = $conn->prepare($stats_query);
$today_start = date('Y-m-d');
$today_end = date('Y-m-d');
$stats_stmt->bind_param('ss', $today_start, $today_end);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Payments</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .action-buttons .btn { margin-right: 5px; margin-bottom: 5px; }
        .table-responsive { overflow-x: auto; }
        .pagination .page-item.active .page-link { background-color: #0d6efd; border-color: #0d6efd; }
        .stat-card { transition: transform 0.2s; }
        .stat-card:hover { transform: scale(1.03); }
        .filter-section { background-color: #f8f9fa; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
        .date-range-info { font-size: 0.9rem; color: #6c757d; }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-money-bill-wave me-2"></i> Payment Management</h2>
            <div>
                <a href="p_payment.php" class="btn btn-primary me-2">
                    <i class="fas fa-plus me-1"></i> New Payment
                </a>
                <button class="btn btn-success" onclick="exportToExcel()">
                    <i class="fas fa-file-excel me-1"></i> Export
                </button>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?= $success ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?= $error ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Filter Section -->
        <div class="filter-section mb-4">
            <form method="GET" class="row g-3" id="filterForm">
                <div class="col-md-3">
                    <input type="text" name="search" class="form-control" placeholder="Search by name, loan or payment ID" value="<?= htmlspecialchars($search_term) ?>">
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select">
                        <option value="all" <?= $payment_status === 'all' ? 'selected' : '' ?>>All Statuses</option>
                        <option value="paid" <?= $payment_status === 'paid' ? 'selected' : '' ?>>Paid</option>
                        <option value="pending" <?= $payment_status === 'pending' ? 'selected' : '' ?>>Pending</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($start_date) ?>" id="startDate">
                </div>
                <div class="col-md-2">
                    <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($end_date) ?>" id="endDate">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter me-1"></i> Filter
                    </button>
                </div>
                <div class="col-md-1">
                    <button type="button" class="btn btn-secondary w-100" onclick="resetFilters()" title="Reset filters">
                        <i class="fas fa-sync"></i>
                    </button>
                </div>
            </form>
            <div class="date-range-info mt-2">
                Showing payments from <?= date('d/m/Y', strtotime($start_date)) ?> to <?= date('d/m/Y', strtotime($end_date)) ?>
                <?php if ($start_date === date('Y-m-d') && $end_date === date('Y-m-d')): ?>
                    (Today's payments)
                <?php endif; ?>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card stat-card text-white bg-primary mb-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title">Today's Payments</h6>
                                <h3 class="card-text"><?= number_format($stats['count']) ?></h3>
                            </div>
                            <i class="fas fa-list fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card text-white bg-success mb-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title">Today's Amount</h6>
                                <h3 class="card-text">Rs <?= number_format($stats['total_amount'], 2) ?></h3>
                            </div>
                            <i class="fas fa-coins fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card text-white bg-info mb-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title">Avg. Payment</h6>
                                <h3 class="card-text">Rs <?= number_format($stats['avg_amount'], 2) ?></h3>
                            </div>
                            <i class="fas fa-calculator fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payments Table -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <div class="d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-table me-1"></i> Payment Records</span>
                    <span>Showing <?= count($payments) ?> of <?= number_format($total_payments) ?> records</span>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="paymentsTable">
                        <thead class="table-dark">
                            <tr>
                                <th>Payment ID</th>
                                <th>Date</th>
                                <th>Loan Number</th>
                                <th>Client</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($payments) > 0): ?>
                                <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td><?= $payment['id'] ?></td>
                                    <td><?= date('d/m/Y', strtotime($payment['payment_date'])) ?></td>
                                    <td><?php 
                                        $prefix = 'LN-'; // default
                                        if ($payment['loan_type'] == 'micro_loan') $prefix = 'ML-';
                                        elseif ($payment['loan_type'] == 'leasing') $prefix = 'LL-';
                                        echo $prefix . str_pad($payment['loan_number'], 6, '0', STR_PAD_LEFT); 
                                        ?>
                                    </td>
                                    <td><?= htmlspecialchars($payment['full_name']) ?></td>
                                    <td>Rs <?= number_format($payment['amount'], 2) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $payment['status'] === 'paid' ? 'success' : 'warning' ?>">
                                            <?= ucfirst($payment['status']) ?>
                                        </span>
                                    </td>
                                    <td class="action-buttons">
                                        <div class="btn-group">
                                            <a href="edit_payment.php?id=<?= $payment['id'] ?>" class="btn btn-sm btn-warning" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="delete_payment.php?id=<?= $payment['id'] ?>" class="btn btn-sm btn-danger" 
                                               onclick="return confirm('Are you sure you want to delete this payment?')" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                            <a href="receipt.php?id=<?= $payment['id'] ?>&type=<?= $payment['loan_type'] == 'micro_loan' ? 'micro' : ($payment['loan_type'] == 'leasing' ? 'lease' : 'regular') ?>" 
                                                class="btn btn-sm btn-info" title="Receipt" target="_blank">
                                                <i class="fas fa-receipt"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4">No payments found for the selected date range</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>" aria-label="First">
                                <span aria-hidden="true">&laquo;&laquo;</span>
                            </a>
                        </li>
                        <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                        
                        <?php 
                        // Show limited page numbers
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        if ($start_page > 1) {
                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }
                        
                        for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; 
                        
                        if ($end_page < $total_pages) {
                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }
                        ?>
                        
                        <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                        <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $total_pages])) ?>" aria-label="Last">
                                <span aria-hidden="true">&raquo;&raquo;</span>
                            </a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- SheetJS for Excel export -->
    <script src="https://cdn.sheetjs.com/xlsx-0.19.3/package/dist/xlsx.full.min.js"></script>
    <script>
        // Auto-dismiss alerts after 5 seconds
        setTimeout(function() {
            var alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                new bootstrap.Alert(alert).close();
            });
        }, 5000);

        // Export to Excel function
        function exportToExcel() {
            // Get table data
            const table = document.getElementById('paymentsTable');
            const workbook = XLSX.utils.table_to_book(table);
            
            // Generate current date for filename
            const today = new Date();
            const dateStr = today.toISOString().split('T')[0];
            
            // Export to Excel file
            XLSX.writeFile(workbook, `Payments_${dateStr}.xlsx`);
        }

        // Reset filters to show today's payments
        function resetFilters() {
            document.getElementById('startDate').value = '';
            document.getElementById('endDate').value = '';
            document.querySelector('select[name="status"]').value = 'all';
            document.querySelector('input[name="search"]').value = '';
            document.getElementById('filterForm').submit();
        }

        // Client-side search (optional enhancement)
        document.querySelector('input[name="search"]').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('#paymentsTable tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
    </script>
</body>
</html>
<?php
// Include footer
include 'footer.php';
?>