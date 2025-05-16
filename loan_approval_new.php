<?php
ob_start();
include "db.php";
session_start();

$page_title = "Business Loan Approvals";
include 'header.php';

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Handle Loan Approval (First Step)
if (isset($_POST['approve'])) {
    $loan_id = $_POST['loan_id'];

    $updateQuery = "UPDATE loan_applications SET status = 'approved' WHERE id = ?";
    $updateStmt = $conn->prepare($updateQuery);
    $updateStmt->bind_param("s", $loan_id);
    
    if ($updateStmt->execute()) {
        $message = "Loan $loan_id has been approved (pending disbursement)";
        $link = "loan_details.php?id=$loan_id";
        $notificationQuery = "INSERT INTO notifications (user_id, message, link) VALUES (?, ?, ?)";
        $notificationStmt = $conn->prepare($notificationQuery);
        $notificationStmt->bind_param("iss", $_SESSION['user_id'], $message, $link);
        $notificationStmt->execute();

        $_SESSION['approval_message'] = "Loan $loan_id approved successfully! Waiting for disbursement.";
        ob_end_clean();
        header("Location: loan_approval1.php");
        exit();
    }
}

// Handle Loan Disbursement (Second Step)
if (isset($_POST['disburse'])) {
    $loan_id = $_POST['loan_id'];
    $disburse_date = $_POST['disburse_date'];
    $first_installment_date = $_POST['first_installment_date'];

    // Validate dates
    if (empty($disburse_date) || empty($first_installment_date)) {
        $_SESSION['error_message'] = "Please select both disbursement date and first installment date";
        header("Location: loan_approval1.php");
        exit();
    }

    // Check if first installment date is after disbursement date
    if (strtotime($first_installment_date) <= strtotime($disburse_date)) {
        $_SESSION['error_message'] = "First installment date must be after disbursement date";
        header("Location: loan_approval1.php");
        exit();
    }

    // Fetch loan application details
    $loanQuery = "SELECT * FROM loan_applications WHERE id = ? AND status = 'approved'";
    $loanStmt = $conn->prepare($loanQuery);
    $loanStmt->bind_param("s", $loan_id);
    $loanStmt->execute();
    $loanResult = $loanStmt->get_result();

    if ($loanResult->num_rows > 0) {
        $loanData = $loanResult->fetch_assoc();

        // Update loan status to 'disbursed' and set dates
        $updateQuery = "UPDATE loan_applications SET status = 'disbursed', disburse_date = ?, first_installment_date = ? WHERE id = ?";
        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->bind_param("sss", $disburse_date, $first_installment_date, $loan_id);
        $updateStmt->execute();

        // Generate installment schedule based on first installment date
        $loan_amount = $loanData['loan_amount'];
        $installments = $loanData['installments'];
        $rental_value = $loanData['rental_value'];
        $loan_product_id = $loanData['loan_product_id'];

        // Get repayment method and interest type
        $productQuery = "SELECT repayment_method, interest_rate, interest_type FROM loan_products WHERE id = ?";
        $productStmt = $conn->prepare($productQuery);
        $productStmt->bind_param("i", $loan_product_id);
        $productStmt->execute();
        $productResult = $productStmt->get_result();
        $productData = $productResult->fetch_assoc();
        $repayment_method = $productData['repayment_method'];
        $interest_rate = $productData['interest_rate'];
        $interest_type = $productData['interest_type'];

        // Insert installments into loan_details table
        $due_date = $first_installment_date;
        $outstanding_loan = $loan_amount;

        // Calculate total interest and rental value
        $total_interest = $rental_value * $installments - $loan_amount;
        $rental_value = ($loan_amount + $total_interest) / $installments;

        for ($i = 0; $i < $installments; $i++) {
            // For first installment, use the specified date
            // For subsequent installments, calculate based on repayment method
            if ($i > 0) {
                if ($repayment_method == "daily") {
                    $due_date = date("Y-m-d", strtotime($due_date . " +1 day"));
                } elseif ($repayment_method == "weekly") {
                    $due_date = date("Y-m-d", strtotime($due_date . " +1 week"));
                } elseif ($repayment_method == "monthly") {
                    $due_date = date("Y-m-d", strtotime($due_date . " +1 month"));
                }
            }

            // Calculate interest due and capital due
            if ($interest_type == 'flat_rate') {
                $interest_due = $total_interest / $installments;
                $capital_due = $loan_amount / $installments;
            } elseif ($interest_type == 'reducing_balance') {
                $interest_due = ($outstanding_loan * $interest_rate / 100) / $installments;
                $capital_due = $rental_value - $interest_due;
                $outstanding_loan -= $capital_due;
            } else {
                die("Invalid interest type!");
            }

            // Insert into loan_details table
            $insertQuery = "INSERT INTO loan_details 
                            (loan_application_id, installment_number, installment_date, installment_amount, capital_due, interest_due, total_due, status) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')";
            $insertStmt = $conn->prepare($insertQuery);
            $installment_number = $i + 1;
            $total_due = $capital_due + $interest_due;
            $insertStmt->bind_param("sisdddd", $loan_id, $installment_number, $due_date, $rental_value, $capital_due, $interest_due, $total_due);
            $insertStmt->execute();
        }

        // Create notification
        $message = "Loan $loan_id has been disbursed with first installment on $first_installment_date";
        $link = "loan_details.php?id=$loan_id";
        $notificationQuery = "INSERT INTO notifications (user_id, message, link) VALUES (?, ?, ?)";
        $notificationStmt = $conn->prepare($notificationQuery);
        $notificationStmt->bind_param("iss", $_SESSION['user_id'], $message, $link);
        $notificationStmt->execute();

        $_SESSION['approval_message'] = "Loan $loan_id disbursed successfully! Installment schedule generated starting from $first_installment_date.";
        ob_end_clean();
        header("Location: loan_approval1.php");
        exit();
    } else {
        $_SESSION['error_message'] = "Loan not found or not in approved status";
        header("Location: loan_approval1.php");
        exit();
    }
}

