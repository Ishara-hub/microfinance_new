<?php
include "db.php";

// Initialize variables
$payment = [];
$error = '';

// Fetch payment details
if (isset($_GET['id'])) {
    $payment_id = $_GET['id'];
    
    // Corrected query - using la.id instead of la.loan_number
    $query = "SELECT p.*, la.id AS loan_id, m.full_name 
              FROM payments p
              JOIN loan_applications la ON p.loan_id = la.id
              JOIN members m ON la.member_id = m.id
              WHERE p.id = ?";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        $error = "Prepare failed: " . $conn->error;
    } else {
        $stmt->bind_param("i", $payment_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $payment = $result->fetch_assoc();
        
        if (!$payment) {
            $error = "Payment not found";
        }
    }
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update'])) {
    $payment_id = $_POST['payment_id'];
    $amount = floatval($_POST['amount']);
    $payment_date = $_POST['payment_date'];
    
    // Update payment
    $query = "UPDATE payments 
              SET amount = ?, payment_date = ?
              WHERE id = ?";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        $error = "Prepare failed: " . $conn->error;
    } else {
        $stmt->bind_param("dsi", $amount, $payment_date, $payment_id);
        
        if ($stmt->execute()) {
            header("Location: manage_payments.php?success=Payment updated successfully");
            exit();
        } else {
            $error = "Error updating payment: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Payment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .card-header {
            font-weight: bold;
        }
        .form-label {
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-edit me-2"></i>Edit Payment</h5>
                    </div>
                    
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?= $error ?></div>
                        <?php endif; ?>
                        
                        <?php if ($payment): ?>
                        <form method="POST">
                            <input type="hidden" name="payment_id" value="<?= $payment['id'] ?>">
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Loan Reference</label>
                                    <input type="text" class="form-control" 
                                           value="LN-<?= str_pad($payment['loan_id'], 6, '0', STR_PAD_LEFT) ?>" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Client Name</label>
                                    <input type="text" class="form-control" 
                                           value="<?= htmlspecialchars($payment['full_name']) ?>" readonly>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Payment Amount (Rs)</label>
                                    <input type="number" name="amount" step="0.01" 
                                           class="form-control" value="<?= $payment['amount'] ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Payment Date</label>
                                    <input type="date" name="payment_date" 
                                           class="form-control" value="<?= $payment['payment_date'] ?>" required>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="manage_payments.php" class="btn btn-secondary me-md-2">
                                    <i class="fas fa-times me-1"></i> Cancel
                                </a>
                                <button type="submit" name="update" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i> Update Payment
                                </button>
                            </div>
                        </form>
                        <?php else: ?>
                            <div class="alert alert-warning">Payment not found</div>
                            <a href="manage_payments.php" class="btn btn-primary">
                                <i class="fas fa-arrow-left me-1"></i> Back to Payments
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>