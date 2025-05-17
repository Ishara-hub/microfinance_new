<?php
include "db.php";

// Check if loan ID is provided

$loan_id = $_GET['id'];

// Fetch detailed loan information
$loanQuery = "SELECT 
                la.*, 
                m.full_name, 
                m.nic, 
                m.phone, 
                m.address,
                m.gender,
                lp.name AS product_name,
                lp.interest_rate,
                lp.repayment_method,
                lp.interest_type,
                b.name AS branch_name,
                u.username AS credit_officer
              FROM loan_applications la
              JOIN members m ON la.member_id = m.id
              JOIN loan_products lp ON la.loan_product_id = lp.id
              JOIN branches b ON la.branch = b.id
              JOIN users u ON la.credit_officer = u.id
              WHERE la.id = ?";

$stmt = $conn->prepare($loanQuery);
$stmt->bind_param("i", $loan_id);
$stmt->execute();
$loan = $stmt->get_result()->fetch_assoc();

if (!$loan) {
    die("Loan not found");
}

// Calculate loan details
$total_repayment = $loan['rental_value'] * $loan['installments'];
$total_interest = $total_repayment - $loan['loan_amount'];
$monthly_interest = $total_interest / $loan['installments'];
?>

<div class="loan-details">
    <div class="row">
        <div class="col-md-6">
            <h5 class="mb-3"><i class="fas fa-user me-2"></i>Member Information</h5>
            <table class="table table-sm table-bordered">
                <tr>
                    <th width="40%">Member Name</th>
                    <td><?= htmlspecialchars($loan['full_name']) ?></td>
                </tr>
                <tr>
                    <th>NIC Number</th>
                    <td><?= htmlspecialchars($loan['nic']) ?></td>
                </tr>
                <tr>
                    <th>Phone</th>
                    <td><?= htmlspecialchars($loan['phone']) ?></td>
                </tr>
                <tr>
                    <th>Email</th>
                    <td><?= htmlspecialchars($loan['gender']) ?></td>
                </tr>
                <tr>
                    <th>Address</th>
                    <td><?= htmlspecialchars($loan['address']) ?></td>
                </tr>
            </table>
        </div>
        
        <div class="col-md-6">
            <h5 class="mb-3"><i class="fas fa-file-invoice-dollar me-2"></i>Loan Information</h5>
            <table class="table table-sm table-bordered">
                <tr>
                    <th width="40%">Loan ID</th>
                    <td><?= htmlspecialchars($loan['id']) ?></td>
                </tr>
                <tr>
                    <th>Loan Product</th>
                    <td><?= htmlspecialchars($loan['product_name']) ?></td>
                </tr>
                <tr>
                    <th>Loan Amount</th>
                    <td>Rs. <?= number_format($loan['loan_amount'], 2) ?></td>
                </tr>
                <tr>
                    <th>Installments</th>
                    <td><?= $loan['installments'] ?> months</td>
                </tr>
                <tr>
                    <th>Monthly Payment</th>
                    <td>Rs. <?= number_format($loan['rental_value'], 2) ?></td>
                </tr>
                <tr>
                    <th>Total Repayment</th>
                    <td>Rs. <?= number_format($total_repayment, 2) ?></td>
                </tr>
                <tr>
                    <th>Total Interest</th>
                    <td>Rs. <?= number_format($total_interest, 2) ?></td>
                </tr>
                <tr>
                    <th>Interest Rate</th>
                    <td><?= $loan['interest_rate'] ?>% (<?= ucfirst(str_replace('_', ' ', $loan['interest_type'])) ?>)</td>
                </tr>
                <tr>
                    <th>Repayment Method</th>
                    <td><?= ucfirst($loan['repayment_method']) ?></td>
                </tr>
                <tr>
                    <th>Branch</th>
                    <td><?= htmlspecialchars($loan['branch_name']) ?></td>
                </tr>
                <tr>
                    <th>Credit Officer</th>
                    <td><?= htmlspecialchars($loan['credit_officer']) ?></td>
                </tr>
            </table>
        </div>
    </div>
    
    <?php if (!empty($loan['attachments'])): ?>
    <div class="row mt-3">
        <div class="col-md-12">
            <h5 class="mb-3"><i class="fas fa-paperclip me-2"></i>Attachments</h5>
            <div class="d-flex flex-wrap gap-2">
                <?php 
                $attachments = json_decode($loan['attachments'], true);
                foreach ($attachments as $attachment): ?>
                    <a href="<?= htmlspecialchars($attachment['path']) ?>" 
                       target="_blank" 
                       class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-file-<?= getFileIcon($attachment['type']) ?> me-1"></i>
                        <?= htmlspecialchars($attachment['name']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php
// Helper function to get appropriate file icon
function getFileIcon($type) {
    $icons = [
        'pdf' => 'pdf',
        'image' => 'image',
        'word' => 'word',
        'excel' => 'excel',
        'text' => 'alt'
    ];
    
    foreach ($icons as $key => $icon) {
        if (strpos($type, $key) !== false) {
            return $icon;
        }
    }
    
    return 'alt';
}
?>