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

// Number to words function
// Enhanced number to words function with Sri Lanka formatting
function numberToWords($num) {
    $num = number_format($num, 2, '.', '');
    $parts = explode('.', $num);
    $whole = $parts[0];
    $cents = isset($parts[1]) ? $parts[1] : '00';
    
    $ones = array(
        0 => "Zero", 1 => "One", 2 => "Two", 3 => "Three", 4 => "Four",
        5 => "Five", 6 => "Six", 7 => "Seven", 8 => "Eight", 9 => "Nine",
        10 => "Ten", 11 => "Eleven", 12 => "Twelve", 13 => "Thirteen",
        14 => "Fourteen", 15 => "Fifteen", 16 => "Sixteen", 17 => "Seventeen",
        18 => "Eighteen", 19 => "Nineteen"
    );
    
    $tens = array(
        2 => "Twenty", 3 => "Thirty", 4 => "Forty", 5 => "Fifty",
        6 => "Sixty", 7 => "Seventy", 8 => "Eighty", 9 => "Ninety"
    );
    
    $formatted = "";
    
    if ($whole < 20) {
        $formatted = $ones[$whole];
    } elseif ($whole < 100) {
        $formatted = $tens[substr($whole, 0, 1)];
        if (substr($whole, 1, 1) != "0") {
            $formatted .= " " . $ones[substr($whole, 1, 1)];
        }
    } elseif ($whole < 1000) {
        $formatted = $ones[substr($whole, 0, 1)] . " Hundred";
        if (substr($whole, 1, 2) != "00") {
            $formatted .= " and " . numberToWords(substr($whole, 1, 2));
        }
    } elseif ($whole < 100000) {
        $formatted = numberToWords(substr($whole, 0, strlen($whole)-3)) . " Thousand";
        if (substr($whole, -3) != "000") {
            $formatted .= " " . numberToWords(substr($whole, -3));
        }
    } elseif ($whole < 10000000) {
        $formatted = numberToWords(substr($whole, 0, strlen($whole)-5)) . " Lakh";
        if (substr($whole, -5) != "00000") {
            $formatted .= " " . numberToWords(substr($whole, -5));
        }
    } else {
        $formatted = numberToWords(substr($whole, 0, strlen($whole)-7)) . " Crore";
        if (substr($whole, -7) != "0000000") {
            $formatted .= " " . numberToWords(substr($whole, -7));
        }
    }
    
    if ($cents > 0) {
        $formatted .= " and " . numberToWords($cents) . " Cents";
    }
    
    return $formatted;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Voucher - <?= htmlspecialchars($payment['reference']) ?></title>
    <style>
        /* Thermal printer friendly styling */
        body {
            font-family: 'Courier New', monospace;
            margin: 0;
            padding: 5px;
            font-size: 12px;
            color: #000;
            background: #fff;
            line-height: 1.3;
        }
        .receipt {
            width: 80mm;
            margin: 0 auto;
            padding: 10px;
            box-sizing: border-box;
        }
        .header {
            text-align: center;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 1px dashed #000;
        }
        .company-name {
            font-weight: bold;
            font-size: 14px;
            margin-bottom: 5px;
            text-transform: uppercase;
        }
        .voucher-title {
            font-size: 13px;
            margin: 5px 0;
            font-weight: bold;
            text-decoration: underline;
        }
        .voucher-info {
            margin: 5px 0;
            font-size: 10px;
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
            width: 40%;
        }
        .detail-value {
            width: 60%;
            text-align: right;
        }
        .payee-info {
            margin: 8px 0;
            padding: 5px;
            border: 1px dashed #ccc;
        }
        .entries-table {
            width: 100%;
            border-collapse: collapse;
            margin: 5px 0;
            font-size: 10px;
        }
        .entries-table th {
            border-bottom: 1px solid #000;
            padding: 3px;
            text-align: left;
        }
        .entries-table td {
            padding: 3px;
            text-align: left;
            vertical-align: top;
        }
        .text-right {
            text-align: right;
        }
        .total-row {
            font-weight: bold;
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
        }
        .footer {
            margin-top: 15px;
            text-align: center;
            font-size: 10px;
            border-top: 1px dashed #000;
            padding-top: 10px;
        }
        .signature {
            margin-top: 20px;
            display: flex;
            justify-content: space-between;
        }
        .signature-box {
            width: 45%;
            text-align: center;
        }
        .signature-line {
            border-top: 1px solid #000;
            width: 100%;
            margin: 15px 0 5px;
        }
        .amount-in-words {
            margin: 10px 0;
            padding: 5px;
            border: 1px dashed #ccc;
            font-size: 11px;
        }
        @media print {
            body {
                padding: 0;
            }
            .receipt {
                width: 100%;
                padding: 0;
            }
            .no-print {
                display: none;
            }
            @page {
                size: auto;
                margin: 5mm;
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
            <div class="voucher-title">PAYMENT VOUCHER</div>
            <div class="company-name">OSHADI INVESTMENT (Pvt) Ltd </div>

            <div class="receipt-info">
                <div> PIGALA ROAD, PELAWATTA</div>
                <div>Tel: 0768 605 734 | Reg No: MF12345</div>
            </div>
        </div>

        <div class="divider"></div>

        <div class="detail-row">
            <span class="detail-label">Voucher No:</span>
            <span class="detail-value"><?= htmlspecialchars($payment['reference']) ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Date:</span>
            <span class="detail-value"><?= date('d/m/Y', strtotime($payment['transaction_date'])) ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Payment Mode:</span>
            <span class="detail-value"><?= htmlspecialchars($payment['payment_method'] ?? 'Cash') ?></span>
        </div>
        
        <?php if (!empty($payment['payee_name'])): ?>
        <div class="payee-info">
            <div><strong>Payee:</strong> <?= htmlspecialchars($payment['payee_name']) ?></div>
            <?php if (!empty($payment['payee_address'])): ?>
            <div><strong>Address:</strong> <?= htmlspecialchars($payment['payee_address']) ?></div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="divider"></div>

        <table class="entries-table">
            <thead>
                <tr>
                    <th width="60%">Account Details</th>
                    <th width="20%" class="text-right">Debit (Rs.)</th>
                    <th width="20%" class="text-right">Credit (Rs.)</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($entry = $entries->fetch_assoc()): ?>
                <?php 
                    $account_name = htmlspecialchars($entry['account_code'].' - '.$entry['account_name']);
                    if ($entry['sub_account_code']) {
                        $account_name .= '<br>'.htmlspecialchars($entry['sub_account_code'].' - '.$entry['sub_account_name']);
                    }
                    if (!empty($entry['description'])) {
                        $account_name .= '<br>'.htmlspecialchars($entry['description']);
                    }
                ?>
                <tr>
                    <td><?= $account_name ?></td>
                    <td class="text-right"><?= ($entry['debit'] > 0 ? number_format($entry['debit'], 2) : '-') ?></td>
                    <td class="text-right"><?= ($entry['credit'] > 0 ? number_format($entry['credit'], 2) : '-') ?></td>
                </tr>
                <?php endwhile; ?>
                <tr class="total-row">
                    <td><strong>Total</strong></td>
                    <td class="text-right"></strong></td>
                    <td class="text-right"><strong><?= number_format($credit_total, 2) ?></strong></td>
                </tr>
            </tbody>
        </table>

        <div class="divider"></div>

        <div class="amount-in-words">
            <strong>Amount in Words:</strong><br>
            Rupees <?= ucfirst(strtolower(numberToWords($debit_total))) ?> Only
        </div>

        <div class="footer">
            <div>** This is a computer generated voucher **</div>
            <div>Generated on: <?= date('d/m/Y H:i:s') ?></div>
        </div>
        
        <div class="signature">
            <div class="signature-box">
                <div class="signature-line"></div>
                <div>Prepared By</div>
            </div>
            <div class="signature-box">
                <div class="signature-line"></div>
                <div>Approved By</div>
            </div>
        </div>
        
        <!-- Print Button -->
        <div class="no-print text-center" style="margin-top: 10px; padding: 10px;">
            <button onclick="window.print()" class="btn btn-primary" style="padding: 5px 15px; font-size: 12px; background: #4CAF50; color: white; border: none; cursor: pointer;">
                Print Voucher
            </button>
            <a href="payments.php" style="padding: 5px 15px; font-size: 12px; background: #f44336; color: white; text-decoration: none; display: inline-block;">
                Back to Payments
            </a>
        </div>
    </div>

    <script>
    // Auto-print with delay for better rendering
    window.onload = function() {
        setTimeout(function() {
            // Check if we're in an iframe
            if (window.self === window.top) {
                window.print();
            }
        }, 500);
        
        // Close window after print (optional)
        window.onafterprint = function() {
            setTimeout(function() {
                // Only close if we're not in an iframe
                if (window.self === window.top) {
                    window.close();
                }
            }, 500);
        };
    };
    </script>
</body>
</html>