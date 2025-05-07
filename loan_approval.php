<?php
ob_start(); // Start output buffering at the VERY FIRST LINE
include "db.php";
session_start();

// Set page title
$page_title = "Loan Approvals";

// Include header
include 'header.php';

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Function to create interest-only payment schedule
function createInterestOnlySchedule($loan_id, $loan_amount, $interest_rate, $installments, $disburse_date, $conn) {
    // Calculate monthly interest payment (annual rate divided by 12)
    $monthly_interest = ($loan_amount * ($interest_rate / 100)) / 12;
    
    // Create interest-only payment schedule
    for ($i = 1; $i <= $installments; $i++) {
        $due_date = date("Y-m-d", strtotime($disburse_date . " +$i month"));
        
        $insertQuery = "INSERT INTO loan_details 
                        (loan_application_id, installment_number, installment_date, 
                         installment_amount, capital_due, interest_due, total_due, status) 
                        VALUES (?, ?, ?, ?, 0, ?, ?, 'pending')";
        $insertStmt = $conn->prepare($insertQuery);
        $total_due = $monthly_interest;
        $insertStmt->bind_param("sisddd", $loan_id, $i, $due_date, 
                              $monthly_interest, $monthly_interest, $total_due);
        $insertStmt->execute();
    }
    
    // Add final principal payment
    $final_payment_date = date("Y-m-d", strtotime($disburse_date . " +$installments month"));
    $insertQuery = "INSERT INTO loan_details 
                    (loan_application_id, installment_number, installment_date, 
                     installment_amount, capital_due, interest_due, total_due, status) 
                    VALUES (?, ?, ?, ?, ?, 0, ?, 'pending')";
    $insertStmt = $conn->prepare($insertQuery);
    $final_installment_number = $installments + 1;
    $insertStmt->bind_param("sisddd", $loan_id, $final_installment_number, 
                          $final_payment_date, $loan_amount, $loan_amount, $loan_amount);
    $insertStmt->execute();
}

