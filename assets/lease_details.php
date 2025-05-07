<?php
include "db.php";
session_start();

if (!isset($_GET['id'])) {
    header("Location: loan_approval.php");
    exit();
}

$lease_id = $_GET['id'];
$leaseQuery = "SELECT la.*, m.full_name, m.email, m.phone, lp.name as product_name, lp.interest_rate
               FROM lease_applications la
               JOIN members m ON la.member_id = m.id
               JOIN loan_products lp ON la.loan_product_id = lp.id
               WHERE la.id = ?";
$leaseStmt = $conn->prepare($leaseQuery);
$leaseStmt->bind_param("s", $lease_id);
$leaseStmt->execute();
$leaseResult = $leaseStmt->get_result();

if ($leaseResult->num_rows == 0) {
    header("Location: loan_approval.php");
    exit();
}

$leaseData = $leaseResult->fetch_assoc();

// Get lease installments
$installmentsQuery = "SELECT * FROM lease_applications 
                      WHERE lease_application_id = ? 
                      ORDER BY installments";
$installmentsStmt = $conn->prepare($installmentsQuery);
$installmentsStmt->bind_param("s", $lease_id);
$installmentsStmt->execute();
$installmentsResult = $installmentsStmt->get_result();

$page_title = "Lease Details - " . $lease_id;
include 'header.php';
?>

<div class="container mt-5">
    <div class="row">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h4>Lease Application Details</h4>
                </div>
                <div class="card-body">
                    <dl class="row">
                        <dt class="col-sm-5">Lease ID:</dt>
                        <dd class="col-sm-7">LS-<?= $leaseData['id'] ?></dd>
                        
                        <dt class="col-sm-5">Member Name:</dt>
                        <dd class="col-sm-7"><?= $leaseData['full_name'] ?></dd>
                        
                        <dt class="col-sm-5">Lease Product:</dt>
                        <dd class="col-sm-7"><?= $leaseData['product_type'] ?></dd>
                        
                        <dt class="col-sm-5">Asset Description:</dt>
                        <dd class="col-sm-7"><?= $leaseData['asset_description'] ?></dd>
                        
                        <dt class="col-sm-5">Asset Value:</dt>
                        <dd class="col-sm-7"><?= number_format($leaseData['asset_value'], 2) ?></dd>
                        
                        <dt class="col-sm-5">Lease Amount:</dt>
                        <dd class="col-sm-7"><?= number_format($leaseData['loan_amount'], 2) ?></dd>
                        
                        <dt class="col-sm-5">Lease Duration:</dt>
                        <dd class="col-sm-7"><?= $leaseData['installments'] ?> months</dd>
                        
                        <dt class="col-sm-5">Monthly Payment:</dt>
                        <dd class="col-sm-7"><?= number_format($leaseData['monthly_payment'], 2) ?></dd>
                        
                        <dt class="col-sm-5">Interest Rate:</dt>
                        <dd class="col-sm-7"><?= $leaseData['interest_rate'] ?>%</dd>
                        
                        <dt class="col-sm-5">Status:</dt>
                        <dd class="col-sm-7">
                            <span class="status-<?= strtolower($leaseData['status']) ?>">
                                <?= ucfirst($leaseData['status']) ?>
                            </span>
                        </dd>
                        
                        <dt class="col-sm-5">Created At:</dt>
                        <dd class="col-sm-7"><?= date('Y-m-d H:i', strtotime($leaseData['created_at'])) ?></dd>
                    </dl>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h4>Payment Schedule</h4>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Due Date</th>
                                    <th>Amount</th>
                                    <th>Principal</th>
                                    <th>Interest</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($installment = $installmentsResult->fetch_assoc()) { ?>
                                    <tr>
                                        <td><?= $installment['installments'] ?></td>
                                        <td><?= $installment['installment_date'] ?></td>
                                        <td><?= number_format($installment['installment_amount'], 2) ?></td>
                                        <td><?= number_format($installment['capital_due'], 2) ?></td>
                                        <td><?= number_format($installment['interest_due'], 2) ?></td>
                                        <td>
                                            <span class="status-<?= $installment['status'] ?>">
                                                <?= ucfirst($installment['status']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="text-center mb-4">
        <a href="loan_approval.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to Approvals
        </a>
    </div>
</div>

<?php include 'footer.php'; ?>