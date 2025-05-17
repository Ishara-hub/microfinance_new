<?php
ob_start();
include "db.php";
session_start();

$page_title = "Lease Approvals";
include 'header.php';

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Handle Loan Approval
if (isset($_POST['approve'])) {
    $loan_id = $_POST['loan_id'];
    
    // Fetch loan details for overview
    $loanQuery = "SELECT ll.*, m.full_name, lp.name AS product_name 
                 FROM lease_applications ll
                 JOIN members m ON ll.member_id = m.id
                 JOIN loan_products lp ON ll.loan_product_id = lp.id
                 WHERE ll.id = ?";
    $loanStmt = $conn->prepare($loanQuery);
    $loanStmt->bind_param("s", $loan_id);
    $loanStmt->execute();
    $loanData = $loanStmt->get_result()->fetch_assoc();

    // Update status to approved
    $updateQuery = "UPDATE lease_applications SET status = 'approved' WHERE id = ?";
    $updateStmt = $conn->prepare($updateQuery);
    $updateStmt->bind_param("s", $loan_id);
    
    if ($updateStmt->execute()) {
        // Create notification
        $message = "Loan $loan_id has been approved (pending disbursement)";
        $link = "loan_details.php?id=$loan_id";
        $notificationQuery = "INSERT INTO notifications (user_id, message, link) VALUES (?, ?, ?)";
        $notificationStmt = $conn->prepare($notificationQuery);
        $notificationStmt->bind_param("iss", $_SESSION['user_id'], $message, $link);
        $notificationStmt->execute();

        $_SESSION['approval_message'] = "Loan $loan_id approved successfully! Waiting for disbursement.";
        header("Location: lease_disburse.php");
        exit();
    }
}

// Handle Loan Rejection
if (isset($_POST['reject'])) {
    $loan_id = $_POST['loan_id'];
    $reason = $_POST['reject_reason'] ?? 'No reason provided';

    $updateQuery = "UPDATE lease_applications SET status = 'rejected', reject_reason = ? WHERE id = ?";
    $updateStmt = $conn->prepare($updateQuery);
    $updateStmt->bind_param("ss", $reason, $loan_id);
    $updateStmt->execute();

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

// Fetch pending loan applications
$result = $conn->query("SELECT ll.id, m.full_name, lp.name, ll.loan_amount, 
                        ll.installments, ll.created_at, ll.credit_officer
                        FROM lease_applications ll
                        JOIN members m ON ll.member_id = m.id
                        JOIN loan_products lp ON ll.loan_product_id = lp.id
                        WHERE ll.status = 'Pending'
                        ORDER BY ll.created_at DESC");
?>

<div class="container mt-5">
    <?php if (isset($_SESSION['approval_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= $_SESSION['approval_message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['approval_message']); ?>
    <?php endif; ?>

    <h2><i class="fas fa-file-signature me-2"></i> Pending Loan Approvals</h2>
    
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <?php if ($result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Member</th>
                                <th>Loan Product</th>
                                <th>Amount</th>
                                <th>Installments</th>
                                <th>Applied On</th>
                                <th>Purpose</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['id']) ?></td>
                                    <td><?= htmlspecialchars($row['full_name']) ?></td>
                                    <td><?= htmlspecialchars($row['name']) ?></td>
                                    <td><?= number_format($row['loan_amount'], 2) ?></td>
                                    <td><?= $row['installments'] ?></td>
                                    <td><?= date('Y-m-d', strtotime($row['created_at'])) ?></td>
                                    <td><?= htmlspecialchars($row['credit_officer']) ?></td>
                                    <td>
                                        <!-- View Details Button - Triggers Modal -->
                                        <button class="btn btn-info btn-sm view-details" 
                                                data-id="<?= $row['id'] ?>"
                                                data-bs-toggle="modal" 
                                                data-bs-target="#loanDetailsModal">
                                            <i class="fas fa-eye me-1"></i> View
                                        </button>
                                        
                                        <!-- Approve/Reject Buttons -->
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="loan_id" value="<?= $row['id'] ?>">
                                            <button type="submit" name="approve" class="btn btn-success btn-sm">
                                                <i class="fas fa-check-circle me-1"></i> Approve
                                            </button>
                                            <button type="button" class="btn btn-danger btn-sm ms-1 reject-btn"
                                                    data-id="<?= $row['id'] ?>"
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#rejectModal">
                                                <i class="fas fa-times-circle me-1"></i> Reject
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">No pending loan applications found.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Loan Details Modal -->
<div class="modal fade" id="loanDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Loan Application Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="loanDetailsContent">
                <!-- Content loaded via AJAX -->
                <div class="text-center my-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Reject Loan Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Reject Loan Application</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="loan_id" id="rejectLoanId">
                    <div class="mb-3">
                        <label for="rejectReason" class="form-label">Reason for Rejection</label>
                        <textarea class="form-control" id="rejectReason" name="reject_reason" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="reject" class="btn btn-danger">Confirm Rejection</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

<script>
$(document).ready(function() {
    // Load loan details in modal
    $('.view-details').click(function() {
        const loanId = $(this).data('id');
        $('#loanDetailsContent').html(`
            <div class="text-center my-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
        `);
        
        $.ajax({
            url: 'get_loan_details.php',
            method: 'GET',
            data: { id: loanId },
            success: function(response) {
                $('#loanDetailsContent').html(response);
            }
        });
    });

    // Set loan ID for rejection
    $('.reject-btn').click(function() {
        $('#rejectLoanId').val($(this).data('id'));
    });
});
</script>