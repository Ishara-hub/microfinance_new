<?php
include "db.php";

ini_set('display_errors', 1);
error_reporting(E_ALL);
// Get all active interest-only loans
$loans = $conn->query("
    SELECT la.id, la.loan_amount, la.interest_rate, la.created_at 
    FROM loan_applications la
    JOIN loan_products lp ON la.loan_product_id = lp.id
    WHERE lp.interest_type = 'interest_only' AND la.status = 'approved'
");

while ($loan = $loans->fetch_assoc()) {
    // Calculate daily interest (annual rate divided by 365)
    $daily_interest = ($loan['loan_amount'] * ($loan['interest_rate'] / 100)) / 365;
    
    // Record daily interest
    $record_query = $conn->prepare("
        INSERT INTO daily_interest_accruals 
        (loan_id, date, amount) 
        VALUES (?, CURDATE(), ?)
        ON DUPLICATE KEY UPDATE amount = ?
    ");
    $record_query->bind_param("sdd", $loan['id'], $daily_interest, $daily_interest);
    $record_query->execute();
    
    // Log the activity
    file_put_contents('daily_interest.log', 
        date('Y-m-d H:i:s') . " - Recorded daily interest for loan {$loan['id']}: Rs. " . 
        number_format($daily_interest, 2) . PHP_EOL, 
        FILE_APPEND);
}

echo "Daily interest calculation completed at " . date('Y-m-d H:i:s');
?>