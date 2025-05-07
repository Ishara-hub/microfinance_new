<?php
require_once('vendor/autoload.php');
require_once('db.php'); // Your database connection file

// Check if payment ID is provided
if (!isset($_GET['payment_id'])) {
    die('Payment ID not provided');
}

$payment_id = $_GET['payment_id'];

// Fetch payment details
$query = "SELECT p.*, m.full_name, m.address, m.phone, la.loan_number 
          FROM payments p
          JOIN loan_applications la ON p.loan_id = la.id
          JOIN members m ON la.member_id = m.id
          WHERE p.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $payment_id);
$stmt->execute();
$payment = $stmt->get_result()->fetch_assoc();

if (!$payment) {
    die('Payment not found');
}

// Create new PDF document
$pdf = new TCPDF('P', 'mm', 'A5', true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('Microfinance System');
$pdf->SetAuthor('Your Organization');
$pdf->SetTitle('Payment Receipt #' . $payment['id']);
$pdf->SetSubject('Payment Receipt');

// Add a page
$pdf->AddPage();

// Logo
$pdf->Image('assets/logo.png', 10, 10, 30, 0, 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);

// Set font
$pdf->SetFont('helvetica', 'B', 16);

// Title
$pdf->Cell(0, 15, 'PAYMENT RECEIPT', 0, 1, 'C');
$pdf->Ln(5);

// Set smaller font for details
$pdf->SetFont('helvetica', '', 10);

// Receipt info
$pdf->Cell(0, 0, 'Receipt No: MF-' . $payment['id'], 0, 1, 'R');
$pdf->Cell(0, 0, 'Date: ' . date('d/m/Y', strtotime($payment['payment_date'])), 0, 1, 'R');
$pdf->Ln(10);

// Client info
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 0, 'Client Information', 0, 1);
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 0, 'Name: ' . $payment['full_name'], 0, 1);
$pdf->Cell(0, 0, 'Address: ' . $payment['address'], 0, 1);
$pdf->Cell(0, 0, 'Phone: ' . $payment['phone'], 0, 1);
$pdf->Ln(10);

// Payment details
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 0, 'Payment Details', 0, 1);
$pdf->SetFont('helvetica', '', 10);

$html = <<<EOD
<table border="1" cellpadding="5">
    <tr>
        <th width="30%">Loan Number</th>
        <td width="70%">{$payment['loan_number']}</td>
    </tr>
    <tr>
        <th>Payment Amount</th>
        <td>Rs. {$payment['amount']}</td>
    </tr>
    <tr>
        <th>Payment Method</th>
        <td>{$payment['payment_method']}</td>
    </tr>
    <tr>
        <th>Transaction Reference</th>
        <td>{$payment['transaction_reference']}</td>
    </tr>
    <tr>
        <th>Payment Date</th>
        <td>{$payment['payment_date']}</td>
    </tr>
</table>
EOD;

$pdf->writeHTML($html, true, false, true, false, '');

// Footer
$pdf->Ln(15);
$pdf->Cell(0, 0, 'Thank you for your payment!', 0, 1, 'C');
$pdf->Ln(10);
$pdf->Cell(0, 0, 'Authorized Signature: ________________________', 0, 1, 'R');

// Close and output PDF
$pdf->Output('receipt_' . $payment['id'] . '.pdf', 'I');
?>