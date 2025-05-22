<?php
include "db.php";

// At the top of the file
$language = isset($_GET['lang']) ? $_GET['lang'] : 'si'; // Default to English
$available_languages = ['en', 'si']; // Add more languages as needed
$strings = [
    'en' => [
        'receipt_title' => 'OFFICIAL PAYMENT RECEIPT',
        'thank_you' => 'Thank you for your payment!'
    ],
    'si' => [
        'receipt_title' => 'නිල ගෙවීම් රිසිට්පත',
        'thank_you' => 'ගෙවීමට ස්තූතියි!'
    ]
];
$payment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($payment_id <= 0) {
    header("Location: manage_payments.php?error=Invalid payment ID");
    exit();
}
$type = isset($_GET['type']) ? $_GET['type'] : 'regular';
$allowed_types = ['regular', 'lease', 'micro'];

if (!in_array($type, $allowed_types)) {
    header("Location: manage_payments.php?error=Invalid payment type");
    exit();
}

switch ($type) {
    case 'regular':
        $payment_query = $conn->prepare("SELECT 
                                        p.*, 
                                        m.full_name, 
                                        m.address, 
                                        m.phone, 
                                        m.nic,
                                        la.loan_amount, 
                                        la.id as loan_number,
                                        la.interest_rate
                                        FROM payments p
                                        JOIN loan_applications la ON p.loan_id = la.id
                                        JOIN members m ON la.member_id = m.id
                                        WHERE p.id = ?");
        break;

    case 'lease':
        $payment_query = $conn->prepare("SELECT 
                                        p.*, 
                                        m.full_name, 
                                        m.address, 
                                        m.phone, 
                                        m.nic,
                                        ll.loan_amount, 
                                        ll.id as loan_number,
                                        ll.interest_rate
                                        FROM lease_loan_payments p
                                        JOIN lease_applications ll ON p.loan_id = ll.id
                                        JOIN members m ON ll.member_id = m.id
                                        WHERE p.id = ?");
        break;

    case 'micro':
        $payment_query = $conn->prepare("SELECT 
                                        p.*, 
                                        m.full_name, 
                                        m.address, 
                                        m.phone, 
                                        m.nic,
                                        ml.loan_amount, 
                                        ml.id as loan_number,
                                        ml.interest_rate
                                        FROM micro_loan_payments p
                                        JOIN micro_loan_applications ml ON p.micro_loan_id = ml.id
                                        JOIN members m ON ml.member_id = m.id
                                        WHERE p.id = ?");
        break;
}

$payment_query->bind_param("i", $payment_id);
$payment_query->execute();
$payment = $payment_query->get_result()->fetch_assoc();

if (!$payment) {
    error_log("Payment not found - ID: $payment_id, Type: $type");
    header("Location: manage_payments.php?error=Payment record not found");
    exit();
}

// Fetch installment details
$installment = null;
$installment_date = 'N/A';

if ($type === 'regular' && isset($payment['installment_id'])) {
    $installment_query = $conn->prepare("SELECT * FROM loan_details WHERE id = ?");
    $installment_query->bind_param("i", $payment['installment_id']);
    $installment_query->execute();
    $installment = $installment_query->get_result()->fetch_assoc();
    $installment_date = $installment ? date('d/m/Y', strtotime($installment['installment_date'])) : 'N/A';
}

if ($type === 'micro' && isset($payment['installment_id'])) {
    $installment_query = $conn->prepare("SELECT * FROM micro_loan_details WHERE id = ?");
    $installment_query->bind_param("i", $payment['installment_id']);
    $installment_query->execute();
    $installment = $installment_query->get_result()->fetch_assoc();
    $installment_date = $installment ? date('d/m/Y', strtotime($installment['installment_date'])) : 'N/A';
}

if ($type === 'lease' && isset($payment['installment_id'])) {
    $installment_query = $conn->prepare("SELECT * FROM lease_details WHERE id = ?");
    $installment_query->bind_param("i", $payment['installment_id']);
    $installment_query->execute();
    $installment = $installment_query->get_result()->fetch_assoc();
    $installment_date = $installment ? date('d/m/Y', strtotime($installment['installment_date'])) : 'N/A';
}
$payment_date = date('d/m/Y', strtotime($payment['payment_date']));

$receipt_titles = [
    'regular' => 'OFFICIAL PAYMENT RECEIPT',
    'lease'   => 'LEASE LOAN RECEIPT',
    'micro'   => 'MICRO LOAN RECEIPT'
];
$loan_prefix = [
    'regular' => 'LN-',
    'lease'   => 'LL-',
    'micro'   => 'ML-'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Receipt - <?= $payment_id ?></title>
    <style>
        /* Thermal printer friendly styling */
        body {
            font-family: 'Courier New', monospace;
            margin: 0;
            padding: 5px;
            font-size: 14px;
            color: #000;
            background: #fff;
        }
        .receipt {
            width: 80mm;
            margin: 0 auto;
            padding: 10px;
        }
        .header {
            text-align: center;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 1px dashed #000;
        }
        .company-name {
            font-weight: bold;
            font-size: 18px;
            margin-bottom: 5px;
        }
        .receipt-title {
            font-size: 16px;
            margin: 5px 0;
        }
        .receipt-info {
            margin: 5px 0;
            font-size: 12px;
        }
        .divider {
            border-top: 1px dashed #000;
            margin: 10px 0;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin: 3px 0;
        }
        .detail-label {
            font-weight: bold;
        }
        .footer {
            margin-top: 15px;
            text-align: center;
            font-size: 12px;
            border-top: 1px dashed #000;
            padding-top: 10px;
        }
        .signature {
            margin-top: 20px;
            display: flex;
            justify-content: space-between;
        }
        @media print {
            .no-print {
                display: none;
            }
            body {
                padding: 0;
            }
            .receipt {
                width: 100%;
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <div class="receipt">
        <div class="header">
            <div style="text-align: center;">
                <img src="assets/images/mf.png" alt="Company Logo" style="max-width: 150px; max-height: 250px; ">
            </div>
            <div class="company-name">OSHADI INVESTMENT (Pvt) Ltd </div>
            <div class="receipt-title"><?= $strings[$language]['receipt_title'] ?></div>

            <div class="receipt-info">
                <div> PIGALA ROAD, PELAWATTA</div>
                <div>Tel: 0768 605 734 | Reg No: MF12345</div>
            </div>
        </div>

        <div class="divider"></div>

        <div class="detail-row">
            <span class="detail-label">Receipt No:</span>
            <span><?= $loan_prefix[$type] ?><?= str_pad($payment['installment_id'], 6, '0', STR_PAD_LEFT) ?></span>

        </div>
        <div class="detail-row">
            <span class="detail-label">Date:</span>
            <span><?= $payment_date ?></span>
        </div>
        
        <div class="divider"></div>

        <div class="detail-row">
            <span class="detail-label">Client Name:</span>
            <span><?= htmlspecialchars($payment['full_name']) ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">NIC No:</span>
            <span><?= htmlspecialchars($payment['nic']) ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Loan No:</span>
            <span><?= $loan_prefix[$type] ?><?= str_pad($payment['loan_number'], 6, '0', STR_PAD_LEFT) ?></span>

        </div>
        
        <div class="divider"></div>

        <div class="detail-row">
            <span class="detail-label">Total Paid:</span>
            <span>Rs <?= number_format($payment['amount'], 2) ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Capital Portion:</span>
            <span>Rs <?= number_format($payment['capital_paid'], 2) ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Interest Portion:</span>
            <span>Rs <?= number_format($payment['interest_paid'], 2) ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Interest Rate:</span>
            <span><?= number_format($payment['interest_rate'], 2) ?>%</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Installment Date:</span>
            <span><?= $installment_date ?></span>
        </div>
        
        <div class="divider"></div>

        <div class="detail-row">
            <span class="detail-label">Payment Method:</span>
            <span>CASH</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Received By:</span>
            <span>SYSTEM USER</span>
        </div>

        <div class="footer">
            <div>** This is a computer generated receipt **</div>
            <div>Thank you for your payment!</div>
            <div><?= $strings[$language]['thank_you'] ?></div><div style="text-align:center; margin-top:10px;" class="no-print">
                <a href="?id=<?= $payment_id ?>&type=<?= $type ?>&lang=en">English</a> | 
                <a href="?id=<?= $payment_id ?>&type=<?= $type ?>&lang=si">සිංහල</a>
            </div>
            <div class="no-print" style="margin-top: 15px;">
                
                <button onclick="window.print()" style="padding: 5px 10px;">Print Receipt</button>
                <button onclick="window.close()" style="padding: 5px 10px;">Close Window</button>
            </div>
        </div>

        <div class="signature">
            <div style="width: 50%; text-align: center;">
                <div style="border-top: 1px solid #000; width: 80%; margin: 0 auto;"></div>
                <div>Client Signature</div>
            </div>
            <div style="width: 50%; text-align: center;">
                <div style="border-top: 1px solid #000; width: 80%; margin: 0 auto;"></div>
                <div>Authorized Signature</div>
            </div>
        </div>
        
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