// Handle Loan Approval
if (isset($_POST['approve'])) {
    $loan_id = $_POST['loan_id'];
    $disburse_date = $_POST['disburse_date'] ?? date('Y-m-d');

    // Fetch loan application details
    $loanQuery = "SELECT * FROM loan_applications WHERE id = ?";
    $loanStmt = $conn->prepare($loanQuery);
    $loanStmt->bind_param("s", $loan_id);
    $loanStmt->execute();
    $loanResult = $loanStmt->get_result();

    if ($loanResult->num_rows > 0) {
        $loanData = $loanResult->fetch_assoc();

        // Update loan status to 'approved'
        $updateQuery = "UPDATE loan_applications SET status = 'approved', approved_date = NOW(), disburse_date = ? WHERE id = ?";
        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->bind_param("ss", $disburse_date, $loan_id);
        $updateStmt->execute();

        // Get loan product details
        $loan_amount = $loanData['loan_amount'];
        $installments = $loanData['installments'];
        $loan_product_id = $loanData['loan_product_id'];

        $productQuery = "SELECT repayment_method, interest_rate, interest_type FROM loan_products WHERE id = ?";
        $productStmt = $conn->prepare($productQuery);
        $productStmt->bind_param("i", $loan_product_id);
        $productStmt->execute();
        $productResult = $productStmt->get_result();
        $productData = $productResult->fetch_assoc();
        
        $repayment_method = $productData['repayment_method'];
        $interest_rate = $productData['interest_rate'];
        $interest_type = $productData['interest_type'];

        // Handle different interest types
        if ($interest_type == 'interest_only') {
            createInterestOnlySchedule($loan_id, $loan_amount, $interest_rate, $installments, $disburse_date, $conn);
        } else {
            // For flat rate and reducing balance loans
            $due_date = $disburse_date;
            $outstanding_loan = $loan_amount;
            $total_interest = ($loan_amount * $interest_rate / 100) * ($installments / 12); // Simple interest calculation
            
            for ($i = 0; $i < $installments; $i++) {
                // Calculate due date based on repayment method
                if ($repayment_method == "daily") {
                    $due_date = date("Y-m-d", strtotime($due_date . " +1 day"));
                } elseif ($repayment_method == "weekly") {
                    $due_date = date("Y-m-d", strtotime($due_date . " +1 week"));
                } elseif ($repayment_method == "monthly") {
                    $due_date = date("Y-m-d", strtotime($due_date . " +1 month"));
                }

                // Calculate payment amounts based on interest type
                if ($interest_type == 'flat_rate') {
                    $interest_due = $total_interest / $installments;
                    $capital_due = $loan_amount / $installments;
                } elseif ($interest_type == 'reducing_balance') {
                    $interest_due = ($outstanding_loan * $interest_rate / 100) / 12;
                    $capital_due = ($loan_amount + $total_interest) / $installments - $interest_due;
                    $outstanding_loan -= $capital_due;
                }

                $total_due = $capital_due + $interest_due;

                // Insert into loan_details table
                $insertQuery = "INSERT INTO loan_details 
                                (loan_application_id, installment_number, installment_date, 
                                installment_amount, capital_due, interest_due, total_due, status) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')";
                $insertStmt = $conn->prepare($insertQuery);
                $installment_number = $i + 1;
                $insertStmt->bind_param("sisdddd", $loan_id, $installment_number, $due_date, 
                                      $total_due, $capital_due, $interest_due, $total_due);
                $insertStmt->execute();
            }
        }

        // Create notification
        $message = "Loan $loan_id has been approved";
        $link = "loan_details.php?id=$loan_id";
        $notificationQuery = "INSERT INTO notifications (user_id, message, link) VALUES (?, ?, ?)";
        $notificationStmt = $conn->prepare($notificationQuery);
        $notificationStmt->bind_param("iss", $_SESSION['user_id'], $message, $link);
        $notificationStmt->execute();

        $_SESSION['approval_message'] = "Loan $loan_id approved successfully!";
        ob_end_clean();
        header("Location: loan_approval.php");
        exit();
    }
}

// Handle Loan Rejection
if (isset($_POST['reject'])) {
    $loan_id = $_POST['loan_id'];
    $rejection_reason = $_POST['rejection_reason'] ?? '';

    // Update loan status to 'rejected'
    $updateQuery = "UPDATE loan_applications SET status = 'rejected', rejection_reason = ? WHERE id = ?";
    $updateStmt = $conn->prepare($updateQuery);
    $updateStmt->bind_param("ss", $rejection_reason, $loan_id);
    $updateStmt->execute();

    // Create notification
    $message = "Loan $loan_id has been rejected";
    $link = "loan_details.php?id=$loan_id";
    $notificationQuery = "INSERT INTO notifications (user_id, message, link) VALUES (?, ?, ?)";
    $notificationStmt = $conn->prepare($notificationQuery);
    $notificationStmt->bind_param("iss", $_SESSION['user_id'], $message, $link);
    $notificationStmt->execute();

    $_SESSION['approval_message'] = "Loan $loan_id rejected successfully!";
    header("Location: loan_approval.php");
    exit();
}

// Filter handling
$filter = $_GET['filter'] ?? 'pending';
$valid_filters = ['pending', 'approved', 'rejected', 'all'];
$filter = in_array($filter, $valid_filters) ? $filter : 'pending';

// Search functionality
$search_term = $_GET['search'] ?? '';
$search_condition = '';
if (!empty($search_term)) {
    $search_term = "%$search_term%";
    $search_condition = " AND (loan_applications.id LIKE ? OR members.full_name LIKE ? OR members.nic LIKE ?)";
}

