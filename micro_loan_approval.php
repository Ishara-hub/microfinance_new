<?php
ob_start(); // Start output buffering at the VERY FIRST LINE
include "db.php";
session_start();

// Set page title
$page_title = "micro Loan Approvals";

// Include header
include 'header.php';

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Handle Loan Approval
if (isset($_POST['approve'])) {
    $loan_id = $_POST['loan_id']; // Changed from intval() since ID is now VARCHAR

    // Fetch loan application details
    $loanQuery = "SELECT * FROM micro_loan_applications WHERE id = ?";
    $loanStmt = $conn->prepare($loanQuery);
    $loanStmt->bind_param("s", $loan_id); // Changed from "i" to "s" for string
    $loanStmt->execute();
    $loanResult = $loanStmt->get_result();

    if ($loanResult->num_rows > 0) {
        $loanData = $loanResult->fetch_assoc();

        // Update loan status to 'approved'
        $updateQuery = "UPDATE micro_loan_applications SET status = 'approved' WHERE id = ?";
        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->bind_param("s", $loan_id); // Changed from "i" to "s"
        $updateStmt->execute();

        // Generate installment schedule
        $loan_amount = $loanData['loan_amount'];
        $installments = $loanData['installments'];
        $rental_value = $loanData['rental_value'];
        $loan_product_id = $loanData['loan_product_id'];
        $disburse_date = date("Y-m-d");

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
        $due_date = $disburse_date;
        $outstanding_loan = $loan_amount;

        // Calculate total interest and rental value
        $total_interest = $rental_value * $installments - $loan_amount;
        $rental_value = ($loan_amount + $total_interest) / $installments;

        for ($i = 0; $i < $installments; $i++) {
            // Calculate due date based on repayment method
            if ($repayment_method == "daily") {
                $due_date = date("Y-m-d", strtotime($due_date . " +1 day"));
            } elseif ($repayment_method == "weekly") {
                $due_date = date("Y-m-d", strtotime($due_date . " +1 week"));
            } elseif ($repayment_method == "monthly") {
                $due_date = date("Y-m-d", strtotime($due_date . " +1 month"));
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
            $insertQuery = "INSERT INTO micro_loan_details 
                            (micro_loan_application_id, installment_number, installment_date, installment_amount, capital_due, interest_due, total_due, status) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')";
            $insertStmt = $conn->prepare($insertQuery);
            $installment_number = $i + 1;
            $total_due = $capital_due + $interest_due;
            $insertStmt->bind_param("sisdddd", $loan_id, $installment_number, $due_date, $rental_value, $capital_due, $interest_due, $total_due);
            $insertStmt->execute();
        }

        // Create notification
        $message = "Loan $loan_id has been approved"; // Changed from # to just display ID
        $link = "loan_details.php?id=$loan_id";
        $notificationQuery = "INSERT INTO notifications (user_id, message, link) VALUES (?, ?, ?)";
        $notificationStmt = $conn->prepare($notificationQuery);
        $notificationStmt->bind_param("iss", $_SESSION['user_id'], $message, $link);
        $notificationStmt->execute();

        // Set session variable for success message
        $_SESSION['approval_message'] = "Loan $loan_id approved successfully!"; // Removed #
        
        // Replace all header() redirects with this pattern:
        if ($updateStmt->execute()) {
            ob_end_clean(); // Clean the buffer before redirect
            header("Location: micro_loan_approval.php");
            exit();
        }
    }
}

// Handle Loan Rejection
if (isset($_POST['reject'])) {
    $loan_id = $_POST['loan_id']; // Changed from intval()

    // Update loan status to 'rejected'
    $updateQuery = "UPDATE micro_loan_applications SET status = 'rejected' WHERE id = ?";
    $updateStmt = $conn->prepare($updateQuery);
    $updateStmt->bind_param("s", $loan_id); // Changed from "i" to "s"
    $updateStmt->execute();

    // Create notification
    $message = "Loan $loan_id has been rejected"; // Changed from #
    $link = "micro_loan_details.php?id=$loan_id";
    $notificationQuery = "INSERT INTO notifications (user_id, message, link) VALUES (?, ?, ?)";
    $notificationStmt = $conn->prepare($notificationQuery);
    $notificationStmt->bind_param("iss", $_SESSION['user_id'], $message, $link);
    $notificationStmt->execute();

    $_SESSION['approval_message'] = "Loan $loan_id rejected successfully!"; // Removed #
    header("Location: micro_loan_approval.php");
    exit();
}

// Fetch all loan applications
$result = $conn->query("SELECT micro_loan_applications.id, members.full_name, loan_products.name, micro_loan_applications.loan_amount, 
                        micro_loan_applications.installments, micro_loan_applications.status, micro_loan_applications.application_date 
                        FROM micro_loan_applications 
                        JOIN members ON micro_loan_applications.member_id = members.id 
                        JOIN loan_products ON micro_loan_applications.loan_product_id = loan_products.id
                        ORDER BY 
                            CASE 
                                WHEN micro_loan_applications.status = 'Pending' THEN 1
                                WHEN micro_loan_applications.status = 'Approved' THEN 2
                                ELSE 3
                            END,
                            micro_loan_applications.application_date DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Micro Loan Approvals</title>
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
                                <th>Created At</th>
                                <th>Status</th>
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
                                    <td><?= date('Y-m-d H:i', strtotime($row['application_date'])); ?></td>
                                    <td class="status-<?= strtolower($row['status']) ?>">
                                        <?= ucfirst($row['status']); ?>
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
                                            <a href="loan_details.php?id=<?= htmlspecialchars($row['id']) ?>" class="btn btn-info btn-sm ms-1">
                                                <i class="fas fa-eye me-1"></i> View
                                            </a>
                                        <?php } else { ?>
                                            <a href="loan_details.php?id=<?= htmlspecialchars($row['id']) ?>" class="btn btn-info btn-sm">
                                                <i class="fas fa-eye me-1"></i> View Details
                                            </a>
                                        <?php } ?>
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
                    }, 30000); // Reload every 30 seconds
                } else {
                    $(this).addClass('btn-primary').removeClass('btn-success');
                    $(this).html('<i class="fas fa-sync-alt"></i> Auto Refresh');
                    clearInterval(reloadInterval);
                }
            });

            // Check for new loan applications periodically (without page reload)
            function checkNewApplications() {
                $.ajax({
                    url: 'check_new_applications.php',
                    method: 'GET',
                    success: function(response) {
                        if (response.count > 0) {
                            // Show notification
                            showNewApplicationNotification(response.count);
                        }
                    },
                    complete: function() {
                        setTimeout(checkNewApplications, 60000); // Check every minute
                    }
                });
            }

            function showNewApplicationNotification(count) {
                // Create or update notification badge
                let notificationBadge = $('#newLoanBadge');
                if (notificationBadge.length === 0) {
                    $('h2').append(` <span class="badge bg-danger" id="newLoanBadge">${count} New</span>`);
                } else {
                    notificationBadge.text(`${count} New`);
                }
                
                // Show toast notification
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
                
                // Auto-remove after 5 seconds
                setTimeout(function() {
                    toast.remove();
                }, 5000);
            }

            // Start checking for new applications
            checkNewApplications();
        });
    </script>
</body>
</html>
<?php
// Include footer
include 'footer.php';
?>