<?php
ob_start();
include "db.php";
session_start();

// Check if loan ID is provided
if (!isset($_GET['id']) || !preg_match('/^[A-Z0-9-]+$/i', $_GET['id'])) {
    $_SESSION['error_message'] = "Invalid loan ID";
    header("Location: loan_approval.php");
    exit();
}

$loan_id = $_GET['id'];
$current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'summary';

// Set page title
$page_title = "Loan Details - $loan_id";

// Include header
include 'header.php';

// Fetch loan application details with additional information
$loanQuery = "SELECT 
                la.*, 
                m.full_name, m.phone, m.initials, m.address, m.nic,
                lp.name AS product_name, lp.product_type, lp.interest_rate, 
                lp.repayment_method, 
                u.user_type AS credit_officer,
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

if ($loanResult->num_rows === 0) {
    $_SESSION['error_message'] = "Loan application not found";
    header("Location: loan_approval.php");
    exit();
}

$loanData = $loanResult->fetch_assoc();

// Calculate loan summary values
$agreed_amount = $loanData['installments'] * $loanData['rental_value'];
$total_paid = 0;
$total_outstanding = 0;
$arrears = 0;

// Fetch all installments
$installmentQuery = "SELECT * FROM loan_details 
                     WHERE loan_application_id = ? 
                     ORDER BY installment_number ASC";
$installmentStmt = $conn->prepare($installmentQuery);
$installmentStmt->bind_param("s", $loan_id);
$installmentStmt->execute();
$installmentResult = $installmentStmt->get_result();
$installments = $installmentResult->fetch_all(MYSQLI_ASSOC);

// Calculate payment summary
foreach ($installments as $installment) {
    if ($installment['status'] == 'paid') {
        $total_paid += $installment['total_due'];
    } else {
        $total_outstanding += $installment['total_due'];
        if (strtotime($installment['installment_date']) < time()) {
            $arrears += $installment['total_due'];
        }
    }
}

// Fetch all payments
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
// Fetch ledger entries
//$ledgerQuery = "SELECT * FROM loan_ledger 
//                WHERE loan_id = ? 
//                ORDER BY transaction_date DESC, id DESC";
//$ledgerStmt = $conn->prepare($ledgerQuery);
//$ledgerStmt->bind_param("s", $loan_id);
//$ledgerStmt->execute();
//$ledgerResult = $ledgerStmt->get_result();
//$ledgerEntries = $ledgerResult->fetch_all(MYSQLI_ASSOC);

