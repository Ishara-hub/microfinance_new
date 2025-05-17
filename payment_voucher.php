<?php
include "db.php";
session_start();

// Language configuration
$language = isset($_GET['lang']) ? $_GET['lang'] : 'en'; // Default to English
$available_languages = ['en', 'si']; // Supported languages
$strings = [
    'en' => [
        'receipt_title' => 'OFFICIAL PAYMENT VOUCHER',
        'thank_you' => 'Thank you for your payment!',
        'receipt_no' => 'Voucher No:',
        'date' => 'Date:',
        'loan_id' => 'Loan ID:',
        'member_name' => 'Member Name:',
        'nic_no' => 'NIC No:',
        'contact_no' => 'Contact No:',
        'loan_details' => 'LOAN DETAILS',
        'loan_amount' => 'Loan Amount:',
        'total_interest' => 'Total Interest:',
        'total_repayment' => 'Total Repayment:',
        'installments' => 'Installments:',
        'monthly_payment' => 'Monthly Payment:',
        'payment_method' => 'Payment Method:',
        'notes' => 'Notes:',
        'borrower_signature' => 'Borrower\'s Signature',
        'officer_signature' => 'Authorized Officer',
        'computer_generated' => '** This is a computer generated receipt **',
        'loan_type_regular' => 'Business Loan',
        'loan_type_lease' => 'Lease Loan',
        'loan_type_micro' => 'Micro Loan'
    ],
    'si' => [
        'receipt_title' => 'නිල ගෙවීම් වව්චරය',
        'thank_you' => 'ගෙවීමට ස්තූතියි!',
        'receipt_no' => 'වව්චර් අංකය:',
        'date' => 'දිනය:',
        'loan_id' => 'ණය අංකය:',
        'member_name' => 'සාමාජිකයාගේ නම:',
        'nic_no' => 'ජා.හැඳු. අංකය:',
        'contact_no' => 'දුරකථන අංකය:',
        'loan_details' => 'ණය විස්තර',
        'loan_amount' => 'ණය මුදල:',
        'total_interest' => 'සම්පූර්ණ පොලී:',
        'total_repayment' => 'සම්පූර්ණ ගෙවීම:',
        'installments' => 'කොටස්:',
        'monthly_payment' => 'මාසික ගෙවීම:',
        'payment_method' => 'ගෙවීම් ක්‍රමය:',
        'notes' => 'සටහන්:',
        'borrower_signature' => 'කර්තෘ අත්සන',
        'officer_signature' => 'අධිකාරී නිලධාරියා',
        'computer_generated' => '** මෙය පරිගණක ජනිත රිසිට්පතකි **',
        'loan_type_regular' => 'ව්‍යාපාර ණය',
        'loan_type_lease' => 'කුලී ණය',
        'loan_type_micro' => 'සුළඟ ණය'
    ]
];

$loan_id = $_GET['loan_id'] ?? $_SESSION['voucher_loan_id'] ?? 0;
$type = isset($_GET['type']) ? $_GET['type'] : 'regular';
$allowed_types = ['regular', 'lease', 'micro'];

if (!in_array($type, $allowed_types)) {
    header("Location: manage_payments.php?error=Invalid payment type");
    exit();
}

// Fetch loan details based on type
switch ($type) {
    case 'regular':
        $loanQuery = "SELECT la.*, m.full_name, m.nic, m.phone, 
                    lp.name AS product_name, la.disburse_date, la.loan_amount,
                    la.rental_value, la.installments,
                    b.name AS branch_name, u.username AS disbursed_by
              FROM loan_applications la
              JOIN members m ON la.member_id = m.id
              JOIN loan_products lp ON la.loan_product_id = lp.id
              JOIN branches b ON la.branch = b.id
              JOIN users u ON la.credit_officer = u.id
              WHERE la.id = ?";
        $loan_type_label = $strings[$language]['loan_type_regular'];
        break;
        
    case 'lease':
        $loanQuery = "SELECT ll.*, m.full_name, m.nic, m.phone, 
                    lp.name AS product_name, ll.disburse_date, ll.loan_amount,
                    ll.rental_value, ll.installments,
                    b.name AS branch_name, u.username AS disbursed_by
              FROM lease_applications ll
              JOIN members m ON ll.member_id = m.id
              JOIN loan_products lp ON ll.loan_product_id = lp.id
              JOIN branches b ON ll.branch = b.id
              JOIN users u ON ll.credit_officer = u.id
              WHERE ll.id = ?";
        $loan_type_label = $strings[$language]['loan_type_lease'];
        break;
        
    case 'micro':
        $loanQuery = "SELECT mla.*, m.full_name, m.nic, m.phone, 
                     lp.name AS product_name, mla.disburse_date, mla.loan_amount,
                     mla.rental_value, mla.installments,
                     b.name AS branch_name, u.username AS disbursed_by
              FROM micro_loan_applications mla
              JOIN members m ON mla.member_id = m.id
              JOIN loan_products lp ON mla.loan_product_id = lp.id
              JOIN branches b ON mla.branch_id = b.id
              JOIN users u ON mla.credit_officer_id = u.id
              WHERE mla.id = ?";
        $loan_type_label = $strings[$language]['loan_type_micro'];
        break;
}

