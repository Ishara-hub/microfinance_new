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

        // =============================================
        // ACCOUNTING ENTRIES BASED ON LOAN TYPE
        // =============================================
        
        // 1. Record the principal disbursement (same for all types)
        // Generate transaction ID
        $transaction_id = generate_transaction_id();
        $description = "Loan disbursement to " . $loanData['full_name'] . " (ID: $loan_id)";
        
        // Start transaction
        $conn->autocommit(FALSE);
        
        try {
            // 1. Create journal header
            $stmt = $conn->prepare("INSERT INTO general_journal 
                                  (transaction_date, reference, description, created_by) 
                                  VALUES (?, ?, ?, ?)");
            $stmt->bind_param("sssi", $disburse_date, $transaction_id, $description, $_SESSION['user_id']);
            $stmt->execute();
            $journal_id = $conn->insert_id;
            
            // 2. Record the principal disbursement (debit loans receivable)
            $stmt = $conn->prepare("INSERT INTO journal_entries 
                                  (journal_id, account_id, sub_account_id, debit, credit, description) 
                                  VALUES (?, ?, ?, ?, 0, ?)");
            
            // Get account IDs - you'll need to implement these based on your chart of accounts
            $loans_receivable_account = getAccountId('Lease loan');
            $cash_account = getAccountId('Cash in hand');
            $unearned_interest_account = getAccountId('BL - Interest Income');
            $interest_receivable_account = getAccountId('Lease loan');
            
            // Debit Loans Receivable
            $stmt->bind_param("iiids", $journal_id, $loans_receivable_account, null, $loan_amount, $description);
            $stmt->execute();
            
            // 3. Credit Cash/Bank
            $stmt->bind_param("iiids", $journal_id, $cash_account, null, 0, $loan_amount, $description);
            $stmt->execute();
            
            // 4. Handle interest based on loan type
            if ($interest_type == 'flat_rate') {
                // For flat rate loans - recognize all interest as unearned initially
                $stmt->bind_param("iiids", $journal_id, $unearned_interest_account, null, $total_interest, 0, 
                                 "Unearned interest on loan $loan_id");
                $stmt->execute();
                
                $stmt->bind_param("iiids", $journal_id, $interest_receivable_account, null, 0, $total_interest, 
                                 "Interest receivable on loan $loan_id");
                $stmt->execute();
            } elseif ($interest_type == 'reducing_balance') {
                // For reducing balance - we'll recognize interest as it accrues
                // Just set up the receivable account
                $stmt->bind_param("iiids", $journal_id, $interest_receivable_account, null, $total_interest, 0, 
                                 "Estimated interest receivable on loan $loan_id");
                $stmt->execute();
            }
            // Verify the accounting equation balances
            $check_balance = $conn->query("
                SELECT ABS(SUM(debit) - SUM(credit)) as diff 
                FROM journal_entries 
                WHERE journal_id = $journal_id
            ")->fetch_assoc();

            if ($check_balance['diff'] > 0.01) {
                throw new Exception("Journal entries do not balance. Difference: ".$check_balance['diff']);
            }
            
            // Commit transaction if everything is successful
            $conn->commit();
        
        // 3. Special handling for lease type loans
        if ($type == 'lease') {
            // Additional entries for lease accounting
            $accountingStmt->bind_param("ssiidsi", $disburse_date, "LOAN-".$loan_id, 
                                      getAccountId('leased_assets'), $loan_amount, 0, 
                                      "Leased asset recognition ".$loan_id, $loan_id);
            $accountingStmt->execute();
            
            $accountingStmt->bind_param("ssiidsi", $disburse_date, "LOAN-".$loan_id, 
                                      getAccountId('lease_liability'), 0, $loan_amount, 
                                      "Lease liability recognition ".$loan_id, $loan_id);
            $accountingStmt->execute();
        }

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

            $_SESSION['disbursement_message'] = "Loan $loan_id disbursed successfully! Transaction ID: $transaction_id";
            $_SESSION['voucher_loan_id'] = $loan_id;
            header("Location: payment_voucher.php?loan_id=" . $loan_id . "&type=lease");
            exit();
            
        } catch (Exception $e) {
            $conn->rollback();
            error_log("Loan Disbursement Error: " . $e->getMessage());
            $_SESSION['error_message'] = "Failed to record accounting entries. Error: " . $e->getMessage();
            header("Location: lease_disburse.php");
            exit();
        }
    }
}
// Helper function to get account IDs
function getAccountId($accountName) {
    global $conn;
    $query = "SELECT id FROM sub_accounts WHERE sub_account_name = ? LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $accountName);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        return $result->fetch_assoc()['id'];
    }
    throw new Exception("Account not found: " . $accountName);
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