// Fetch documents
//$docQuery = "SELECT * FROM loan_documents 
//             WHERE loan_id = ? 
//             ORDER BY upload_date DESC";
//$docStmt = $conn->prepare($docQuery);
//$docStmt->bind_param("s", $loan_id);
//$docStmt->execute();
//$docResult = $docStmt->get_result();
//$documents = $docResult->fetch_all(MYSQLI_ASSOC);
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
        .document-card {
            transition: all 0.3s ease;
        }
        .document-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
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
                    Loan #<?= htmlspecialchars($loan_id) ?>
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
                <a href="loan_approval.php" class="btn btn-outline-secondary me-2">
                    <i class="fas fa-arrow-left me-1"></i> Back
                </a>
                <button class="btn btn-primary" onclick="window.print()">
                    <i class="fas fa-print me-1"></i> Print
                </button>
            </div>
        </div>

        <!-- Loan Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card summary-card primary">
                    <div class="card-body">
                        <h6 class="card-title text-muted">Agreed Amount</h6>
                        <h3 class="text-primary"><?= number_format($agreed_amount, 2) ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card summary-card success">
                    <div class="card-body">
                        <h6 class="card-title text-muted">Total Paid</h6>
                        <h3 class="text-success"><?= number_format($total_paid, 2) ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card summary-card warning">
                    <div class="card-body">
                        <h6 class="card-title text-muted">Outstanding</h6>
                        <h3 class="text-warning"><?= number_format($total_outstanding, 2) ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card summary-card danger">
                    <div class="card-body">
                        <h6 class="card-title text-muted">Arrears</h6>
                        <h3 class="text-danger"><?= number_format($arrears, 2) ?></h3>
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
                <button class="nav-link <?= $current_tab == 'ledger' ? 'active' : '' ?>" 
                        id="ledger-tab" data-bs-toggle="tab" data-bs-target="#ledger" 
                        type="button" role="tab" aria-controls="ledger" aria-selected="false">
                    <i class="fas fa-book me-1"></i> Ledger
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $current_tab == 'documents' ? 'active' : '' ?>" 
                        id="documents-tab" data-bs-toggle="tab" data-bs-target="#documents" 
                        type="button" role="tab" aria-controls="documents" aria-selected="false">
                    <i class="fas fa-file-alt me-1"></i> Documents
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
                                        <i class="fas fa-envelope me-1"></i> <?= htmlspecialchars($loanData['initials']) ?>
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
                                            </tr>
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
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Ledger Tab -->
            <div class="tab-pane fade <?= $current_tab == 'ledger' ? 'show active' : '' ?>" 
                 id="ledger" role="tabpanel" aria-labelledby="ledger-tab">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-book me-2"></i>
                                Loan Ledger
                            </h5>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if (count($ledgerEntries) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Date</th>
                                            <th>Transaction</th>
                                            <th>Debit</th>
                                            <th>Credit</th>
                                            <th>Balance</th>
                                            <th>product_type</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $running_balance = 0;
                                        foreach ($ledgerEntries as $entry): 
                                            $running_balance += $entry['debit'] - $entry['credit'];
                                        ?>
                                            <tr>
                                                <td><?= date('M d, Y', strtotime($entry['transaction_date'])) ?></td>
                                                <td><?= htmlspecialchars($entry['transaction_type']) ?></td>
                                                <td><?= $entry['debit'] > 0 ? number_format($entry['debit'], 2) : '-' ?></td>
                                                <td><?= $entry['credit'] > 0 ? number_format($entry['credit'], 2) : '-' ?></td>
                                                <td><?= number_format($running_balance, 2) ?></td>
                                                <td><?= htmlspecialchars($entry['product_type']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-info-circle fa-3x text-info mb-3"></i>
                                <h5>No ledger entries found</h5>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Documents Tab -->
            <div class="tab-pane fade <?= $current_tab == 'documents' ? 'show active' : '' ?>" 
                 id="documents" role="tabpanel" aria-labelledby="documents-tab">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-file-alt me-2"></i>
                                Loan Documents
                            </h5>
                            <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#uploadModal">
                                <i class="fas fa-upload me-1"></i> Upload
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (count($documents) > 0): ?>
                            <div class="row">
                                <?php foreach ($documents as $doc): ?>
                                    <div class="col-md-4 mb-3">
                                        <div class="card document-card h-100">
                                            <div class="card-body text-center">
                                                <?php 
                                                $file_ext = pathinfo($doc['file_path'], PATHINFO_EXTENSION);
                                                $icon = 'fa-file';
                                                if (in_array($file_ext, ['pdf'])) $icon = 'fa-file-pdf text-danger';
                                                elseif (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif'])) $icon = 'fa-file-image text-info';
                                                elseif (in_array($file_ext, ['doc', 'docx'])) $icon = 'fa-file-word text-primary';
                                                elseif (in_array($file_ext, ['xls', 'xlsx'])) $icon = 'fa-file-excel text-success';
                                                ?>
                                                <i class="fas <?= $icon ?> fa-3x mb-3"></i>
                                                <h6><?= htmlspecialchars($doc['document_name']) ?></h6>
                                                <small class="text-muted d-block mb-2">
                                                    <?= date('M d, Y', strtotime($doc['upload_date'])) ?>
                                                </small>
                                                <div class="btn-group">
                                                    <a href="uploads/<?= htmlspecialchars($doc['file_path']) ?>" 
                                                       target="_blank" 
                                                       class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-eye me-1"></i> View
                                                    </a>
                                                    <a href="uploads/<?= htmlspecialchars($doc['file_path']) ?>" 
                                                       download 
                                                       class="btn btn-sm btn-outline-secondary">
                                                        <i class="fas fa-download me-1"></i> Download
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-info-circle fa-3x text-info mb-3"></i>
                                <h5>No documents uploaded yet</h5>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload Document Modal -->
    <div class="modal fade" id="uploadModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="upload_document.php" enctype="multipart/form-data">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title">Upload Document</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="loan_id" value="<?= $loan_id ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Document Name</label>
                            <input type="text" class="form-control" name="document_name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Document Type</label>
                            <select class="form-select" name="document_type" required>
                                <option value="">Select type</option>
                                <option value="application">Application Form</option>
                                <option value="agreement">Loan Agreement</option>
                                <option value="id_proof">ID Proof</option>
                                <option value="address_proof">Address Proof</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">File</label>
                            <input type="file" class="form-control" name="document_file" required>
                            <small class="text-muted">Max size: 5MB (PDF, JPG, PNG, DOC, XLS)</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Upload</button>
                    </div>
                </form>
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