$loanStmt = $conn->prepare($loanQuery);
$loanStmt->bind_param("i", $loan_id);
$loanStmt->execute();
$loan = $loanStmt->get_result()->fetch_assoc();

if (!$loan) {
    error_log("Loan not found for ID: $loan_id, Type: $type");
    header("Location: lease_approval.php?error=Loan not found");
    exit();
}

// Calculate payment details
if ($type == 'regular') {
    // For regular loans, use stored values if available
    $total_repayment = $loan['rental_value'] * $loan['installments'];
    $total_interest = $total_repayment - $loan['loan_amount'];
    $monthly_payment = $loan['rental_value'];
} else {
    // For lease/micro loans
    $total_repayment = $loan['rental_value'] * $loan['installments'];
    $total_interest = $total_repayment - $loan['loan_amount'];
    $monthly_payment = $loan['rental_value'];
}

$page_title = "Payment Voucher - " . $loan_type_label . " $loan_id";
$payment_date = date('Y/m/d', strtotime($loan['disburse_date']));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <style>
        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            margin: 0;
            padding: 0;
            font-size: 12px;
            color: #000;
            background: #fff;
        }
        .voucher {
            width: 80mm;
            margin: 0 auto;
            padding: 5mm;
            box-sizing: border-box;
        }
        .header {
            text-align: center;
            margin-bottom: 5px;
            padding-bottom: 5px;
            border-bottom: 1px dashed #ccc;
        }
        .company-name {
            font-weight: bold;
            font-size: 14px;
            margin-bottom: 3px;
        }
        .branch-info {
            font-size: 10px;
            margin-bottom: 3px;
        }
        .receipt-title {
            font-size: 13px;
            font-weight: bold;
            margin: 5px 0;
            text-transform: uppercase;
        }
        .loan-type {
            font-size: 12px;
            font-weight: bold;
            color: #333;
            margin-bottom: 3px;
        }
        .divider {
            border-top: 1px dashed #ccc;
            margin: 5px 0;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin: 3px 0;
        }
        .detail-label {
            font-weight: bold;
            width: 40%;
        }
        .detail-value {
            width: 60%;
            text-align: right;
        }
        .payment-table {
            width: 100%;
            border-collapse: collapse;
            margin: 5px 0;
            font-size: 11px;
        }
        .payment-table th {
            background-color: #f5f5f5;
            text-align: left;
            padding: 3px;
            border: 1px solid #ddd;
        }
        .payment-table td {
            padding: 3px;
            border: 1px solid #ddd;
        }
        .amount {
            text-align: right;
        }
        .notes {
            font-size: 10px;
            margin: 5px 0;
            padding: 5px;
            background-color: #f9f9f9;
            border-radius: 3px;
        }
        .signature-area {
            display: flex;
            justify-content: space-between;
            margin-top: 15px;
        }
        .signature {
            width: 45%;
            text-align: center;
        }
        .signature-line {
            border-top: 1px dashed #000;
            margin: 15px auto 5px;
            width: 80%;
        }
        .footer {
            text-align: center;
            font-size: 9px;
            margin-top: 10px;
            color: #666;
        }
        .language-switch {
            text-align: center;
            margin-bottom: 5px;
        }
        .language-btn {
            padding: 2px 5px;
            font-size: 10px;
            margin: 0 2px;
            cursor: pointer;
            background: #f0f0f0;
            border: 1px solid #ddd;
            border-radius: 3px;
        }
        .logo {
            text-align: center;
            margin-bottom: 5px;
        }
        .logo img {
            max-height: 50px;
        }
        @media print {
            .no-print {
                display: none;
            }
            body {
                padding: 0;
            }
            .voucher {
                width: 100%;
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <div class="voucher">
        <!-- Language Switch (Non-printable) -->
        <div class="language-switch no-print">
            <button class="language-btn" onclick="window.location.href='?loan_id=<?= $loan_id ?>&type=<?= $type ?>&lang=en'">English</button>
            <button class="language-btn" onclick="window.location.href='?loan_id=<?= $loan_id ?>&type=<?= $type ?>&lang=si'">සිංහල</button>
        </div>

        <!-- Logo -->
        <div class="logo">
            <img src="assets/images/mf.png" alt="Company Logo">
        </div>

        <!-- Header -->
        <div class="header">
            <div class="company-name">FAF SOLUTION (Pvt) Ltd</div>
            <div class="branch-info"><?= htmlspecialchars($loan['branch_name']) ?></div>
            <div class="receipt-title"><?= $strings[$language]['receipt_title'] ?></div>
            <div class="loan-type"><?= $loan_type_label ?></div>
        </div>

        <!-- Voucher Info -->
        <div class="divider"></div>
        <div class="detail-row">
            <span class="detail-label"><?= $strings[$language]['receipt_no'] ?></span>
            <span class="detail-value"><?= str_pad($loan_id, 5, '0', STR_PAD_LEFT) ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label"><?= $strings[$language]['date'] ?></span>
            <span class="detail-value"><?= $payment_date ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label"><?= $strings[$language]['loan_id'] ?></span>
            <span class="detail-value"><?= strtoupper(substr($type, 0, 1)) ?>-<?= str_pad($loan_id, 6, '0', STR_PAD_LEFT) ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Disbursed By:</span>
            <span class="detail-value"><?= htmlspecialchars($loan['disbursed_by']) ?></span>
        </div>
        <div class="divider"></div>

        <!-- Member Details -->
        <div class="detail-row">
            <span class="detail-label"><?= $strings[$language]['member_name'] ?></span>
            <span class="detail-value"><?= htmlspecialchars($loan['full_name']) ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label"><?= $strings[$language]['nic_no'] ?></span>
            <span class="detail-value"><?= htmlspecialchars($loan['nic']) ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label"><?= $strings[$language]['contact_no'] ?></span>
            <span class="detail-value"><?= htmlspecialchars($loan['phone']) ?></span>
        </div>
        <div class="divider"></div>

        <!-- Loan Details -->
        <table class="payment-table">
            <tr>
                <th colspan="2"><?= $strings[$language]['loan_details'] ?></th>
            </tr>
            <tr>
                <td><?= $strings[$language]['loan_amount'] ?></td>
                <td class="amount">Rs. <?= number_format($loan['loan_amount'], 2) ?></td>
            </tr>
            <tr>
                <td><?= $strings[$language]['total_interest'] ?></td>
                <td class="amount">Rs. <?= number_format($total_interest, 2) ?></td>
            </tr>
            <tr>
                <td><?= $strings[$language]['total_repayment'] ?></td>
                <td class="amount">Rs. <?= number_format($total_repayment, 2) ?></td>
            </tr>
            <tr>
                <td><?= $strings[$language]['installments'] ?></td>
                <td class="amount">Term <?= number_format($loan['installments']) ?></td>
            </tr>
            <tr>
                <td><?= $strings[$language]['monthly_payment'] ?></td>
                <td class="amount">Rs. <?= number_format($loan['rental_value'], 2) ?></td>
            </tr>
            <tr>
                <td>Product:</td>
                <td><?= htmlspecialchars($loan['product_name']) ?></td>
            </tr>
        </table>

        <!-- Payment Method -->
        <div class="detail-row">
            <span class="detail-label"><?= $strings[$language]['payment_method'] ?></span>
            <span class="detail-value">CASH</span>
        </div>
        <div class="divider"></div>

        <!-- Notes -->
        <div class="notes">
            <strong><?= $strings[$language]['notes'] ?></strong><br>
            1. <?= $language == 'en' ? 'Late payments incur 3% penalty' : 'ප්‍රමාද ගෙවීම් සඳහා මාසිකව 3% දඩයක් අය කෙරේ' ?><br>
            2. <?= $language == 'en' ? 'Early settlement allowed' : 'කලින් ගෙවා අවසන් කිරීමට අවසර ඇත' ?><br>
            3. <?= $language == 'en' ? 'Keep this voucher for reference' : 'මෙම රිසිට්පත යොමුව සඳහා තබා ගන්න' ?>
        </div>

        <!-- Signatures -->
        <div class="signature-area">
            <div class="signature">
                <div class="signature-line"></div>
                <div><?= $strings[$language]['borrower_signature'] ?></div>
            </div>
            <div class="signature">
                <div class="signature-line"></div>
                <div><?= $strings[$language]['officer_signature'] ?></div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <?= $strings[$language]['computer_generated'] ?>
        </div>
    </div>

    <!-- Print Button -->
    <div class="no-print text-center" style="margin-top: 10px; padding: 10px;">
        <button onclick="window.print()" class="btn btn-primary" style="padding: 5px 15px; font-size: 12px;">
            <i class="fas fa-print"></i> Print Voucher
        </button>
        <a href="loan_approval.php" class="btn btn-secondary" style="padding: 5px 15px; font-size: 12px;">
            <i class="fas fa-arrow-left"></i> Back to Payments
        </a>
    </div>

    <script>
    // Auto-print with delay for better rendering
    window.onload = function() {
        setTimeout(function() {
            window.print();
        }, 300);
        
        // Close window after print (optional)
        window.onafterprint = function() {
            setTimeout(function() {
                window.close();
            }, 500);
        };
    };
    </script>
</body>
</html>

<?php include 'footer.php'; ?>