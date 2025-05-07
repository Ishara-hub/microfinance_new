<?php
session_start();
require "db.php";

// Check if payment ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid payment ID";
    header("Location: payments.php");
    exit();
}

$payment_id = intval($_GET['id']);

// Get payment header information
$stmt = $conn->prepare("
    SELECT gj.*, u.username as created_by_name 
    FROM general_journal gj
    LEFT JOIN users u ON gj.created_by = u.id
    WHERE gj.id = ?
");
$stmt->bind_param("i", $payment_id);
$stmt->execute();
$result = $stmt->get_result();
$payment = $result->fetch_assoc();

if (!$payment) {
    $_SESSION['error'] = "Payment not found";
    header("Location: payments.php");
    exit();
}

// Get journal entries
$entries = $conn->query("
    SELECT je.*, coa.account_code, coa.account_name, 
           sa.sub_account_code, sa.sub_account_name
    FROM journal_entries je
    LEFT JOIN chart_of_accounts coa ON je.account_id = coa.id
    LEFT JOIN sub_accounts sa ON je.sub_account_id = sa.id
    WHERE je.journal_id = $payment_id
    ORDER BY je.debit DESC, je.credit DESC
");

// Calculate totals
$debit_total = 0;
$credit_total = 0;

while ($entry = $entries->fetch_assoc()) {
    $debit_total += $entry['debit'];
    $credit_total += $entry['credit'];
}

// Reset pointer for entries to use again in display
$entries->data_seek(0);

include 'header.php';
?>

<div class="container mt-4">
    <div class="card shadow">
        <div class="d-flex justify-content-between align-items-center">
            <h2><i class="fas fa-receipt me-2"></i>Payment Voucher</h2>
            <div>
                <a href="print_vaucher.php?id=<?= $payment_id ?>" class="btn btn-light me-2" target="_blank">
                    <i class="fas fa-print me-1"></i> Print Voucher
                </a>
                <a href="payments.php" class="btn btn-light">
                    <i class="fas fa-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>
        
        <div class="card-body">
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
            <?php endif; ?>
            
            <!-- Voucher Header -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <h4>Company Name</h4>
                    <p>123 Business Street<br>City, Country</p>
                </div>
                <div class="col-md-6 text-end">
                    <h4>Payment Voucher</h4>
                    <p><strong>Voucher No:</strong> <?= htmlspecialchars($payment['reference']) ?></p>
                    <p><strong>Date:</strong> <?= date('d/m/Y', strtotime($payment['transaction_date'])) ?></p>
                </div>
            </div>
            
            <!-- Payment Details -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Description</th>
                                    <th>Account</th>
                                    <th>Sub Account</th>
                                    <th class="text-end">Debit (Rs.)</th>
                                    <th class="text-end">Credit (Rs.)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($entry = $entries->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($entry['description']) ?></td>
                                    <td>
                                        <?= htmlspecialchars($entry['account_code']) ?> - 
                                        <?= htmlspecialchars($entry['account_name']) ?>
                                    </td>
                                    <td>
                                        <?php if ($entry['sub_account_code']): ?>
                                            <?= htmlspecialchars($entry['sub_account_code']) ?> - 
                                            <?= htmlspecialchars($entry['sub_account_name']) ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end"><?= number_format($entry['debit'], 2) ?></td>
                                    <td class="text-end"><?= number_format($entry['credit'], 2) ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <td colspan="3" class="text-end"><strong>Total</strong></td>
                                    <td class="text-end"><strong><?= number_format($debit_total, 2) ?></strong></td>
                                    <td class="text-end"><strong><?= number_format($credit_total, 2) ?></strong></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Additional Information -->
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Prepared By:</strong> <?= htmlspecialchars($payment['created_by_name']) ?></p>
                </div>
                <div class="col-md-6 text-end">
                    <p><strong>Amount in Words:</strong> 
                        <?= numberToWords($debit_total) ?> Rupees Only
                    </p>
                </div>
            </div>
            
            <!-- Approval Section -->
            <div class="row mt-5">
                <div class="col-md-4 text-center">
                    <p class="border-top pt-2">Prepared By</p>
                </div>
                <div class="col-md-4 text-center">
                    <p class="border-top pt-2">Checked By</p>
                </div>
                <div class="col-md-4 text-center">
                    <p class="border-top pt-2">Approved By</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Print Styles -->
<style>
@media print {
    body * {
        visibility: hidden;
    }
    .card, .card * {
        visibility: visible;
    }
    .card {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        border: none;
        box-shadow: none;
    }
    .no-print {
        display: none !important;
    }
    .table {
        page-break-inside: avoid;
    }
}
</style>

<?php 
// Number to words function for amount in words
function numberToWords($num) {
    $ones = array(
        0 => "Zero",
        1 => "One",
        2 => "Two",
        3 => "Three",
        4 => "Four",
        5 => "Five",
        6 => "Six",
        7 => "Seven",
        8 => "Eight",
        9 => "Nine",
        10 => "Ten",
        11 => "Eleven",
        12 => "Twelve",
        13 => "Thirteen",
        14 => "Fourteen",
        15 => "Fifteen",
        16 => "Sixteen",
        17 => "Seventeen",
        18 => "Eighteen",
        19 => "Nineteen"
    );
    
    $tens = array(
        2 => "Twenty",
        3 => "Thirty",
        4 => "Forty",
        5 => "Fifty",
        6 => "Sixty",
        7 => "Seventy",
        8 => "Eighty",
        9 => "Ninety"
    );
    
    $hundreds = array(
        "Hundred",
        "Thousand",
        "Million",
        "Billion",
        "Trillion",
        "Quadrillion"
    );
    
    $num = number_format($num, 2, ".", ",");
    $num_arr = explode(".", $num);
    $wholenum = $num_arr[0];
    $decnum = $num_arr[1];
    $whole_arr = array_reverse(explode(",", $wholenum));
    krsort($whole_arr);
    $rettxt = "";
    
    foreach ($whole_arr as $key => $i) {
        if ($i < 20) {
            $rettxt .= $ones[$i];
        } elseif ($i < 100) {
            $rettxt .= $tens[substr($i, 0, 1)];
            $rettxt .= " " . $ones[substr($i, 1, 1)];
        } else {
            $rettxt .= $ones[substr($i, 0, 1)] . " " . $hundreds[0];
            $rettxt .= " " . $tens[substr($i, 1, 1)];
            $rettxt .= " " . $ones[substr($i, 2, 1)];
        }
        if ($key > 0) {
            $rettxt .= " " . $hundreds[$key] . " ";
        }
    }
    
    if ($decnum > 0) {
        $rettxt .= " and ";
        if ($decnum < 20) {
            $rettxt .= $ones[$decnum];
        } elseif ($decnum < 100) {
            $rettxt .= $tens[substr($decnum, 0, 1)];
            $rettxt .= " " . $ones[substr($decnum, 1, 1)];
        }
    }
    
    return $rettxt;
}

include 'footer.php';
?>