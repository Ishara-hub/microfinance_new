<?php
include "db.php";
session_start();

// Initialize variables
$loan_details = [];
$installments = [];
$payment_message = "";

// Handle Search
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['search'])) {
    $search_term = $_POST['search_term'];

    // Search for lease loans
    $query = "SELECT la.id AS loan_id, la.loan_amount, m.full_name, m.nic, v.vehicle_no
              FROM lease_applications la
              JOIN members m ON la.member_id = m.id 
              JOIN vehicles v ON la.vehicle_no = v.id
              WHERE la.id = ? OR m.nic = ? OR v.vehicle_no = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sss", $search_term, $search_term, $search_term);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $loan_details = $result->fetch_assoc();

        // Fetch installments
        $installment_query = $conn->prepare("SELECT * FROM lease_details 
                                           WHERE lease_application_id = ? 
                                           ORDER BY installment_date ASC");
        $installment_query->bind_param("i", $loan_details['loan_id']);
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
        $payment_message = "No lease loan found with the provided Loan ID, NIC, or Vehicle Number.";
    }
}

// Handle Payment
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['pay'])) {
    $loan_id = $_POST['loan_id'];
    $paid_amount = floatval($_POST['paid_amount']);
    $payment_date = $_POST['payment_date'] ?? date('Y-m-d');
    $payment_method = $_POST['payment_method'] ?? 'cash';
    $payment_reference = $_POST['payment_reference'] ?? null;
    $notes = $_POST['notes'] ?? null;
    $remaining_amount = $paid_amount;
    
    // Start transaction
    $conn->begin_transaction();

    try {
        // Get all pending installments in order
        $stmt = $conn->prepare("SELECT * FROM lease_details 
                              WHERE lease_application_id = ? AND status = 'pending' 
                              ORDER BY installment_date ASC");
        $stmt->bind_param("i", $loan_id);
        $stmt->execute();
        $pending_installments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        if (empty($pending_installments)) {
            throw new Exception("No pending installments found!");
        }

        // Generate receipt number (format: LEASE-YYYYMMDD-XXXX)
        $receipt_number = 'LEASE-' . date('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);

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
            $update_stmt = $conn->prepare("UPDATE lease_details 
                SET paid_amount = paid_amount + ?, 
                    interest_due = ?, 
                    capital_due = ?, 
                    total_due = ?, 
                    status = ?, 
                    payment_date = ? 
                WHERE id = ?");
            
            $update_stmt->bind_param("ddddssi", $paid_amount_in_row, $new_interest_due, $new_capital_due, 
                $new_total_due, $status, $payment_date, $installment_id);
            $update_stmt->execute();
        
            // Track total
            $total_interest_paid += $interest_paid;
            $total_capital_paid += $capital_paid;
        }

        // Create payment record with all fields
        $payment_stmt = $conn->prepare("INSERT INTO lease_loan_payments 
                                    (loan_id, installment_id, receipt_number, amount, 
                                    interest_paid, capital_paid, payment_method, 
                                    payment_reference, payment_date, received_by, 
                                    recorded_by, notes) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $staff_id = $_SESSION['user_id'] ?? null;
        $payment_stmt->bind_param(
            "iisdddsssiis", 
            $loan_id, 
            $primary_installment_id,
            $receipt_number,
            $paid_amount,
            $total_interest_paid, 
            $total_capital_paid,
            $payment_method,
            $payment_reference,
            $payment_date,
            $staff_id,
            $staff_id,
            $notes
        );
        $payment_stmt->execute();

        // Handle any remaining overpayment amount
        if ($remaining_amount > 0) {
            // Record overpayment as client credit
            $credit_stmt = $conn->prepare("INSERT INTO client_credits
                                         (loan_id, loan_type, amount, payment_date, notes)
                                         VALUES (?, 'lease', ?, ?, ?)");
            $credit_notes = "Overpayment from receipt $receipt_number";
            $credit_stmt->bind_param("idss", $loan_id, $remaining_amount, $payment_date, $credit_notes);
            $credit_stmt->execute();
        }

        $conn->commit();
        
        // Redirect with success message including receipt number
        header("Location: lease_loan_payment.php?success=Payment of Rs " . 
              number_format($paid_amount, 2) . " recorded successfully. Receipt #: $receipt_number");
        exit();
        
    } catch (Exception $e) {
        $conn->rollback();
        $payment_message = "Error processing payment: " . $e->getMessage();
    }
}

// Include header
include 'header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lease Loan Payment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .loan-type-badge {
            font-size: 1rem;
            padding: 0.35em 0.65em;
            background-color: #6c757d;
        }
        .table-responsive {
            max-height: 500px;
            overflow-y: auto;
        }
        .payment-method-icon {
            font-size: 1.2rem;
            margin-right: 5px;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <h2>Lease Loan Payment System</h2>
        
        <!-- Search Form -->
        <form method="POST" class="mb-4">
            <div class="row">
                <div class="col-md-6">
                    <input type="text" name="search_term" class="form-control" 
                           placeholder="Enter Lease Loan ID, NIC, or Vehicle Number" required>
                </div>
                <div class="col-md-6">
                    <button type="submit" name="search" class="btn btn-primary">
                        <i class="fas fa-search"></i> Search
                    </button>
                </div>
            </div>
        </form>

        <?php if (!empty($loan_details)): ?>
            <!-- Loan Details -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    Lease Loan Details
                    <span class="badge loan-type-badge float-end">
                        LEASE LOAN
                    </span>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Lease Loan ID:</strong> <?= htmlspecialchars($loan_details['loan_id']) ?></p>
                            <p><strong>Client Name:</strong> <?= htmlspecialchars($loan_details['full_name']) ?></p>
                            <p><strong>NIC Number:</strong> <?= htmlspecialchars($loan_details['nic']) ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Vehicle Number:</strong> <?= htmlspecialchars($loan_details['vehicle_no']) ?></p>
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
                        
                        <div class="row mb-3">
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
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Payment Method</label>
                                <select name="payment_method" class="form-select" required>
                                    <option value="cash"><i class="fas fa-money-bill-wave payment-method-icon"></i> Cash</option>
                                    <option value="check"><i class="fas fa-money-check payment-method-icon"></i> Check</option>
                                    <option value="bank_transfer"><i class="fas fa-university payment-method-icon"></i> Bank Transfer</option>
                                    <option value="mobile_money"><i class="fas fa-mobile-alt payment-method-icon"></i> Mobile Money</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Payment Reference</label>
                                <input type="text" name="payment_reference" class="form-control" 
                                       placeholder="Check #, Transaction ID, etc.">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-8">
                                <label class="form-label">Notes</label>
                                <textarea name="notes" class="form-control" rows="2"></textarea>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-12 text-end">
                                <button type="submit" name="pay" class="btn btn-primary">
                                    <i class="fas fa-credit-card"></i> Submit Payment
                                </button>
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
                            <th>#</th>
                            <th>Due Date</th>
                            <th>Amount</th>
                            <th>Paid</th>
                            <th>Interest Due</th>
                            <th>Capital Due</th>
                            <th>Total Due</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($installments as $index => $installment): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td><?= htmlspecialchars($installment['installment_date']) ?></td>
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
            <div class="alert alert-info mt-4">
                <?= htmlspecialchars($payment_message) ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success mt-4">
                <?= htmlspecialchars($_GET['success']) ?>
            </div>
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
        });
    </script>
</body>
</html>