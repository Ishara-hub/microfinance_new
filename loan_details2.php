<?php
ob_start();
include "db.php";
session_start();

// Check if loan ID is provided
if (!isset($_GET['id'])) {
    header("Location: loan_approval.php");
    exit();
}

$loan_id = $_GET['id'];

// Set page title
$page_title = "Loan Details";

// Include header
include 'header.php';

// Fetch loan application details
$loanQuery = "SELECT la.*, m.full_name, m.phone, m.address, lp.name AS product_name, 
              lp.interest_rate, lp.interest_type, lp.repayment_method
              FROM loan_applications la
              JOIN members m ON la.member_id = m.id
              JOIN loan_products lp ON la.loan_product_id = lp.id
              WHERE la.id = ?";
$loanStmt = $conn->prepare($loanQuery);
$loanStmt->bind_param("s", $loan_id);
$loanStmt->execute();
$loanResult = $loanStmt->get_result();

if ($loanResult->num_rows === 0) {
    $_SESSION['error_message'] = "Loan application not found";
    header("Location: loan_approval.php");
    exit();
}

$loanData = $loanResult->fetch_assoc();

// Fetch installment schedule
$installmentQuery = "SELECT * FROM loan_details 
                     WHERE loan_application_id = ? 
                     ORDER BY installment_number ASC";
$installmentStmt = $conn->prepare($installmentQuery);
$installmentStmt->bind_param("s", $loan_id);
$installmentStmt->execute();
$installmentResult = $installmentStmt->get_result();

// Handle Loan Approval (if coming from details page)
if (isset($_POST['approve'])) {
    // Similar approval logic as in loan_approval.php
    $updateQuery = "UPDATE loan_applications SET status = 'approved' WHERE id = ?";
    $updateStmt = $conn->prepare($updateQuery);
    $updateStmt->bind_param("s", $loan_id);
    $updateStmt->execute();

    // Generate installments if not already generated
    if ($installmentResult->num_rows === 0) {
        // Insert installments (same logic as loan_approval.php)
        // ... [insert your installment generation code here] ...
    }

    $_SESSION['approval_message'] = "Loan $loan_id approved successfully!";
    header("Location: loan_details.php?id=$loan_id");
    exit();
}

