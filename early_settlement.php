<?php
include "db.php";
session_start();

// Initialize variables
$loan_details = [];
$installments = [];
$payment_message = "";
$loan_type = ""; // Will be 'business_loan', 'micro_loan', or 'leasing'
$success_data = [];

// Initialize settlement form variables
$discount_percentage = 0;
$discount_amount = 0;
$settlement_amount = 0;
$original_interest = 0;

// Check for and display success message from session
if (isset($_SESSION['early_settlement_success'])) {
    $success_data = json_decode($_SESSION['early_settlement_success'], true);
    if (is_array($success_data)) {
        $payment_message = $success_data['message'];
        unset($_SESSION['early_settlement_success']);
        
        // Auto-fill search with last loan ID
        if (!isset($_POST['search_term']) && isset($success_data['loan_id'])) {
            $_POST['search_term'] = $success_data['loan_id'];
            $_POST['search'] = true;
        }
    }
}

// Handle Search
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['search'])) {
    $search_term = trim($_POST['search_term']);

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
            $query = "SELECT ll.id AS loan_id, ll.loan_amount AS loan_amount, m.full_name, m.nic, 'leasing' AS loan_type
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

        // Calculate total due for early settlement (all pending installments)
        $total_due = 0;
        $total_interest_due = 0;
        $total_capital_due = 0;
        
        foreach ($installments as $installment) {
            if ($installment['status'] === 'pending') {
                $total_due += $installment['total_due'];
                $total_interest_due += $installment['interest_due'];
                $total_capital_due += $installment['capital_due'];
            }
        }
        
        $loan_details['total_due'] = $total_due;
        $loan_details['total_interest_due'] = $total_interest_due;
        $loan_details['total_capital_due'] = $total_capital_due;
        
        // Initialize settlement values
        $original_interest = $total_interest_due;
        $settlement_amount = $total_due;
    } else {
        $payment_message = "No loan found with the provided Loan ID or NIC.";
    }
}

