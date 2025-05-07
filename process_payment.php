<?php
include "db.php";
session_start();

// Initialize variables
$loan_details = [];
$installments = [];
$payment_message = "";
$loan_type = ""; // Will be 'business_loan', 'micro_loan', or 'lease_loan'
$success_data = [];


// Check for and display success message from session
if (isset($_SESSION['payment_success'])) {
    $success_data = json_decode($_SESSION['payment_success'], true);
    if (is_array($success_data)) {
        $payment_message = $success_data['message'];
        unset($_SESSION['payment_success']);
        
        // Auto-fill search with last loan ID
        if (!isset($_POST['search_term']) && isset($success_data['loan_id'])) {
            $_POST['search_term'] = $success_data['loan_id'];
            $_POST['search'] = true;
        }
    }
}

// Handle Search
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['search'])) {
    $search_term = $_POST['search_term'];

    // First try business loans
    $query = "SELECT la.id AS loan_id, la.loan_amount, m.full_name, m.nic, 'business_loan' AS loan_type
              FROM loan_applications la
              JOIN members m ON la.member_id = m.id
              JOIN loan_products lp ON la.loan_product_id = lp.id 
              WHERE la.id = ? OR m.nic = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $search_term, $search_term);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $loan_details = $result->fetch_assoc();
        $loan_type = 'business_loan';
    } else {
        // Try micro loans
        $query = "SELECT mla.id AS loan_id, mla.loan_amount, m.full_name, m.nic, 'micro_loan' AS loan_type
                  FROM micro_loan_applications mla 
                  JOIN members m ON mla.member_id = m.id
                  JOIN loan_products lp ON mla.loan_product_id = lp.id
                  WHERE mla.id = ? OR m.nic = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ss", $search_term, $search_term);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $loan_details = $result->fetch_assoc();
            $loan_type = 'micro_loan';
        } else {
            // Try lease loans
            $query = "SELECT ll.id AS loan_id, ll.loan_amount AS loan_amount, m.full_name, m.nic, 'lease_loan' AS loan_type
                      FROM lease_applications ll
                      JOIN members m ON ll.member_id = m.id
                      JOIN loan_products lp ON ll.loan_product_id = lp.id
                      WHERE ll.id = ? OR m.nic = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ss", $search_term, $search_term);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $loan_details = $result->fetch_assoc();
                $loan_type = 'leasing';
            }
        }
    }

    if (!empty($loan_details)) {
        // Fetch installments based on loan type
        if ($loan_type == 'business_loan') {
            $installment_query = $conn->prepare("SELECT * FROM loan_details 
                                               WHERE loan_application_id = ? 
                                               ORDER BY installment_date ASC");
        } elseif ($loan_type == 'micro_loan') {
            $installment_query = $conn->prepare("SELECT * FROM micro_loan_details 
                                               WHERE micro_loan_application_id = ? 
                                               ORDER BY installment_date ASC");
        } elseif ($loan_type == 'leasing') {
            $installment_query = $conn->prepare("SELECT * FROM lease_details 
                                               WHERE lease_application_id = ? 
                                               ORDER BY installment_date ASC");
        }
        
        $installment_query->bind_param("s", $loan_details['loan_id']);
        $installment_query->execute();
        $installments = $installment_query->get_result()->fetch_all(MYSQLI_ASSOC);

        // Calculate total due
        $total_due = 0;
        foreach ($installments as $installment) {
            if ($installment['status'] === 'pending') {
                $total_due += $installment['total_due'];
            }
        }
        $loan_details['total_due'] = $total_due;
    } else {
        $payment_message = "No loan found with the provided Loan ID or NIC.";
    }
}

// Handle Payment
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['pay'])) {
    $loan_id = $_POST['loan_id'];
    $loan_type = $_POST['loan_type'];
    $paid_amount = floatval($_POST['paid_amount']);
    $payment_date = $_POST['payment_date'] ?? date('Y-m-d');
    $remaining_amount = $paid_amount;
    
    // Start transaction
    $conn->begin_transaction();

    try {
        // Get all pending installments in order
        if ($loan_type == 'business_loan') {
            $stmt = $conn->prepare("SELECT * FROM loan_details 
                                  WHERE loan_application_id = ? AND status = 'pending' 
                                  ORDER BY installment_date ASC");
        } elseif ($loan_type == 'micro_loan') {
            $stmt = $conn->prepare("SELECT * FROM micro_loan_details 
                                  WHERE micro_loan_application_id = ? AND status = 'pending' 
                                  ORDER BY installment_date ASC");
        } elseif ($loan_type == 'leasing'){ // lease_loan
            $stmt = $conn->prepare("SELECT * FROM lease_details 
                                  WHERE lease_application_id = ? AND status = 'pending' 
                                  ORDER BY installment_date ASC");
        }
        
        $stmt->bind_param("s", $loan_id);
        $stmt->execute();
        $pending_installments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        if (empty($pending_installments)) {
            throw new Exception("No pending installments found!");
        }

        // Initialize payment allocation variables
        $total_interest_paid = 0;
        $total_capital_paid = 0;
        $primary_installment_id = null;

        foreach ($pending_installments as $installment) {
            if ($remaining_amount <= 0) break;
        
            $installment_id = $installment['id'];
            if ($primary_installment_id === null) {
                $primary_installment_id = $installment_id;
            }
        
            $interest_due = floatval($installment['interest_due']);
            $capital_due = floatval($installment['capital_due']);
            $total_due = floatval($installment['total_due']);
            $paid_amount_in_row = 0;
        
            // Pay interest first
            $interest_paid = min($remaining_amount, $interest_due);
            $remaining_amount -= $interest_paid;
        
            // Then pay capital
            $capital_paid = min($remaining_amount, $capital_due);
            $remaining_amount -= $capital_paid;
        
            $new_interest_due = $interest_due - $interest_paid;
            $new_capital_due = $capital_due - $capital_paid;
            $new_total_due = $new_interest_due + $new_capital_due;
        
            $status = ($new_total_due <= 0) ? 'paid' : 'pending';
            $paid_amount_in_row = $interest_paid + $capital_paid;
        
            // Update the installment
            if ($loan_type == 'business_loan') {
                $update_stmt = $conn->prepare("UPDATE loan_details 
                    SET paid_amount = paid_amount + ?,
                        interest_due = ?,
                        capital_due = ?, 
                        total_due = ?, 
                        status = ?, 
                        paid_date = ? 
                    WHERE id = ?");
                $update_stmt->bind_param("ddddssi", $paid_amount_in_row, $new_interest_due, $new_capital_due, 
                $new_total_due, $status, $payment_date, $installment_id);

            } elseif ($loan_type == 'micro_loan') {
                $update_stmt = $conn->prepare("UPDATE micro_loan_details 
                    SET paid_amount = paid_amount + ?,
                        interest_due = ?,
                        capital_due = ?, 
                        total_due = ?, 
                        status = ?, 
                        payment_date = ? 
                    WHERE id = ?");
                $update_stmt->bind_param("ddddssi", $paid_amount_in_row, $new_interest_due, $new_capital_due, 
                $new_total_due, $status, $payment_date, $installment_id);

            } elseif ($loan_type == 'leasing') { // lease_loan
                $update_stmt = $conn->prepare("UPDATE lease_details
                    SET paid_amount = paid_amount + ?,
                        interest_due = ?,
                        capital_due = ?, 
                        total_due = ?, 
                        status = ?, 
                        paid_date = ? 
                    WHERE id = ?");
                $update_stmt->bind_param("ddddssi", $paid_amount_in_row, $new_interest_due, $new_capital_due, 
                $new_total_due, $status, $payment_date, $installment_id);
            }
            
            $update_stmt->execute();

            // Track total
            $total_interest_paid += $interest_paid;
            $total_capital_paid += $capital_paid;
        }

        // Create payment record in the appropriate table
        if ($loan_type == 'micro_loan') {
            $payment_stmt = $conn->prepare("INSERT INTO micro_loan_payments 
                                        (loan_id, installment_id, amount, 
                                        interest_paid, capital_paid, payment_date) 
                                        VALUES (?, ?, ?, ?, ?, ?)");
            $payment_stmt->bind_param("siddds", $loan_id, $primary_installment_id, $paid_amount,
                                    $total_interest_paid, $total_capital_paid, $payment_date);
            $payment_stmt->execute();                        
        } elseif ($loan_type == 'business_loan') {
            $payment_stmt = $conn->prepare("INSERT INTO payments 
                                        (loan_id, installment_id, amount, 
                                        interest_paid, capital_paid, payment_date) 
                                        VALUES (?, ?, ?, ?, ?, ?)");
            $payment_stmt->bind_param("siddds", $loan_id, $primary_installment_id, $paid_amount,
                                    $total_interest_paid, $total_capital_paid, $payment_date);
            $payment_stmt->execute();                        
        } else { // lease_loan
            $payment_stmt = $conn->prepare("INSERT INTO lease_loan_payments 
                                        (loan_id, installment_id, amount, 
                                        interest_paid, capital_paid, payment_date) 
                                        VALUES (?, ?, ?, ?, ?, ?)");
            $payment_stmt->bind_param("siddds", $loan_id, $primary_installment_id, $paid_amount,
                                    $total_interest_paid, $total_capital_paid, $payment_date);
            $payment_stmt->execute();
        }
        
        

        // Handle any remaining overpayment amount
        if ($remaining_amount > 0) {
            $credit_stmt = $conn->prepare("INSERT INTO client_credits
                                         (loan_id, loan_type, amount, payment_date)
                                         VALUES (?, ?, ?, ?)");
            $credit_stmt->bind_param("isds", $loan_id, $loan_type, $remaining_amount, $payment_date);
            $credit_stmt->execute();
        }
        
        // Store success data in session
        $_SESSION['payment_success'] = json_encode([
            'message' => "Payment of Rs " . number_format($paid_amount, 2) . " recorded successfully",
            'loan_id' => $loan_id,
            'amount' => $paid_amount,
            'date' => $payment_date,
            'loan_type' => $loan_type
        ]);
        
        $conn->commit();
        header("Location: manage_payments.php?success=Payment of Rs " . number_format($paid_amount, 2) . " recorded successfully");
        exit();
        
    } catch (Exception $e) {
        $conn->rollback();
        $payment_message = "Error processing payment: " . $e->getMessage();
    }
}

include 'header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loan Payment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .loan-type-badge {
            font-size: 1rem;
            padding: 0.35em 0.65em;
        }
        .alert-success {
            border-left: 4px solid #2e7d32;
            background-color: #edf7ed;
        }
        .alert-success .bi {
            color: #2e7d32;
            flex-shrink: 0;
        }
        .alert-heading {
            font-size: 1.1rem;
            color: #1e561f;
        }
        .small {
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <h2>Loan Payment</h2>
        
        <!-- Search Form -->
        <form method="POST" class="mb-4">
            <div class="row">
                <div class="col-md-6">
                    <input type="text" name="search_term" class="form-control" 
                           placeholder="Enter Loan ID or NIC" required
                           value="<?= isset($_POST['search_term']) ? htmlspecialchars($_POST['search_term']) : '' ?>">
                </div>
                <div class="col-md-6">
                    <button type="submit" name="search" class="btn btn-primary">Search</button>
                </div>
            </div>
        </form>

        <?php if (!empty($loan_details)): ?>
            <!-- Loan Details -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    Loan Details
                    <span class="badge <?= $loan_type == 'business_loan' ? 'bg-info' : ($loan_type == 'micro_loan' ? 'bg-warning' : 'bg-secondary') ?> loan-type-badge float-end">
                        <?= strtoupper(str_replace('_', ' ', $loan_type)) ?> LOAN
                    </span>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Loan ID:</strong> <?= htmlspecialchars($loan_details['loan_id']) ?></p>
                            <p><strong>Client Name:</strong> <?= htmlspecialchars($loan_details['full_name']) ?></p>
                            <p><strong>NIC Number:</strong> <?= htmlspecialchars($loan_details['nic']) ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Loan Amount:</strong> Rs <?= number_format($loan_details['loan_amount'], 2) ?></p>
                            <p><strong>Total Due:</strong> Rs <?= number_format($loan_details['total_due'], 2) ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payment Form -->
            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    Make Payment
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="loan_id" value="<?= htmlspecialchars($loan_details['loan_id']) ?>">
                        <input type="hidden" name="loan_type" value="<?= htmlspecialchars($loan_type) ?>">
                        <div class="row">
                            <div class="col-md-4">
                                <label class="form-label">Payment Amount (Rs)</label>
                                <input type="number" name="paid_amount" step="0.01" min="0.01"
                                       max="<?= $loan_details['total_due'] ?>" 
                                       class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Payment Date</label>
                                <input type="date" name="payment_date" 
                                       class="form-control" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" name="pay" class="btn btn-primary">Submit Payment</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Installments Table -->
            <h4>Installments</h4>
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead class="table-dark">
                        <tr>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Paid</th>
                            <th>Interest Due</th>
                            <th>Capital Due</th>
                            <th>Total Due</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($installments as $installment): ?>
                            <tr>
                                <td><?= $installment['installment_date'] ?></td>
                                <td>Rs <?= number_format($installment['installment_amount'], 2) ?></td>
                                <td>Rs <?= number_format($installment['paid_amount'], 2) ?></td>
                                <td>Rs <?= number_format($installment['interest_due'], 2) ?></td>
                                <td>Rs <?= number_format($installment['capital_due'], 2) ?></td>
                                <td>Rs <?= number_format($installment['total_due'], 2) ?></td>
                                <td>
                                    <span class="badge bg-<?= $installment['status'] === 'paid' ? 'success' : 'warning' ?>">
                                        <?= ucfirst($installment['status']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if (!empty($payment_message)): ?>
            <div class="alert alert-<?= strpos($payment_message, 'success') !== false ? 'success' : 'danger' ?> alert-dismissible fade show mt-4" role="alert">
                <div class="d-flex align-items-center">
                    <?php if (strpos($payment_message, 'success') !== false): ?>
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-check-circle-fill me-2" viewBox="0 0 16 16">
                            <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/>
                        </svg>
                    <?php else: ?>
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-exclamation-triangle-fill me-2" viewBox="0 0 16 16">
                            <path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767L8.982 1.566zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5zm.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/>
                        </svg>
                    <?php endif; ?>
                    <div>
                        <h5 class="alert-heading mb-1"><?= strpos($payment_message, 'success') !== false ? 'Payment Successful!' : 'Payment Error' ?></h5>
                        <p class="mb-0"><?= htmlspecialchars($payment_message) ?></p>
                        <?php if (!empty($success_data) && is_array($success_data) && strpos($payment_message, 'success') !== false): ?>
                            <div class="mt-2 small">
                                <div>Loan ID: <?= htmlspecialchars($success_data['loan_id']) ?></div>
                                <div>Amount: Rs <?= number_format($success_data['amount'], 2) ?></div>
                                <div>Date: <?= htmlspecialchars($success_data['date']) ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <script>
                // Auto-dismiss after 5 seconds
                setTimeout(() => {
                    const alert = document.querySelector('.alert');
                    if (alert) {
                        const bsAlert = new bootstrap.Alert(alert);
                        bsAlert.close();
                    }
                }, 5000);
            </script>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Set max payment amount to total due when page loads
        document.addEventListener('DOMContentLoaded', function() {
            const paidAmountInput = document.querySelector('input[name="paid_amount"]');
            if (paidAmountInput && <?= !empty($loan_details) ? 'true' : 'false' ?>) {
                paidAmountInput.max = <?= !empty($loan_details) ? $loan_details['total_due'] : 0 ?>;
            }
            
            // Focus on search field if showing success message
            if (document.querySelector('.alert-success')) {
                document.querySelector('[name="search_term"]').focus();
            }
        });
    </script>
</body>
</html>