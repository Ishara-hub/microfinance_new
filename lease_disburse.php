<?php

use function PHPSTORM_META\type;

ob_start();
include "db.php";
session_start();

$page_title = "Lease Disbursements";
include 'header.php';

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Handle Loan Disbursement
if (isset($_POST['disburse'])) {
    $loan_id = $_POST['loan_id'];
    $type= $_POST['lease'];
    $disburse_date = $_POST['disburse_date'];
    $first_installment_date = $_POST['first_installment_date'];

    // Validate dates
    if (empty($disburse_date) || empty($first_installment_date)) {
        $_SESSION['error_message'] = "Please select both disbursement date and first installment date";
        header("Location: lease_disburse.php");
        exit();
    }

    // Check if first installment date is after disbursement date
    if (strtotime($first_installment_date) <= strtotime($disburse_date)) {
        $_SESSION['error_message'] = "First installment date must be after disbursement date";
        header("Location: lease_disburse.php");
        exit();
    }

    // Fetch loan application details
    $loanQuery = "SELECT * FROM lease_applications WHERE id = ? AND status = 'approved'";
    $loanStmt = $conn->prepare($loanQuery);
    $loanStmt->bind_param("s", $loan_id);
    $loanStmt->execute();
    $loanResult = $loanStmt->get_result();

    if ($loanResult->num_rows > 0) {
        $loanData = $loanResult->fetch_assoc();

        // Update loan status to 'disbursed' and set dates
        $updateQuery = "UPDATE lease_applications SET status = 'disbursed', disburse_date = ?, first_installment_date = ? WHERE id = ?";
        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->bind_param("sss", $disburse_date, $first_installment_date, $loan_id);
        $updateStmt->execute();

        // Generate installment schedule
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
        $total_interest = $rental_value * $installments - $loan_amount;
        $rental_value = ($loan_amount + $total_interest) / $installments;

        for ($i = 0; $i < $installments; $i++) {
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
            }

            // Insert into loan_details table
            $insertQuery = "INSERT INTO lease_details 
                          (lease_application_id, installment_number, installment_date, 
                           installment_amount, capital_due, interest_due, total_due, status) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')";
            $insertStmt = $conn->prepare($insertQuery);
            $installment_number = $i + 1;
            $total_due = $capital_due + $interest_due;
            $insertStmt->bind_param("sisdddd", $loan_id, $installment_number, $due_date, 
                                  $rental_value, $capital_due, $interest_due, $total_due);
            $insertStmt->execute();
        }

        // Create notification
        $message = "Loan $loan_id has been disbursed with first installment on $first_installment_date";
        $link = "loan_details.php?id=$loan_id";
        $notificationQuery = "INSERT INTO notifications (user_id, message, link) VALUES (?, ?, ?)";
        $notificationStmt = $conn->prepare($notificationQuery);
        $notificationStmt->bind_param("iss", $_SESSION['user_id'], $message, $link);
        $notificationStmt->execute();

        $_SESSION['disbursement_message'] = "Loan $loan_id disbursed successfully!";
        $_SESSION['voucher_loan_id'] = $loan_id; // For voucher printing
        header("Location: payment_voucher.php?loan_id=" . $loan_id . "&type=lease");
        exit();
    } else {
        $_SESSION['error_message'] = "Loan not found or not in approved status";
        header("Location: lease_disburse.php");
        exit();
    }
}

// Fetch approved loans waiting for disbursement
$result = $conn->query("SELECT ll.id, m.full_name, lp.name, ll.loan_amount, 
                        ll.installments, ll.rental_value, ll.created_at, ll.created_at AS approved_date
                        FROM lease_applications ll
                        JOIN members m ON ll.member_id = m.id
                        JOIN loan_products lp ON ll.loan_product_id = lp.id
                        WHERE ll.status = 'Approved'
                        ORDER BY ll.created_at ASC");
?>

<div class="container mt-5">
    <?php if (isset($_SESSION['disbursement_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= $_SESSION['disbursement_message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['disbursement_message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= $_SESSION['error_message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <h2><i class="fas fa-money-bill-wave me-2"></i> Approved Loans for Disbursement</h2>
    
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
                                <th>rental_value</th>
                                <th>Approved On</th>
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
                                    <td><?= number_format($row['rental_value'], 2) ?></td>
                                    <td><?= date('Y-m-d', strtotime($row['approved_date'])) ?></td>
                                    <td>
                                        <!-- Disburse Form -->
                                        <form method="POST" class="disburse-form">
                                            <input type="hidden" name="loan_id" value="<?= $row['id'] ?>">
                                            
                                            <div class="form-group me-2">
                                                <label class="small">Disburse Date</label>
                                                <input type="date" name="disburse_date" class="form-control form-control-sm" 
                                                       value="<?= date('Y-m-d') ?>" required>
                                            </div>
                                            
                                            <div class="form-group me-2">
                                                <label class="small">1st Installment</label>
                                                <input type="date" name="first_installment_date" class="form-control form-control-sm" 
                                                       value="<?= date('Y-m-d', strtotime('+1 month')) ?>" required>
                                            </div>
                                            
                                            <button type="submit" name="disburse" class="btn btn-primary btn-sm">
                                                <i class="fas fa-money-bill-wave me-1"></i> Disburse
                                            </button>
                                            
                                            <a href="loan_details.php?id=<?= $row['id'] ?>" class="btn btn-info btn-sm ms-2">
                                                <i class="fas fa-eye me-1"></i> View
                                            </a>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">No approved loans waiting for disbursement.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Set first installment date to 1 month after disbursement date by default
    $('input[name="disburse_date"]').change(function() {
        var disburseDate = new Date($(this).val());
        if (!isNaN(disburseDate.getTime())) {
            disburseDate.setMonth(disburseDate.getMonth() + 1);
            var formattedDate = disburseDate.toISOString().split('T')[0];
            $('input[name="first_installment_date"]').val(formattedDate);
        }
    });
});
</script>

<?php include 'footer.php'; ?>