// Fetch loan applications with filter
$query = "SELECT loan_applications.id, members.full_name, loan_products.name, 
          loan_applications.loan_amount, loan_applications.installments, 
          loan_applications.status, loan_applications.created_at,
          loan_products.interest_type
          FROM loan_applications 
          JOIN members ON loan_applications.member_id = members.id 
          JOIN loan_products ON loan_applications.loan_product_id = loan_products.id";

if ($filter !== 'all') {
    $query .= " WHERE loan_applications.status = '" . ucfirst($filter) . "'";
} else {
    $query .= " WHERE loan_applications.status IN ('Pending', 'Approved', 'Rejected')";
}

if (!empty($search_condition)) {
    $query .= $search_condition;
}

$query .= " ORDER BY loan_applications.created_at DESC";

$stmt = $conn->prepare($query);
if (!empty($search_term)) {
    $stmt->bind_param("sss", $search_term, $search_term, $search_term);
}
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Loan Approvals</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .status-pending { color: #ffc107; font-weight: bold; }
        .status-approved { color: #28a745; font-weight: bold; }
        .status-rejected { color: #dc3545; font-weight: bold; }
        .auto-reload {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
        }
        .loan-id {
            font-family: monospace;
            font-weight: bold;
        }
        .filter-active {
            background-color: #0d6efd;
            color: white !important;
        }
        .interest-type-badge {
            font-size: 0.75rem;
            padding: 0.25em 0.4em;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <?php if (isset($_SESSION['approval_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?= $_SESSION['approval_message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['approval_message']); ?>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-file-signature me-2"></i> Loan Applications</h2>
            <form method="GET" class="d-flex">
                <input type="text" name="search" class="form-control me-2" placeholder="Search loans..." 
                       value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                <button type="submit" class="btn btn-outline-primary">Search</button>
            </form>
        </div>
        
        <!-- Status Filter Tabs -->
        <div class="mb-4">
            <div class="btn-group" role="group">
                <a href="?filter=pending<?= !empty($search_term) ? '&search=' . urlencode($_GET['search']) : '' ?>" 
                   class="btn btn-outline-primary <?= $filter === 'pending' ? 'filter-active' : '' ?>">
                    Pending
                </a>
                <a href="?filter=approved<?= !empty($search_term) ? '&search=' . urlencode($_GET['search']) : '' ?>" 
                   class="btn btn-outline-primary <?= $filter === 'approved' ? 'filter-active' : '' ?>">
                    Approved
                </a>
                <a href="?filter=rejected<?= !empty($search_term) ? '&search=' . urlencode($_GET['search']) : '' ?>" 
                   class="btn btn-outline-primary <?= $filter === 'rejected' ? 'filter-active' : '' ?>">
                    Rejected
                </a>
                <a href="?filter=all<?= !empty($search_term) ? '&search=' . urlencode($_GET['search']) : '' ?>" 
                   class="btn btn-outline-primary <?= $filter === 'all' ? 'filter-active' : '' ?>">
                    All Loans
                </a>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Member</th>
                                <th>Loan Product</th>
                                <th>Amount</th>
                                <th>Installments</th>
                                <th>Interest Type</th>
                                <th>Created At</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()) { ?>
                                    <tr>
                                        <td class="loan-id"><?= htmlspecialchars($row['id']); ?></td>
                                        <td><?= htmlspecialchars($row['full_name']); ?></td>
                                        <td><?= htmlspecialchars($row['name']); ?></td>
                                        <td><?= number_format($row['loan_amount'], 2); ?></td>
                                        <td><?= $row['installments']; ?> <?= $row['installments'] == 1 ? 'Month' : 'Months'; ?></td>
                                        <td>
                                            <span class="badge bg-secondary interest-type-badge">
                                                <?= ucfirst(str_replace('_', ' ', $row['interest_type'])) ?>
                                            </span>
                                        </td>
                                        <td><?= date('Y-m-d H:i', strtotime($row['created_at'])); ?></td>
                                        <td class="status-<?= strtolower($row['status']) ?>">
                                            <?= ucfirst($row['status']); ?>
                                        </td>
                                        <td>
                                            <?php if ($row['status'] == 'Pending') { ?>
                                                <button type="button" class="btn btn-success btn-sm" 
                                                        data-bs-toggle="modal" data-bs-target="#approveModal<?= $row['id'] ?>">
                                                    <i class="fas fa-check-circle me-1"></i> Approve
                                                </button>
                                                <button type="button" class="btn btn-danger btn-sm ms-1" 
                                                        data-bs-toggle="modal" data-bs-target="#rejectModal<?= $row['id'] ?>">
                                                    <i class="fas fa-times-circle me-1"></i> Reject
                                                </button>
                                            <?php } ?>
                                            <a href="loan_details.php?id=<?= htmlspecialchars($row['id']) ?>" 
                                               class="btn btn-info btn-sm <?= $row['status'] == 'Pending' ? 'ms-1' : '' ?>">
                                                <i class="fas fa-eye me-1"></i> View
                                            </a>
                                        </td>
                                    </tr>

                                    <!-- Approve Modal -->
                                    <div class="modal fade" id="approveModal<?= $row['id'] ?>" tabindex="-1" aria-labelledby="approveModalLabel" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header bg-success text-white">
                                                    <h5 class="modal-title" id="approveModalLabel">Approve Loan</h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <form method="POST">
                                                    <div class="modal-body">
                                                        <p>Are you sure you want to approve this loan?</p>
                                                        <div class="mb-3">
                                                            <label for="disburse_date" class="form-label">Disbursement Date</label>
                                                            <input type="date" class="form-control" id="disburse_date" name="disburse_date" 
                                                                   value="<?= date('Y-m-d') ?>" required>
                                                        </div>
                                                        <input type="hidden" name="loan_id" value="<?= $row['id'] ?>">
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" name="approve" class="btn btn-success">Confirm Approval</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Reject Modal -->
                                    <div class="modal fade" id="rejectModal<?= $row['id'] ?>" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header bg-danger text-white">
                                                    <h5 class="modal-title" id="rejectModalLabel">Reject Loan</h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <form method="POST">
                                                    <div class="modal-body">
                                                        <p>Are you sure you want to reject this loan?</p>
                                                        <div class="mb-3">
                                                            <label for="rejection_reason" class="form-label">Reason for Rejection</label>
                                                            <textarea class="form-control" id="rejection_reason" name="rejection_reason" rows="3"></textarea>
                                                        </div>
                                                        <input type="hidden" name="loan_id" value="<?= $row['id'] ?>">
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" name="reject" class="btn btn-danger">Confirm Rejection</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php } ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="text-center py-4">
                                        <div class="text-muted">
                                            <i class="fas fa-inbox fa-3x mb-3"></i>
                                            <h5>No <?= ucfirst($filter) ?> loans found</h5>
                                            <?php if (!empty($search_term)): ?>
                                                <p>Try a different search term</p>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Auto-reload button -->
    <div class="auto-reload">
        <button class="btn btn-primary btn-sm" id="autoReloadBtn" title="Auto Refresh">
            <i class="fas fa-sync-alt"></i> Auto Refresh
        </button>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // Auto-reload functionality
            let autoReload = false;
            let reloadInterval;
            
            $('#autoReloadBtn').click(function() {
                autoReload = !autoReload;
                
                if (autoReload) {
                    $(this).addClass('btn-success').removeClass('btn-primary');
                    $(this).html('<i class="fas fa-sync-alt fa-spin"></i> Auto Refresh ON');
                    reloadInterval = setInterval(function() {
                        location.reload();
                    }, 30000);
                } else {
                    $(this).addClass('btn-primary').removeClass('btn-success');
                    $(this).html('<i class="fas fa-sync-alt"></i> Auto Refresh');
                    clearInterval(reloadInterval);
                }
            });
        });
    </script>
</body>
</html>
<?php
// Include footer
include 'footer.php';
ob_end_flush();
?>