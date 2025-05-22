<?php
include "db.php";
require 'vendor/autoload.php';

// Set page title
$page_title = "Loan Agreement";
// Include header

// Check if loan application ID is provided
if (!isset($_GET['id'])) {
    die("Loan application ID is required");
}

$loan_id = $_GET['id'];

// Fetch loan application data
$loan_query = $conn->prepare("
    SELECT la.*, 
           lp.name as loan_product_name, la.rental_value,
           lp.interest_rate as product_interest_rate,
           m.full_name, m.initials, m.nic, m.address, m.phone, m.dob,
           b.name as branch_name, b.location as branch_location,
           u.username as credit_officer_name
    FROM loan_applications la
    JOIN loan_products lp ON la.loan_product_id = lp.id
    JOIN members m ON la.member_id = m.id
    JOIN branches b ON la.branch = b.id
    JOIN users u ON la.credit_officer = u.id
    WHERE la.id = ?
");
$loan_query->bind_param("s", $loan_id);
$loan_query->execute();
$loan_result = $loan_query->get_result();

if ($loan_result->num_rows == 0) {
    die("Loan application not found");
}

$loan = $loan_result->fetch_assoc();

// Calculate additional loan details
$interest_rate = $loan['interest_rate'] ?? $loan['product_interest_rate'];
$total_interest = ($loan['loan_amount'] * $interest_rate / 100) * ($loan['installments'] / 12);
$total_repayment = $loan['loan_amount'] + $total_interest;
$installment_amount = $total_repayment / $loan['installments'];

// Function to convert number to words
function numberToWords($num) {
    $ones = array(
        0 => "Zero", 1 => "One", 2 => "Two", 3 => "Three", 4 => "Four",
        5 => "Five", 6 => "Six", 7 => "Seven", 8 => "Eight", 9 => "Nine",
        10 => "Ten", 11 => "Eleven", 12 => "Twelve", 13 => "Thirteen", 14 => "Fourteen",
        15 => "Fifteen", 16 => "Sixteen", 17 => "Seventeen", 18 => "Eighteen", 19 => "Nineteen"
    );
    $tens = array(
        2 => "Twenty", 3 => "Thirty", 4 => "Forty", 5 => "Fifty",
        6 => "Sixty", 7 => "Seventy", 8 => "Eighty", 9 => "Ninety"
    );
    
    if ($num < 20) {
        return $ones[$num];
    } elseif ($num < 100) {
        return $tens[($num / 10)] . (($num % 10 != 0) ? " " . $ones[$num % 10] : "");
    } elseif ($num < 1000) {
        return $ones[($num / 100)] . " Hundred" . (($num % 100 != 0) ? " and " . numberToWords($num % 100) : "");
    } elseif ($num < 100000) {
        return numberToWords($num / 1000) . " Thousand" . (($num % 1000 != 0) ? " " . numberToWords($num % 1000) : "");
    } elseif ($num < 10000000) {
        return numberToWords($num / 100000) . " Lakh" . (($num % 100000 != 0) ? " " . numberToWords($num % 100000) : "");
    } else {
        return numberToWords($num / 10000000) . " Crore" . (($num % 10000000 != 0) ? " " . numberToWords($num % 10000000) : "");
    }
}

// Format dates
$agreement_date = date("F j, Y");
$first_payment_date = date("F j, Y", strtotime("+1 month"));

// Borrower full name
$borrower_name = $loan['initials'];
?>

<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <!-- Include Sinhala font -->
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Sinhala:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Noto Sans Sinhala', Arial, sans-serif;
            margin: 0;
            padding: 5px;
            font-size: 14px;
            color: #333;
            background: #fff;
        }
        .sinhala-text {
            font-family: 'Noto Sans Sinhala', sans-serif;
            direction: ltr;
            unicode-bidi: bidi-override;
        }
        .loan-agreement {
            font-family: 'Noto Sans Sinhala', Arial, sans-serif;
            margin: 0 auto;
            padding: 10px;
        }
        .agreement-section {
            margin-bottom: 5px;
            text-align: justify;
        }
        .agreement-section h5 {
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
            margin-bottom: 10px;
            text-align: center;
        }
        .agreement-section h6 {
            font-size: 1rem;
            font-weight: bold;
        }
        .signature-section {
            margin-top: 20px;
            display: flex;
            justify-content: space-between;
        }
        .signature-section p {
            margin-top: 5px;
            padding-top: 5px;
            width: 100%;
        }
        .text-center {
            text-align: center;
        }
        .mb-4 {
            margin-bottom: 1.5rem;
        }
        .row {
            display: flex;
            flex-wrap: wrap;
            margin-right: -15px;
            margin-left: -15px;
        }
        .col-md-6 {
            flex: 0 0 50%;
            max-width: 50%;
            padding-right: 15px;
            padding-left: 15px;
        }
        .mt-5 {
            margin-top: 3rem;
        }
        .highlight {
            font-weight: bold;
            background-color: #fffde7;
            padding: 2px 4px;
        }
        .company-header {
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .terms-list {
            list-style-type: decimal;
            padding-left: 20px;
        }
        .terms-list li {
            margin-bottom: 10px;
        }
        .footer-note {
            font-size: 0.9em;
            text-align: center;
            margin-top: 30px;
            color: #666;
        }
        @media print {
            .no-print {
                display: none;
            }
            body {
                padding: 0;
            }
            .loan-agreement {
                width: 100%;
                padding: 0;
            }
        }
    </style>
</head>
<body>
<div class="container mt-1">
    <div class="card shadow">
        <div class="card-body">
            <div class="loan-agreement">
                <div class="text-center mb-1 company-header">
                    <h3>FAF SOLUTIONS PVT LTD</h3>
                    <h3 class="sinhala-text">ණය ගිවිසුම</h3>
                    <p>ලිපිනය: පිටිගල පාර, පැලවත්ත</p>
                </div>

                <div class="agreement-section">
                    <ol class="terms-list">
                        <li>
                            ඉහත කී නයකරු ණය හිමි පක්ෂය වෙතින් තම ස්වයං රැකියා වන .............................................. දියුණු කර ගැනීමෙ කාරණාව උදෙසා රුපියල් (<?= numberToWords($loan['loan_amount']) ?> Rupees Only) (:<strong>රු.<?= number_format($loan['loan_amount'], 2) ?></strong> )ණයක් අයදුම් කර ඇති අතර.
                            ණය හිමි පක්ෂය එකී (:<strong>රු.<?= number_format($loan['loan_amount'], 2) ?></strong> ) ක මුදල ණයකරු වෙත ලබාදීමට එකඟ වේ.
                            ඉහත කී ණය මුදල ................................... කාරණාව උදෙසා පමණක් යෙදවීමට ණයකරු එකඟ වේ.
                        </li>
                        
                        <li>
                            ණය කරු එකී ණය මුදල සියයට <span class="highlight"><?= $interest_rate ?>%</span> ක වාර්ෂික පොලී අනුපාතිකයක් මත ණය හිමි පක්ෂය වෙත ගෙවීමට එකඟ වේ.
                            මුළු පොලිය: <span class="highlight">රු.<?= number_format($total_interest, 2) ?></span> (<?= numberToWords($total_interest) ?> Rupees Only)
                        </li>
                        
                        <li>
                            ඉහත කී ණය මුදල මාසික වාරිකයක් වන (:<strong>රු.<?= number_format($installment_amount, 2) ?></strong> ) බැගින් වන වාරික <strong><?= $loan['installments'] ?></strong> කින් (<?= numberToWords($loan['installments']) ?> installments) ණයකරු විසින් ගෙවා නිමකළ යුතුය.
                            පළමු වාරිකය ගෙවිය යුතු දිනය: (  <span class="highlight"><?= $first_payment_date ?></span>)
                        </li>
                        
                        <li>
                            නියමිත ණය මුදල නිසි කාලයට ගෙවීමට ණයකරු අපොහොසත වුවහොත් ප්‍රමාද වන සෑම වාරිකයක් මතම <span class="highlight">3%</span> ක දඩ පොලී අනුපාතිකයක් ණයහිමි පක්‍ෂය වෙත ගෙවීමට ණයකරු එකඟ වේ.
                        </li>
                        
                        <li>
                            මෙම ණය ගිවිසුම අනුව සහ/හෝ ඊට අදාළව ඉහත කී ලෙස ගෙවිය යුතු ණය මුදල් සහ ඊට අදාල පොලිය ණය හිමි පක්‍ෂය වෙත ගෙවීමේ පොරොන්දුව ඇතිව ණය මුදලට සුරක්‍ෂිතයක් වශයෙන් ඉහත කී ණය හිමි පක්‍ෂය වෙත මෙම ගිවිසුමෙහිම දිනැතිව අත්සන් තබන ලද පොරොන්දු නෝට්ටුවක් ලබාදීමට එකඟ වේ.
                        </li>
                        
                        <li>
                            එසේ හෙයින් මෙම මුදල් ණයට දීමේ ගිවිසුමෙහි සහ/හෝ ඉහත් කී ලෙස පොරොන්දු නෝට්ටුවේ සඳහන් නියමයන් සහ කොන්දේසි වලට අනුකුලව අදාළ ණය මුදල ඊට අදාළ පොලිය සමගින් නැවත අයකර ගැනීම සඳහා ණය හිමි පක්‍ෂය වෙත අයිතිය ඇති බව ණය කරු පිළිගනී.<br>
                            එමෙන්ම (:<strong>රු.<?= number_format($total_repayment, 2) ?></strong> )ක සම්පූර්ණ ණය මුදල සහ ඊට අදාළ පොලිය ණය හිමි පක්‍ෂය විසින් හෝ එකී සමාගමේ අනුපාප්තික බලකාර ලැබුම්කාරාදීන් විසින් එකවර ගෙවන ලෙස ඉල්ලා සිටින විටකදී එකී මුදල සඳහා වෙනත් වවුචරයක් හෝ ලේඛණයක් ඉදිරිපත් නොකල ද එකී මුදල එසේ එකවර ගේවා නිදහස් වීමට ද ණයකරු එකඟ වේ.
                        </li>
                        
                        <li>
                            තවද, මෙම මුදල් ණය දීමේ ගිවිසුම අර්ථකථනය කිරීමේ දී මෙම ණය ගිවිසුම සඳහා සුරක්‍ෂිතයක් වශයෙන් ණයකරු විසින් බාදී ඇති පොරොන්දු නෝට්ටුව මෙම මුදල් ණයටදීමේ ගිවිසුමේම කොටසක් වශයෙන් පිළිගනීමට දෙපක්‍ෂයම එකඟ වේ. එ අනුව ගෙවීම් පැහැර හැරියහොත් ණය හිමිට ලඝුකාර්යය පරිපාටිය අනුව මෙම පොදු පොරොන්දු නෝට්ටුව මත නඩු පවරා මෙම මුදල අයකර ගැනීමට හැකි බව පිළිගනී.
                        </li>
                        
                        <li>
                            මෙම ලියවිල්ලේ සඳහන් කොන්දේසි සහ පොරොන්දු කියවා තේරුම්ගෙන / කියවා තේරුම් කර දීමෙන් පසු නිසි ලෙස අවබෝධ කරෙගෙන ගිවිසිලි හරි ආකාරව ඉෂ්ඨ කිරීම පිණිස ඉහත කී දෙපක්‍ෂය ඔවුනොවුන්ගේ උරුමක්කාර, පොල්මක්කාර, අද්මිනිස්ත්‍රාසිකාර, බලාකාර ලැබුම්කාර, අනුපාප්තිකාදීන් සමගින් මෙයින් බැදී මෙම මුදල් ණයට දීමේ ගිවිසුමට වර්ෂ .............. ක් වූ ........ මස ...... දින .............. දී අත්සන් තබන ලදී.
                        </li>
                    </ol>
                </div>

                <div class="signature-section ">
                    <div class="col-md-6">
                        <p>_________________________<br>
                        <span class="sinhala-text">ණය දෙන්නා</span><br>
                    </div>
                    <div class="col-md-6">
                        <p>_________________________<br>
                        <span class="sinhala-text">ණය ගන්නා</span><br>
                    </div>
                </div>
                <div class="signature-section ">
                    <div class="col-md-6">
                        <p>_________________________<br>
                        <span class="sinhala-text">ඇපකරුවා 1</span><br>
                        <strong><?= $loan['guarantor1_name'] ?></strong><br>
                        NIC: <?= $loan['guarantor1_nic'] ?><br>
                        දිනය: _________________________</p>
                    </div>
                    <div class="col-md-6">
                        <p>_________________________<br>
                        <span class="sinhala-text">ඇපකරුවා 2</span><br>
                        <strong><?= $loan['guarantor2_name'] ?></strong><br>
                        NIC: <?= $loan['guarantor2_nic'] ?><br>
                        ලිපිනය: <?= $loan['guarantor2_address'] ?><br>
                        දිනය: _________________________</p>
                    </div>
                </div>
                
                <div class="footer-note">
                    <p>This agreement is electronically generated and valid without signature. A signed copy is available at our office.</p>
                    <p class="sinhala-text">මෙම ගිවිසුම ඉලෙක්ට්‍රොනිකව ජනනය කර ඇති අතර අත්සන නොමැතිව වලංගු වේ. අත්සන් කළ පිටපතක් අපගේ කාර්යාලයේ ලබා ගත හැකිය.</p>
                </div>
                <div class="no-print" style="margin-top: 15px;">
                    <button onclick="window.print()" style="padding: 5px 10px;">Print Receipt</button>
                    <button onclick="window.close()" style="padding: 5px 10px;">Close Window</button>
                </div>
            </div>
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

<?php
// Include footer
include 'footer.php';
?>