// Handle Loan Rejection
if (isset($_POST['reject'])) {
    $updateQuery = "UPDATE loan_applications SET status = 'rejected' WHERE id = ?";
    $updateStmt = $conn->prepare($updateQuery);
    $updateStmt->bind_param("s", $loan_id);
    $updateStmt->execute();

    $_SESSION['approval_message'] = "Loan $loan_id rejected successfully!";
    header("Location: loan_details.php?id=$loan_id");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Loan Details - <?= htmlspecialchars($loan_id) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .loan-header {
            background-color: #f8f9fa;
            border-radius: 5px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .status-badge {
            font-size: 0.9rem;
            padding: 5px 10px;
            border-radius: 20px;
        }
        .status-pending { background-color: #fff3cd; color: #856404; }
        .status-approved { background-color: #d4edda; color: #155724; }
        .status-rejected { background-color: #f8d7da; color: #721c24; }
        .installment-table th {
            background-color: #f1f1f1;
        }
        .paid-installment {
            background-color: #e6ffe6;
        }
        .overdue-installment {
            background-color: #ffebeb;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <?php if (isset($_SESSION['approval_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?= $_SESSION['approval_message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['approval_message']); ?>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>
                <i class="fas fa-file-invoice-dollar me-2"></i>
                Loan Details: <?= htmlspecialchars($loan_id) ?>
            </h2>
            <a href="loan_approval.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back to Loans
            </a>
        </div>

        <!-- Loan Summary Card -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Loan Application Summary
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <dl class="row">
                            <dt class="col-sm-4">Member Name:</dt>
                            <dd class="col-sm-8"><?= htmlspecialchars($loanData['full_name']) ?></dd>

                            <dt class="col-sm-4">Contact:</dt>
                            <dd class="col-sm-8">
                                <?= htmlspecialchars($loanData['phone']) ?><br>
                                <?= htmlspecialchars($loanData['address']) ?>
                            </dd>

                            <dt class="col-sm-4">Loan Product:</dt>
                            <dd class="col-sm-8"><?= htmlspecialchars($loanData['product_name']) ?></dd>
                        </dl>
                    </div>
                    <div class="col-md-6">
                        <dl class="row">
                            <dt class="col-sm-4">Loan Amount:</dt>
                            <dd class="col-sm-8"><?= number_format($loanData['loan_amount'], 2) ?></dd>

                            <dt class="col-sm-4">Installments:</dt>
                            <dd class="col-sm-8"><?= $loanData['installments'] ?></dd>

                            <dt class="col-sm-4">Interest Rate:</dt>
                            <dd class="col-sm-8">
                                <?= $loanData['interest_rate'] ?>%
                                (<?= ucfirst(str_replace('_', ' ', $loanData['interest_type'])) ?>)
                            </dd>

                            <dt class="col-sm-4">Repayment Method:</dt>
                            <dd class="col-sm-8"><?= ucfirst($loanData['repayment_method']) ?></dd>

                            <dt class="col-sm-4">Status:</dt>
                            <dd class="col-sm-8">
                                <span class="status-badge status-<?= strtolower($loanData['status']) ?>">
                                    <?= ucfirst($loanData['status']) ?>
                                </span>
                            </dd>
                        </dl>
                    </div>
                </div>

                <!-- Approval Buttons (only show if pending) -->
                <?php if ($loanData['status'] == 'Pending'): ?>
                    <div class="mt-4 border-top pt-3">
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="loan_id" value="<?= htmlspecialchars($loan_id) ?>">
                            <button type="submit" name="approve" class="btn btn-success me-2">
                                <i class="fas fa-check-circle me-1"></i> Approve Loan
                            </button>
                            <button type="submit" name="reject" class="btn btn-danger">
                                <i class="fas fa-times-circle me-1"></i> Reject Loan
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Installment Schedule -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-calendar-alt me-2"></i>
                    Repayment Schedule
                </h5>
            </div>
            <div class="card-body">
                <?php if ($installmentResult->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-bordered installment-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Due Date</th>
                                    <th>Amount</th>
                                    <th>Capital due</th>
                                    <th>Interest due</th>
                                    <th>Total Due</th>
                                    <th>Status</th>
                                    <th>Payment Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($installment = $installmentResult->fetch_assoc()): 
                                    $rowClass = '';
                                    if ($installment['status'] == 'paid') {
                                        $rowClass = 'paid-installment';
                                    } elseif (strtotime($installment['installment_date']) < time() && $installment['status'] != 'paid') {
                                        $rowClass = 'overdue-installment';
                                    }
                                ?>
                                    <tr class="<?= $rowClass ?>">
                                        <td><?= $installment['installment_number'] ?></td>
                                        <td><?= date('M d, Y', strtotime($installment['installment_date'])) ?></td>
                                        <td><?= number_format($installment['installment_amount'], 2) ?></td>
                                        <td><?= number_format($installment['capital_due'], 2) ?></td>
                                        <td><?= number_format($installment['interest_due'], 2) ?></td>
                                        <td><?= number_format($installment['total_due'], 2) ?></td>
                                        <td>
                                            <?php if ($installment['status'] == 'paid'): ?>
                                                <span class="badge bg-success">Paid</span>
                                            <?php elseif (strtotime($installment['installment_date']) < time()): ?>
                                                <span class="badge bg-danger">Overdue</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?= $installment['payment_date'] ? date('M d, Y', strtotime($installment['payment_date'])) : '--' ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                            <tfoot>
                                <tr class="table-active">
                                    <th colspan="2">Totals</th>
                                    <th><?= number_format(array_sum(array_column($installmentResult->fetch_all(MYSQLI_ASSOC), 'installment_amount')), 2) ?></th>
                                    <th><?= number_format(array_sum(array_column($installmentResult->fetch_all(MYSQLI_ASSOC), 'capital_due')), 2) ?></th>
                                    <th><?= number_format(array_sum(array_column($installmentResult->fetch_all(MYSQLI_ASSOC), 'interest_due')), 2) ?></th>
                                    <th colspan="3"></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php elseif ($loanData['status'] == 'approved'): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Installment schedule not generated yet. Please contact administrator.
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Installment schedule will be generated after loan approval.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Loan Documents (optional) -->
        <?php if (!empty($loanData['documents'])): ?>
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-file-alt me-2"></i>
                    Attached Documents
                </h5>
            </div>
            <div class="card-body">
                <ul class="list-group">
                    <?php foreach (json_decode($loanData['documents']) as $doc): ?>
                        <li class="list-group-item">
                            <a href="uploads/<?= htmlspecialchars($doc) ?>" target="_blank">
                                <i class="fas fa-file-pdf me-2"></i> <?= htmlspecialchars($doc) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</body>
</html>
<?php
// Include footer
include 'footer.php';
ob_end_flush();
?>