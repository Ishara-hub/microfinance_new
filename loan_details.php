<?php
ob_start();
include "db.php";
session_start();

function getInstallmentTable($loan_type) {
    switch ($loan_type) {
        case 'micro': 
            return 'micro_loan_details';
        case 'lease': 
            return 'lease_details';
        default: 
            return 'loan_details';
    }
}

function getPaymentTable($loan_type) {
    switch ($loan_type) {
        case 'micro': 
            return 'micro_loan_payments';
        case 'lease': 
            return 'lease_loan_payments';
        default: 
            return 'payments';
    }
}
// Check if loan ID is provided and determine loan type from prefix
if (!isset($_GET['id'])) {
    $_SESSION['error_message'] = "Loan ID not provided";
    header("Location: loan_approval.php");
    exit();
}

$loan_id = $_GET['id'];
$current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'summary';

// Determine loan type from ID prefix
$loan_type = 'regular'; // default
if (preg_match('/^ML/i', $loan_id)) {
    $loan_type = 'micro';
} elseif (preg_match('/^LL/i', $loan_id)) {
    $loan_type = 'lease';
}

// Validate loan ID format based on type
$valid = false;
switch ($loan_type) {
    case 'regular':
        $valid = preg_match('/^BL\d{3,}$/i', $loan_id);
        break;
    case 'micro':
        $valid = preg_match('/^ML\d{4,}$/i', $loan_id);
        break;
    case 'lease':
        $valid = preg_match('/^LL\d{3,}$/i', $loan_id);
        break;
}

if (!$valid) {
    $_SESSION['error_message'] = "Invalid loan ID format for $loan_type loan";
    header("Location: loan_approval.php");
    exit();
}

// Set page title
$page_title = "Loan Details - $loan_id";

// Include header
include 'header.php';

// Initialize variables
$loanData = [];
$installments = [];
$payments = [];
$penalties = [];
$agreed_amount = 0;
$total_paid = 0;
$total_outstanding = 0;
$arrears = 0;
$totalPenalties = 0;
$totalPaidPenalties = 0;
$totalUnpaidPenalties = 0;

