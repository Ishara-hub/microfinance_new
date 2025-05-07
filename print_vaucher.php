<?php
session_start();
require "db.php";
require_once __DIR__ . '/vendor/autoload.php';

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

// Create PDF with Mpdf
$mpdf = new \Mpdf\Mpdf([
    'mode' => 'utf-8',
    'format' => [80, 150], // Smaller format similar to receipt
    'margin_left' => 5,
    'margin_right' => 5,
    'margin_top' => 10,
    'margin_bottom' => 10,
    'margin_header' => 5,
    'margin_footer' => 5,
]);

$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payment Voucher - '.htmlspecialchars($payment['reference']).'</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            margin: 0;
            padding: 0;
        }
        .header {
            text-align: center;
            margin-bottom: 5px;
        }
        .company-name {
            font-weight: bold;
            font-size: 12px;
            margin-bottom: 3px;
        }
        .voucher-title {
            font-size: 11px;
            font-weight: bold;
            margin: 3px 0;
        }
        .voucher-info {
            margin-bottom: 5px;
            font-size: 9px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 5px;
            font-size: 9px;
        }
        table, th, td {
            border: 0.5px solid #000;
        }
        th, td {
            padding: 3px;
            text-align: left;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .divider {
            border-top: 1px dashed #000;
            margin: 5px 0;
        }
        .signature-line {
            border-top: 0.5px solid #000;
            margin-top: 15px;
            padding-top: 2px;
            width: 100%;
        }
        .footer {
            margin-top: 10px;
            font-size: 8px;
            text-align: center;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin: 2px 0;
        }
        .detail-label {
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="company-name">FAF SOLUTION (Pvt) Ltd</div>
        <div class="voucher-title">PAYMENT VOUCHER</div>
        <div class="voucher-info">
            <div>123 Finance Street, Colombo</div>
            <div>Tel: 0112 345 678 | Reg No: MF12345</div>
        </div>
    </div>

    <div class="divider"></div>

    <div class="detail-row">
        <span class="detail-label">Voucher No:</span>
        <span>'.htmlspecialchars($payment['reference']).'</span>
    </div>
    <div class="detail-row">
        <span class="detail-label">Date:</span>
        <span>'.date('d/m/Y', strtotime($payment['transaction_date'])).'</span>
    </div>

    <div class="divider"></div>

    <table>
        <thead>
            <tr>
                <th width="40%">Description</th>
                <th width="30%">Account</th>
                <th width="15%" class="text-right">Debit</th>
                <th width="15%" class="text-right">Credit</th>
            </tr>
        </thead>
        <tbody>';

while ($entry = $entries->fetch_assoc()) {
    $html .= '
            <tr>
                <td>'.htmlspecialchars($entry['description']).'</td>
                <td>'.htmlspecialchars($entry['account_code'].' - '.substr($entry['account_name'], 0, 15)).'</td>
                <td class="text-right">'.number_format($entry['debit'], 2).'</td>
                <td class="text-right">'.number_format($entry['credit'], 2).'</td>
            </tr>';
}

$html .= '
            <tr>
                <td colspan="2" class="text-right"><strong>Total</strong></td>
                <td class="text-right"><strong>'.number_format($debit_total, 2).'</strong></td>
                <td class="text-right"><strong>'.number_format($credit_total, 2).'</strong></td>
            </tr>
        </tbody>
    </table>

    <div class="divider"></div>

    <div>
        <p><strong>Amount in Words:</strong> '.numberToWords($debit_total).' Rupees Only</p>
    </div>

    <div>
        <p><strong>Prepared By:</strong> '.htmlspecialchars($payment['created_by_name']).'</p>
    </div>

    <div style="margin-top: 20px;">
        <div class="signature">
            <div style="width: 50%; text-align: center; float: left;">
                <div class="signature-line"></div>
                <div>Prepared By</div>
            </div>
            <div style="width: 50%; text-align: center; float: right;">
                <div class="signature-line"></div>
                <div>Approved By</div>
            </div>
            <div style="clear: both;"></div>
        </div>
    </div>

    <div class="footer">
        <div>** This is a computer generated voucher **</div>
        <div>Generated on: '.date('d/m/Y H:i:s').'</div>
    </div>
</body>
</html>';

$mpdf->WriteHTML($html);

// Output the PDF
$mpdf->Output('Payment_Voucher_'.$payment['reference'].'.pdf', 'I');

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
?>