// Handle Early Settlement
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['settle'])) {
    $loan_id = $_POST['loan_id'];
    $loan_type = $_POST['loan_type'];
    $payment_date = $_POST['payment_date'] ?? date('Y-m-d');
    $discount_percentage = floatval($_POST['discount_percentage']);
    $discount_amount = floatval($_POST['discount_amount']);
    $settlement_amount = floatval($_POST['settlement_amount']);
    
    // Get loan details again to ensure we have fresh data
    if ($loan_type == 'business_loan') {
        $query = "SELECT la.id AS loan_id, la.loan_amount, m.full_name, m.nic 
                  FROM loan_applications la
                  JOIN members m ON la.member_id = m.id
                  WHERE la.id = ?";
    } elseif ($loan_type == 'micro_loan') {
        $query = "SELECT mla.id AS loan_id, mla.loan_amount, m.full_name, m.nic 
                  FROM micro_loan_applications mla 
                  JOIN members m ON mla.member_id = m.id
                  WHERE mla.id = ?";
    } elseif ($loan_type == 'leasing') {
        $query = "SELECT ll.id AS loan_id, ll.loan_amount, m.full_name, m.nic 
                  FROM lease_applications ll
                  JOIN members m ON ll.member_id = m.id
                  WHERE ll.id = ?";
    }
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $loan_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $loan_details = $result->fetch_assoc();
    
    // Calculate components
    $total_capital_due = floatval($_POST['total_capital_due']);
    $total_interest_due = floatval($_POST['total_interest_due']);
    $interest_paid = $total_interest_due - $discount_amount;
    $capital_paid = $total_capital_due;
    
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
        } elseif ($loan_type == 'leasing') {
            $stmt = $conn->prepare("SELECT * FROM lease_details 
                                  WHERE lease_application_id = ? AND status = 'pending' 
                                  ORDER BY installment_date ASC");
        }
        
        $stmt->bind_param("s", $loan_id);
        $stmt->execute();
        $pending_installments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        if (empty($pending_installments)) {
            throw new Exception("No pending installments found for early settlement!");
        }

        $primary_installment_id = null;

        // Mark all installments as paid
        foreach ($pending_installments as $installment) {
            $installment_id = $installment['id'];
            if ($primary_installment_id === null) {
                $primary_installment_id = $installment_id;
            }
            
            // Update the installment to mark as paid
            if ($loan_type == 'business_loan') {
                $update_stmt = $conn->prepare("UPDATE loan_details 
                    SET paid_amount = installment_amount, 
                        interest_due = 0, 
                        capital_due = 0, 
                        total_due = 0, 
                        status = 'paid', 
                        payment_date = ? 
                    WHERE id = ?");
            } elseif ($loan_type == 'micro_loan') {
                $update_stmt = $conn->prepare("UPDATE micro_loan_details 
                    SET paid_amount = installment_amount, 
                        interest_due = 0, 
                        capital_due = 0, 
                        total_due = 0, 
                        status = 'paid', 
                        payment_date = ? 
                    WHERE id = ?");
            } elseif ($loan_type == 'leasing') {
                $update_stmt = $conn->prepare("UPDATE lease_details 
                    SET paid_amount = installment_amount, 
                        interest_due = 0, 
                        capital_due = 0, 
                        total_due = 0, 
                        status = 'paid', 
                        payment_date = ? 
                    WHERE id = ?");
            }
            
            $update_stmt->bind_param("si", $payment_date, $installment_id);
            $update_stmt->execute();
        }

        // Create payment record showing the actual payment with discount
        if ($loan_type == 'micro_loan') {
            $payment_stmt = $conn->prepare("INSERT INTO micro_loan_payments 
                                        (loan_id, installment_id, amount, 
                                        interest_paid, capital_paid, payment_date, is_settlement,
                                        discount_amount, original_interest) 
                                        VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?)");
            $payment_stmt->bind_param("siddddsdd", 
                                    $loan_id, 
                                    $primary_installment_id, 
                                    $settlement_amount,
                                    $interest_paid, 
                                    $capital_paid, 
                                    $payment_date,
                                    $discount_amount, 
                                    $total_interest_due);
        } elseif ($loan_type == 'business_loan') {
            $payment_stmt = $conn->prepare("INSERT INTO payments 
                                        (loan_id, installment_id, amount, 
                                        interest_paid, capital_paid, payment_date, is_settlement,
                                        discount_amount, original_interest) 
                                        VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?)");
            $payment_stmt->bind_param("sidddsdd", 
                                    $loan_id, 
                                    $primary_installment_id, 
                                    $settlement_amount,
                                    $interest_paid, 
                                    $capital_paid, 
                                    $payment_date,
                                    $discount_amount, 
                                    $total_interest_due);
        } else { // lease_loan
            $payment_stmt = $conn->prepare("INSERT INTO lease_loan_payments 
                                        (loan_id, installment_id, amount, 
                                        interest_paid, capital_paid, payment_date, is_settlement,
                                        discount_amount, original_interest) 
                                        VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?)");
            $payment_stmt->bind_param("siddddsdd", 
                                    $loan_id, 
                                    $primary_installment_id, 
                                    $settlement_amount,
                                    $interest_paid, 
                                    $capital_paid, 
                                    $payment_date,
                                    $discount_amount, 
                                    $total_interest_due);
        }
        $payment_stmt->execute();
        
        // Update loan status to 'settled'
        if ($loan_type == 'business_loan') {
            $status_stmt = $conn->prepare("UPDATE loan_applications SET status = 'settled', settled_date = ?
                                         WHERE id = ?");
        } elseif ($loan_type == 'micro_loan') {
            $status_stmt = $conn->prepare("UPDATE micro_loan_applications SET status = 'settled', settled_date = ?
                                         WHERE id = ?");
        } elseif ($loan_type == 'leasing') {
            $status_stmt = $conn->prepare("UPDATE lease_applications SET status = 'settled', settled_date = ?
                                         WHERE id = ?");
        }
        $status_stmt->bind_param("ss", $payment_date, $loan_id);
        $status_stmt->execute();
        
        // Store success data in session
        $_SESSION['early_settlement_success'] = json_encode([
            'message' => "Early settlement recorded successfully. Full amount: Rs " . number_format($total_due, 2) . 
                        ", Paid: Rs " . number_format($settlement_amount, 2) . 
                        " (Discount: Rs " . number_format($discount_amount, 2) . ")",
            'loan_id' => $loan_id,
            'amount' => $settlement_amount,
            'date' => $payment_date,
            'loan_type' => $loan_type
        ]);
        
        $conn->commit();
        header("Location: early_settlement.php?success=1");
        exit();
        
    } catch (Exception $e) {
        $conn->rollback();
        $payment_message = "Error processing early settlement: " . $e->getMessage();
    }
}

include 'header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Early Loan Settlement</title>
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
        .settlement-summary {
            background-color: #f8f9fa;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
        }
        input[type=number] { 
            width: 100px; 
        }
        .form-group { 
            margin-bottom: 10px; 
        }
        .label { 
            display: inline-block; 
            width: 160px; 
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <h2>Early Loan Settlement</h2>
        
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

            <!-- Settlement Summary -->
            <div class="settlement-summary mb-4">
                <h4>Early Settlement Summary</h4>
                <div class="row">
                    <div class="col-md-4">
                        <p><strong>Total Capital Due:</strong> Rs <?= number_format($loan_details['total_capital_due'], 2) ?></p>
                    </div>
                    <div class="col-md-4">
                        <p><strong>Total Interest Due:</strong> Rs <?= number_format($loan_details['total_interest_due'], 2) ?></p>
                    </div>
                    <div class="col-md-4">
                        <p><strong>Total Settlement Amount:</strong> Rs <?= number_format($loan_details['total_due'], 2) ?></p>
                    </div>
                </div>
            </div>

            <!-- Settlement Form -->
            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    Confirm Early Settlement
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="loan_id" value="<?= htmlspecialchars($loan_details['loan_id']) ?>">
                        <input type="hidden" name="loan_type" value="<?= htmlspecialchars($loan_type) ?>">
                        <input type="hidden" name="total_due" value="<?= htmlspecialchars($loan_details['total_due']) ?>">
                        <input type="hidden" name="total_capital_due" value="<?= htmlspecialchars($loan_details['total_capital_due']) ?>">
                        <input type="hidden" name="total_interest_due" value="<?= htmlspecialchars($loan_details['total_interest_due']) ?>">
                        <input type="hidden" id="hidden_discount_amount" name="discount_amount" value="<?= $discount_amount ?>">
                        <input type="hidden" id="hidden_settlement_amount" name="settlement_amount" value="<?= $settlement_amount ?>">
                        
                        <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Capital Due:</label>
                            <div class="col-sm-9">
                                <input type="number" id="capital_due" class="form-control" value="<?= $loan_details['total_capital_due'] ?>" readonly>
                            </div>
                        </div>

                        <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Original Interest Due:</label>
                            <div class="col-sm-9">
                                <input type="number" id="interest_due" class="form-control" value="<?= $loan_details['total_interest_due'] ?>" readonly>
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="discount_percentage" class="col-sm-3 col-form-label">Interest Waiver (%)</label>
                            <div class="col-sm-9">
                                <input type="number" name="discount_percentage" id="discount_percentage" class="form-control" 
                                       value="<?= $discount_percentage ?>" step="0.01" min="0" max="100" oninput="calculateDiscount()">
                            </div>
                        </div>

                        <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Discount Amount:</label>
                            <div class="col-sm-9">
                                <input type="number" id="discount_amount" class="form-control" value="<?= number_format($discount_amount, 2) ?>" readonly>
                            </div>
                        </div>

                        <div class="form-group row">
                            <label class="col-sm-3 col-form-label">New Interest Due:</label>
                            <div class="col-sm-9">
                                <input type="number" id="new_interest_due" class="form-control" value="<?= number_format($loan_details['total_interest_due'] - $discount_amount, 2) ?>" readonly>
                            </div>
                        </div>

                        <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Settlement Amount:</label>
                            <div class="col-sm-9">
                                <input type="number" id="settlement_amount" class="form-control" value="<?= number_format($loan_details['total_capital_due'] + ($loan_details['total_interest_due'] - $discount_amount), 2) ?>" readonly>
                            </div>
                        </div>

                        <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Settlement Date</label>
                            <div class="col-sm-9">
                                <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>
                        <div class="form-group row">
                            <div class="col-sm-9 offset-sm-3">
                                <button type="submit" name="settle" class="btn btn-danger">Confirm Early Settlement</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Installments Table -->
            <h4>Installments to be Settled</h4>
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
                                <td><?= htmlspecialchars($installment['installment_date']) ?></td>
                                <td>Rs <?= number_format($installment['installment_amount'], 2) ?></td>
                                <td>Rs <?= number_format($installment['paid_amount'], 2) ?></td>
                                <td>Rs <?= number_format($installment['interest_due'], 2) ?></td>
                                <td>Rs <?= number_format($installment['capital_due'], 2) ?></td>
                                <td>Rs <?= number_format($installment['total_due'], 2) ?></td>
                                <td>
                                    <span class="badge bg-<?= $installment['status'] === 'paid' ? 'success' : 'warning' ?>">
                                        <?= ucfirst(htmlspecialchars($installment['status'])) ?>
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
                        <h5 class="alert-heading mb-1"><?= strpos($payment_message, 'success') !== false ? 'Settlement Successful!' : 'Settlement Error' ?></h5>
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
        // Focus on search field if showing success message
        document.addEventListener('DOMContentLoaded', function() {
            if (document.querySelector('.alert-success')) {
                document.querySelector('[name="search_term"]').focus();
            }
            
            // Initialize calculation
            calculateDiscount();
        });
        
        function calculateDiscount() {
            const capitalDue = parseFloat(document.getElementById('capital_due').value);
            const interestDue = parseFloat(document.getElementById('interest_due').value);
            const discountPercent = parseFloat(document.getElementById('discount_percentage').value) || 0;
            
            // Validate discount percentage
            const validatedDiscountPercent = Math.min(Math.max(discountPercent, 0), 100);
            if (discountPercent !== validatedDiscountPercent) {
                document.getElementById('discount_percentage').value = validatedDiscountPercent;
            }
            
            // Calculate amounts
            const discountAmount = (interestDue * validatedDiscountPercent) / 100;
            const newInterestDue = interestDue - discountAmount;
            const settlementAmount = capitalDue + newInterestDue;
            
            // Update display fields
            document.getElementById('discount_amount').value = discountAmount.toFixed(2);
            document.getElementById('new_interest_due').value = newInterestDue.toFixed(2);
            document.getElementById('settlement_amount').value = settlementAmount.toFixed(2);
            
            // Update hidden fields
            document.getElementById('hidden_discount_amount').value = discountAmount;
            document.getElementById('hidden_settlement_amount').value = settlementAmount;
        }
    </script>
</body>
</html>