// Fetch loan data based on type
if ($loan_type == 'regular') {
    // Regular business loan
    $loanQuery = "SELECT 
                    la.*, 
                    m.full_name, m.phone, m.initials, m.address, m.nic,
                    lp.name AS product_name, lp.product_type, lp.interest_rate, 
                    lp.repayment_method, 
                    u.first_name AS credit_officer,
                    b.name AS branch_name
                  FROM loan_applications la
                  JOIN members m ON la.member_id = m.id
                  JOIN loan_products lp ON la.loan_product_id = lp.id
                  LEFT JOIN users u ON la.credit_officer = u.id
                  LEFT JOIN branches b ON la.branch = b.id
                  WHERE la.id = ?";
    
    $loanStmt = $conn->prepare($loanQuery);
    $loanStmt->bind_param("s", $loan_id);
    $loanStmt->execute();
    $loanResult = $loanStmt->get_result();
    
    if ($loanResult->num_rows > 0) {
        $loanData = $loanResult->fetch_assoc();
        
        // Calculate loan summary values
        $agreed_amount = $loanData['installments'] * $loanData['rental_value'];
        
        // Fetch installments
        $installmentQuery = "SELECT * FROM loan_details 
                            WHERE loan_application_id = ? 
                            ORDER BY installment_number ASC";
        $installmentStmt = $conn->prepare($installmentQuery);
        $installmentStmt->bind_param("s", $loan_id);
        $installmentStmt->execute();
        $installmentResult = $installmentStmt->get_result();
        $installments = $installmentResult->fetch_all(MYSQLI_ASSOC);
        
        // Fetch payments
        $paymentQuery = "SELECT * FROM payments 
                        WHERE loan_id = ? 
                        ORDER BY payment_date DESC";
        $paymentStmt = $conn->prepare($paymentQuery);
        $paymentStmt->bind_param("s", $loan_id);
        $paymentStmt->execute();
        $paymentResult = $paymentStmt->get_result();
        $payments = $paymentResult->fetch_all(MYSQLI_ASSOC);

        // Second: Get the total payment amount
        $totalQuery = "SELECT SUM(amount) AS total_amount FROM payments WHERE loan_id = ?";
        $totalStmt = $conn->prepare($totalQuery);
        $totalStmt->bind_param("s", $loan_id);
        $totalStmt->execute();
        $totalResult = $totalStmt->get_result();
        $totalAmount = $totalResult->fetch_assoc();
        $total_paid = $totalAmount['total_amount'] ?? 0;

        // Get penalties for this loan
        $penalties_query = "SELECT p.*, 
        ld.installment_number,
        ld.installment_date as due_date
        FROM penalties p
        LEFT JOIN " . getInstallmentTable($loan_type) . " ld ON p.installment_id = ld.id
        WHERE p.loan_id = ?
        ORDER BY p.penalty_date DESC";
        $stmt = $conn->prepare($penalties_query);
        $stmt->bind_param("s", $loan_id);
        $stmt->execute();
        $penalties = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        // Calculate penalty totals
        $totalPenalties = array_sum(array_column($penalties, 'penalty_amount'));
        $totalPaidPenalties = array_sum(array_column(array_filter($penalties, function($p) { 
            return $p['is_paid']; 
        }), 'penalty_amount'));
        $totalUnpaidPenalties = $totalPenalties - $totalPaidPenalties;
    }
} elseif ($loan_type == 'micro') {
    // Micro loan
    $loanQuery = "SELECT 
                    ml.*, 
                    m.full_name, m.phone, m.initials, m.address, m.nic,
                    lp.name AS product_name, lp.product_type, lp.interest_rate, 
                    lp.repayment_method, 
                    u.first_name AS credit_officer,
                    b.name AS branch_name
                  FROM micro_loan_applications ml
                  JOIN members m ON ml.member_id = m.id
                  JOIN loan_products lp ON ml.loan_product_id = lp.id
                  LEFT JOIN users u ON ml.credit_officer_id = u.id
                  LEFT JOIN branches b ON ml.branch_id = b.id
                  WHERE ml.id = ?";
    
    $loanStmt = $conn->prepare($loanQuery);
    $loanStmt->bind_param("s", $loan_id);
    $loanStmt->execute();
    $loanResult = $loanStmt->get_result();
    
    if ($loanResult->num_rows > 0) {
        $loanData = $loanResult->fetch_assoc();
        
        // Calculate loan summary values
        $agreed_amount = $loanData['installments'] * $loanData['rental_value'];
        
        // Fetch installments
        $installmentQuery = "SELECT * FROM micro_loan_details 
                            WHERE micro_loan_application_id = ? 
                            ORDER BY installment_number ASC";
        $installmentStmt = $conn->prepare($installmentQuery);
        $installmentStmt->bind_param("s", $loan_id);
        $installmentStmt->execute();
        $installmentResult = $installmentStmt->get_result();
        $installments = $installmentResult->fetch_all(MYSQLI_ASSOC);
        
        // Fetch payments
        $paymentQuery = "SELECT * FROM micro_loan_payments 
                        WHERE loan_id = ? 
                        ORDER BY payment_date DESC";
        $paymentStmt = $conn->prepare($paymentQuery);
        $paymentStmt->bind_param("s", $loan_id);
        $paymentStmt->execute();
        $paymentResult = $paymentStmt->get_result();
        $payments = $paymentResult->fetch_all(MYSQLI_ASSOC);

        // Second: Get the total payment amount
        $totalQuery = "SELECT SUM(amount) AS total_amount FROM micro_loan_payments WHERE loan_id = ?";
        $totalStmt = $conn->prepare($totalQuery);
        $totalStmt->bind_param("s", $loan_id);
        $totalStmt->execute();
        $totalResult = $totalStmt->get_result();
        $totalAmount = $totalResult->fetch_assoc();
        $total_paid = $totalAmount['total_amount'] ?? 0;

        // Get penalties for this loan
        $penalties_query = "SELECT p.*, 
                            ld.installment_number,
                            ld.installment_date as due_date
                            FROM penalties p
                            LEFT JOIN micro_loan_details ld ON p.installment_id = ld.id
                            WHERE p.loan_id = ?
                            ORDER BY p.penalty_date DESC";
            $stmt = $conn->prepare($penalties_query);
            $stmt->bind_param("s", $loan_id);
            $stmt->execute();
            $penalties = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
} elseif ($loan_type == 'lease') {
    // Lease loan
    $loanQuery = "SELECT 
                    ll.*, 
                    m.full_name, m.phone, m.initials, m.address, m.nic,
                    lp.name AS product_name, lp.product_type, lp.interest_rate, 
                    lp.repayment_method, 
                    u.first_name AS credit_officer,
                    b.name AS branch_name
                  FROM lease_applications ll
                  JOIN members m ON ll.member_id = m.id
                  JOIN loan_products lp ON ll.loan_product_id = lp.id
                  LEFT JOIN users u ON ll.credit_officer = u.id
                  LEFT JOIN branches b ON ll.branch = b.id
                  WHERE ll.id = ?";
    
    $loanStmt = $conn->prepare($loanQuery);
    $loanStmt->bind_param("s", $loan_id);
    $loanStmt->execute();
    $loanResult = $loanStmt->get_result();
    
    if ($loanResult->num_rows > 0) {
        $loanData = $loanResult->fetch_assoc();
        
        // Calculate loan summary values
        $agreed_amount = $loanData['installments'] * $loanData['rental_value'];
        
        // Fetch installments
        $installmentQuery = "SELECT * FROM lease_details 
                            WHERE lease_application_id = ? 
                            ORDER BY installment_number ASC";
        $installmentStmt = $conn->prepare($installmentQuery);
        $installmentStmt->bind_param("s", $loan_id);
        $installmentStmt->execute();
        $installmentResult = $installmentStmt->get_result();
        $installments = $installmentResult->fetch_all(MYSQLI_ASSOC);
        
        // Fetch payments
        $paymentQuery = "SELECT * FROM lease_loan_payments 
                        WHERE loan_id = ? 
                        ORDER BY payment_date DESC";
        $paymentStmt = $conn->prepare($paymentQuery);
        $paymentStmt->bind_param("s", $loan_id);
        $paymentStmt->execute();
        $paymentResult = $paymentStmt->get_result();
        $payments = $paymentResult->fetch_all(MYSQLI_ASSOC);

        // Get the total payment amount
        $totalQuery = "SELECT SUM(amount) AS total_amount FROM lease_loan_payments WHERE loan_id = ?";
        $totalStmt = $conn->prepare($totalQuery);
        $totalStmt->bind_param("s", $loan_id);
        $totalStmt->execute();
        $totalResult = $totalStmt->get_result();
        $totalAmount = $totalResult->fetch_assoc();
        $total_paid = $totalAmount['total_amount'] ?? 0;

        // Get penalties for this loan
        $penalties_query = "SELECT p.*, 
                          ld.installment_number,
                          ld.installment_date as due_date
                          FROM penalties p
                          LEFT JOIN lease_details ld ON p.installment_id = ld.id
                          WHERE p.loan_id = ?
                          ORDER BY p.penalty_date DESC";
        $stmt = $conn->prepare($penalties_query);
        $stmt->bind_param("s", $loan_id);
        $stmt->execute();
        $penalties = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        // Calculate penalty totals
        $totalPenalties = array_sum(array_column($penalties, 'penalty_amount'));
        $totalPaidPenalties = array_sum(array_column(array_filter($penalties, function($p) { 
            return $p['is_paid']; 
        }), 'penalty_amount'));
        $totalUnpaidPenalties = $totalPenalties - $totalPaidPenalties;
    }
}

// Calculate payment summary
foreach ($installments as $installment) {
    $totalDue = $installment['total_due'] + ($installment['penalty'] ?? 0);
    
    if ($installment['status'] == 'paid') {
        $total_paid += $totalDue;
    } else {
        $total_outstanding += $totalDue;
        if (strtotime($installment['installment_date']) < time()) {
            $arrears += $totalDue;
        }
    }
}



if (empty($loanData)) {
    $_SESSION['error_message'] = "Loan application not found";
    header("Location: loan_approval.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title><?= $page_title ?></title>
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
        .status-Disbursed { background-color: #d4edda; color: #6f42c1; }
        .status-rejected { background-color: #f8d7da; color: #721c24; }
        .summary-card {
            border-left: 4px solid;
            height: 100%;
        }
        .summary-card.primary { border-color: #0d6efd; }
        .summary-card.success { border-color: #198754; }
        .summary-card.warning { border-color: #ffc107; }
        .summary-card.danger { border-color: #dc3545; }
        .nav-tabs .nav-link.active {
            font-weight: bold;
            border-bottom: 3px solid #0d6efd;
        }
        .installment-table th {
            background-color: #f1f1f1;
        }
        .paid-installment {
            background-color: #e6ffe6;
        }
        .overdue-installment {
            background-color: #ffebeb;
        }
        .loan-type-badge {
            font-size: 1rem;
            padding: 0.35em 0.65em;
        }
        .regular-badge { background-color: #20c997; }
        .micro-badge { background-color: #fd7e14; }
        .lease-badge { background-color: #6f42c1; }
    </style>
</head>
<body>
    <div class="container py-4">
        <!-- Status Messages -->
        <?php if (isset($_SESSION['approval_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?= $_SESSION['approval_message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['approval_message']); ?>
        <?php endif; ?>

        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-0">
                    <i class="fas fa-file-invoice-dollar me-2"></i>
                    <?= strtoupper($loan_type) ?> Loan #<?= htmlspecialchars($loan_id) ?>
                    <span class="badge <?= $loan_type == 'regular' ? 'regular-badge' : ($loan_type == 'micro' ? 'micro-badge' : 'lease-badge') ?>">
                        <?= ucfirst($loan_type) ?> Loan
                    </span>
                </h2>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="loan_approval.php">Loan Approvals</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Loan Details</li>
                    </ol>
                </nav>
            </div>
            <div>
                <a href="loan_portfolio_report.php" class="btn btn-outline-secondary me-2">
                    <i class="fas fa-arrow-left me-1"></i> Back
                </a>
                <button class="btn btn-primary" onclick="window.print()">
                    <i class="fas fa-print me-1"></i> Print
                </button>
            </div>
        </div>

        <!-- Loan Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-2 mb-3">
                <div class="card summary-card primary">
                    <div class="card-body">
                        <h6 class="card-title text-muted">Agreed Amount</h6>
                        <h4 class="text-primary"><?= number_format($agreed_amount, 2) ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-md-2 mb-3">
                <div class="card summary-card success">
                    <div class="card-body">
                        <h6 class="card-title text-muted">Total Paid</h6>
                        <h4 class="text-success"><?= number_format($total_paid, 2) ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-md-2 mb-3">
                <div class="card summary-card warning">
                    <div class="card-body">
                        <h6 class="card-title text-muted">Outstanding</h6>
                        <h4 class="text-warning"><?= number_format($total_outstanding, 2) ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-md-2 mb-3">
                <div class="card summary-card danger">
                    <div class="card-body">
                        <h6 class="card-title text-muted">Arrears</h6>
                        <h4 class="text-danger"><?= number_format($arrears, 2) ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-md-2 mb-3">
                <div class="card summary-card danger">
                    <div class="card-body">
                        <h6 class="card-title text-muted">Total Penalties</h6>
                        <h4 class="text-danger">
                            <?= number_format(array_sum(array_column($penalties, 'penalty_amount')), 2) ?>
                        </h4>
                    </div>
                </div>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <ul class="nav nav-tabs mb-4" id="loanTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $current_tab == 'summary' ? 'active' : '' ?>" 
                        id="summary-tab" data-bs-toggle="tab" data-bs-target="#summary" 
                        type="button" role="tab" aria-controls="summary" aria-selected="true">
                    <i class="fas fa-info-circle me-1"></i> Summary
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $current_tab == 'schedule' ? 'active' : '' ?>" 
                        id="schedule-tab" data-bs-toggle="tab" data-bs-target="#schedule" 
                        type="button" role="tab" aria-controls="schedule" aria-selected="false">
                    <i class="fas fa-calendar-alt me-1"></i> Schedule
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $current_tab == 'payments' ? 'active' : '' ?>" 
                        id="payments-tab" data-bs-toggle="tab" data-bs-target="#payments" 
                        type="button" role="tab" aria-controls="payments" aria-selected="false">
                    <i class="fas fa-money-bill-wave me-1"></i> Payments
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $current_tab == 'guarantors' ? 'active' : '' ?>" 
                        id="guarantors-tab" data-bs-toggle="tab" data-bs-target="#guarantors" 
                        type="button" role="tab" aria-controls="guarantors" aria-selected="false">
                    <i class="fas fa-user-shield me-1"></i> Guarantors
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $current_tab == 'penalties' ? 'active' : '' ?>" 
                        id="penalties-tab" data-bs-toggle="tab" data-bs-target="#penalties" 
                        type="button" role="tab" aria-controls="penalties" aria-selected="false">
                    <i class="fas fa-exclamation-triangle me-1"></i> Penalties
                </button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="loanTabsContent">
            <!-- Summary Tab -->
            <div class="tab-pane fade <?= $current_tab == 'summary' ? 'show active' : '' ?>" 
                 id="summary" role="tabpanel" aria-labelledby="summary-tab">
                <div class="row">
                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-user me-2"></i>
                                    Member Information
                                </h5>
                            </div>
                            <div class="card-body">
                                <dl class="row">
                                    <dt class="col-sm-4">Full Name:</dt>
                                    <dd class="col-sm-8"><?= htmlspecialchars($loanData['full_name']) ?></dd>

                                    <dt class="col-sm-4">NIC Number:</dt>
                                    <dd class="col-sm-8"><?= htmlspecialchars($loanData['nic']) ?></dd>

                                    <dt class="col-sm-4">Contact:</dt>
                                    <dd class="col-sm-8">
                                        <i class="fas fa-phone me-1"></i> <?= htmlspecialchars($loanData['phone']) ?><br>
                                    </dd>

                                    <dt class="col-sm-4">Address:</dt>
                                    <dd class="col-sm-8"><?= htmlspecialchars($loanData['address']) ?></dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Loan Information
                                </h5>
                            </div>
                            <div class="card-body">
                                <dl class="row">
                                    <dt class="col-sm-4">Loan Product:</dt>
                                    <dd class="col-sm-8"><?= htmlspecialchars($loanData['product_name']) ?></dd>

                                    <dt class="col-sm-4">Loan Amount:</dt>
                                    <dd class="col-sm-8"><?= number_format($loanData['loan_amount'], 2) ?></dd>

                                    <dt class="col-sm-4">Installments:</dt>
                                    <dd class="col-sm-8"><?= $loanData['installments'] ?> months</dd>

                                    <dt class="col-sm-4">Interest Rate:</dt>
                                    <dd class="col-sm-8"><?= $loanData['interest_rate'] ?>%</dd>

                                    <dt class="col-sm-4">Rental Value:</dt>
                                    <dd class="col-sm-8"><?= number_format($loanData['rental_value'], 2) ?></dd>

                                    <dt class="col-sm-4">Repayment Method:</dt>
                                    <dd class="col-sm-8"><?= ucfirst($loanData['repayment_method']) ?></dd>

                                    <dt class="col-sm-4">Credit Officer:</dt>
                                    <dd class="col-sm-8"><?= htmlspecialchars($loanData['credit_officer']) ?></dd>

                                    <dt class="col-sm-4">Branch:</dt>
                                    <dd class="col-sm-8"><?= htmlspecialchars($loanData['branch_name']) ?></dd>

                                    <dt class="col-sm-4">Status:</dt>
                                    <dd class="col-sm-8">
                                        <span class="status-badge status-<?= strtolower($loanData['status']) ?>">
                                            <?= ucfirst($loanData['status']) ?>
                                        </span>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Schedule Tab -->
            <div class="tab-pane fade <?= $current_tab == 'schedule' ? 'show active' : '' ?>" 
                 id="schedule" role="tabpanel" aria-labelledby="schedule-tab">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-calendar-alt me-2"></i>
                                Repayment Schedule
                            </h5>
                            <span class="badge bg-white text-primary">
                                <?= count($installments) ?> Installments
                            </span>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if (count($installments) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Due Date</th>
                                            <th>Principal</th>
                                            <th>Interest</th>
                                            <th>Total Due</th>
                                            <th>Paid Amount</th>
                                            <th>Status</th>
                                            <th>Payment Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($installments as $installment): 
                                            $isOverdue = strtotime($installment['installment_date']) < time() && $installment['status'] != 'paid';
                                            $rowClass = $installment['status'] == 'paid' ? 'table-success' : ($isOverdue ? 'table-danger' : '');
                                        ?>
                                            <tr class="<?= $rowClass ?>">
                                                <td><?= $installment['installment_number'] ?></td>
                                                <td>
                                                    <?= date('M d, Y', strtotime($installment['installment_date'])) ?>
                                                    <?php if ($isOverdue): ?>
                                                        <span class="badge bg-danger ms-2">Overdue</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= number_format($installment['capital_due'], 2) ?></td>
                                                <td><?= number_format($installment['interest_due'], 2) ?></td>
                                                <td><?= number_format($installment['total_due'], 2) ?></td>
                                                <td><?= number_format($installment['paid_amount'], 2) ?></td>
                                                <td>
                                                    <?php if ($installment['status'] == 'paid'): ?>
                                                        <span class="badge bg-success">Paid</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-<?= $isOverdue ? 'danger' : 'warning' ?> text-dark">
                                                            <?= $isOverdue ? 'Overdue' : 'Pending' ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?= $installment['status'] == 'paid' ? date('M d, Y', strtotime($installment['paid_date'])) : '--' ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-info-circle fa-3x text-info mb-3"></i>
                                <h5>Installment schedule will be generated after approval</h5>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Payments Tab -->
            <div class="tab-pane fade <?= $current_tab == 'payments' ? 'show active' : '' ?>" 
                 id="payments" role="tabpanel" aria-labelledby="payments-tab">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-money-bill-wave me-2"></i>
                                Payment History
                            </h5>
                            <span class="badge bg-white text-primary">
                                <?= count($payments) ?> Payments
                            </span>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if (count($payments) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Date</th>
                                            <th>Receipt #</th>
                                            <th>Amount</th>
                                            <th>Principal</th>
                                            <th>Interest</th>
                                            <th>Installment</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($payments as $payment): ?>
                                            <tr>
                                                <td><?= date('M d, Y', strtotime($payment['payment_date'])) ?></td>
                                                <td><?= htmlspecialchars($payment['id']) ?></td>
                                                <td><?= number_format($payment['amount'], 2) ?></td>
                                                <td><?= number_format($payment['capital_paid'], 2) ?></td>
                                                <td><?= number_format($payment['interest_paid'], 2) ?></td>
                                                <td>#<?= $payment['installment_id'] ?></td>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-info-circle fa-3x text-info mb-3"></i>
                                <h5>No payments recorded yet</h5>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <!-- Add to your tab content -->
            <div class="tab-pane fade <?= $current_tab == 'penalties' ? 'show active' : '' ?>" 
                id="penalties" role="tabpanel" aria-labelledby="penalties-tab">
                <div class="card">
                    <div class="card-header bg-warning text-dark">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Penalty Charges
                            </h5>
                            <span class="badge bg-white text-warning">
                                Total: <?= number_format($totalUnpaidPenalties, 2) ?> (Unpaid)
                            </span>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if (count($penalties) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Date</th>
                                            <th>Installment #</th>
                                            <th>Due Date</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <th>Paid Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($penalties as $penalty): ?>
                                            <tr class="<?= $penalty['is_paid'] ? 'table-success' : 'table-warning' ?>">
                                                <td><?= date('M d, Y', strtotime($penalty['penalty_date'])) ?></td>
                                                <td>#<?= $penalty['installment_number'] ?></td>
                                                <td><?= date('M d, Y', strtotime($penalty['due_date'])) ?></td>
                                                <td><?= number_format($penalty['penalty_amount'], 2) ?></td>
                                                <td>
                                                    <?php if ($penalty['is_paid']): ?>
                                                        <span class="badge bg-success">Paid</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning text-dark">Unpaid</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?= $penalty['is_paid'] ? date('M d, Y', strtotime($penalty['payment_date'])) : '--' ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                                <h5>No penalty charges recorded</h5>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Guarantors Tab -->
            <div class="tab-pane fade <?= $current_tab == 'guarantors' ? 'show active' : '' ?>" 
                 id="guarantors" role="tabpanel" aria-labelledby="guarantors-tab">
                <div class="row">
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header bg-secondary text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-user-shield me-2"></i>
                                    Guarantor 1
                                </h5>
                            </div>
                            <div class="card-body">
                                <dl class="row">
                                    <dt class="col-sm-4">Name:</dt>
                                    <dd class="col-sm-8"><?= htmlspecialchars($loanData['guarantor1_name']) ?></dd>

                                    <dt class="col-sm-4">NIC:</dt>
                                    <dd class="col-sm-8"><?= htmlspecialchars($loanData['guarantor1_nic']) ?></dd>

                                    <dt class="col-sm-4">Mobile:</dt>
                                    <dd class="col-sm-8"><?= htmlspecialchars($loanData['guarantor1_mobile']) ?></dd>

                                    <dt class="col-sm-4">Address:</dt>
                                    <dd class="col-sm-8"><?= htmlspecialchars($loanData['guarantor1_address']) ?></dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header bg-secondary text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-user-shield me-2"></i>
                                    Guarantor 2
                                </h5>
                            </div>
                            <div class="card-body">
                                <dl class="row">
                                    <dt class="col-sm-4">Name:</dt>
                                    <dd class="col-sm-8"><?= htmlspecialchars($loanData['guarantor2_name']) ?></dd>

                                    <dt class="col-sm-4">NIC:</dt>
                                    <dd class="col-sm-8"><?= htmlspecialchars($loanData['guarantor2_nic']) ?></dd>

                                    <dt class="col-sm-4">Mobile:</dt>
                                    <dd class="col-sm-8"><?= htmlspecialchars($loanData['guarantor2_mobile']) ?></dd>

                                    <dt class="col-sm-4">Address:</dt>
                                    <dd class="col-sm-8"><?= htmlspecialchars($loanData['guarantor2_address']) ?></dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Activate tab from URL parameter
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const tabParam = urlParams.get('tab');
            
            if (tabParam) {
                const tab = new bootstrap.Tab(document.getElementById(tabParam + '-tab'));
                tab.show();
            }
        });
    </script>
</body>
</html>
<?php
include 'footer.php';
ob_end_flush();
?>