// Handle Loan Rejection
if (isset($_POST['reject'])) {
    $loan_id = $_POST['loan_id'];

    $updateQuery = "UPDATE loan_applications SET status = 'rejected' WHERE id = ?";
    $updateStmt = $conn->prepare($updateQuery);
    $updateStmt->bind_param("s", $loan_id);
    $updateStmt->execute();

    $message = "Loan $loan_id has been rejected";
    $link = "loan_details.php?id=$loan_id";
    $notificationQuery = "INSERT INTO notifications (user_id, message, link) VALUES (?, ?, ?)";
    $notificationStmt = $conn->prepare($notificationQuery);
    $notificationStmt->bind_param("iss", $_SESSION['user_id'], $message, $link);
    $notificationStmt->execute();

    $_SESSION['approval_message'] = "Loan $loan_id rejected successfully!";
    ob_end_clean();
    header("Location: loan_approval1.php");
    exit();
}

// Fetch all loan applications
$result = $conn->query("SELECT loan_applications.id, members.full_name, loan_products.name, loan_applications.loan_amount, 
                        loan_applications.installments, loan_applications.status, loan_applications.created_at, 
                        loan_applications.disburse_date, loan_applications.first_installment_date
                        FROM loan_applications 
                        JOIN members ON loan_applications.member_id = members.id 
                        JOIN loan_products ON loan_applications.loan_product_id = loan_products.id
                        ORDER BY 
                            CASE 
                                WHEN loan_applications.status = 'Pending' THEN 1
                                WHEN loan_applications.status = 'Approved' THEN 2
                                WHEN loan_applications.status = 'Disbursed' THEN 3
                                ELSE 4
                            END,
                            loan_applications.created_at DESC");
?>
'Pending','Approved','Rejected','Settled','Disbursed'
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Business Loan Approvals</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .status-pending { color: #ffc107; font-weight: bold; }
        .status-approved { color: #28a745; font-weight: bold; }
        .status-disbursed { color: #17a2b8; font-weight: bold; }
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
        .disburse-form {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }
        .disburse-form .form-group {
            margin-bottom: 0;
        }
        .disburse-form input[type="date"] {
            max-width: 150px;
        }
        .date-label {
            font-size: 0.8rem;
            margin-bottom: 0.2rem;
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

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?= $_SESSION['error_message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <h2><i class="fas fa-file-signature me-2"></i> Loan Applications</h2>
        
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
                                <th>Status</th>
                                <th>Disburse Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $result->fetch_assoc()) { ?>
                                <tr>
                                    <td class="loan-id"><?= htmlspecialchars($row['id']); ?></td>
                                    <td><?= htmlspecialchars($row['full_name']); ?></td>
                                    <td><?= htmlspecialchars($row['name']); ?></td>
                                    <td><?= number_format($row['loan_amount'], 2); ?></td>
                                    <td><?= $row['installments']; ?> <?= $row['installments'] == 1 ? 'Month' : 'Months'; ?></td>
                                    <td class="status-<?= strtolower($row['status']) ?>">
                                        <?= ucfirst($row['status']); ?>
                                    </td>
                                    <td>
                                        <?= $row['disburse_date'] ? date('Y-m-d', strtotime($row['disburse_date'])) : 'N/A'; ?>
                                    </td>
                                    <td>
                                        <?php if ($row['status'] == 'Pending') { ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="loan_id" value="<?= htmlspecialchars($row['id']); ?>">
                                                <button type="submit" name="approve" class="btn btn-success btn-sm">
                                                    <i class="fas fa-check-circle me-1"></i> Approve
                                                </button>
                                                <button type="submit" name="reject" class="btn btn-danger btn-sm ms-1">
                                                    <i class="fas fa-times-circle me-1"></i> Reject
                                                </button>
                                            </form>
                                        <?php } elseif ($row['status'] == 'Approved') { ?>
                                            <form method="POST" class="disburse-form">
                                                <input type="hidden" name="loan_id" value="<?= htmlspecialchars($row['id']); ?>">
                                                
                                                <div class="form-group">
                                                    <div class="date-label">Disburse Date</div>
                                                    <input type="date" name="disburse_date" class="form-control form-control-sm" 
                                                           value="<?= date('Y-m-d') ?>" min="<?= date('Y-m-d', strtotime($row['created_at'])) ?>">
                                                </div>
                                                
                                                <div class="form-group">
                                                    <div class="date-label">1st Installment</div>
                                                    <input type="date" name="first_installment_date" class="form-control form-control-sm" 
                                                           value="<?= date('Y-m-d', strtotime('+3 days')) ?>">
                                                </div>
                                                
                                                <button type="submit" name="disburse" class="btn btn-primary btn-sm align-self-end">
                                                    <i class="fas fa-money-bill-wave me-1"></i> Disburse
                                                </button>
                                            </form>
                                        <?php } ?>
                                        <a href="loan_details.php?id=<?= htmlspecialchars($row['id']) ?>" class="btn btn-info btn-sm <?= $row['status'] == 'Approved' ? 'mt-2' : '' ?>">
                                            <i class="fas fa-eye me-1"></i> View
                                        </a>
                                    </td>
                                </tr>
                            <?php } ?>
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

            // Set first installment date to 3 days after disbursement date by default
            $('input[name="disburse_date"]').change(function() {
                var disburseDate = new Date($(this).val());
                if (!isNaN(disburseDate.getTime())) {
                    disburseDate.setDate(disburseDate.getDate() + 3);
                    var formattedDate = disburseDate.toISOString().split('T')[0];
                    $('input[name="first_installment_date"]').val(formattedDate);
                }
            });

            // Check for new loan applications periodically
            function checkNewApplications() {
                $.ajax({
                    url: 'check_new_applications.php',
                    method: 'GET',
                    success: function(response) {
                        if (response.count > 0) {
                            showNewApplicationNotification(response.count);
                        }
                    },
                    complete: function() {
                        setTimeout(checkNewApplications, 60000);
                    }
                });
            }

            function showNewApplicationNotification(count) {
                let notificationBadge = $('#newLoanBadge');
                if (notificationBadge.length === 0) {
                    $('h2').append(` <span class="badge bg-danger" id="newLoanBadge">${count} New</span>`);
                } else {
                    notificationBadge.text(`${count} New`);
                }
                
                const toast = $(`
                    <div class="toast show position-fixed bottom-0 end-0 m-3" style="z-index: 9999">
                        <div class="toast-header bg-primary text-white">
                            <strong class="me-auto">New Loan Application</strong>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
                        </div>
                        <div class="toast-body">
                            There are ${count} new loan applications waiting for review.
                            <a href="loan_approval.php" class="text-white fw-bold">Click to view</a>
                        </div>
                    </div>
                `);
                
                $('body').append(toast);
                
                setTimeout(function() {
                    toast.remove();
                }, 5000);
            }

            checkNewApplications();
        });
    </script>
</body>
</html>
<?php
include 'footer.php';
?>