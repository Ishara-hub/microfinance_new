<?php
include "db.php";

// 1. Fetch all overdue installments
$query = "
    SELECT ld.*, la.id as loan_id, 'regular' as loan_type, lp.penalty_rate
    FROM loan_details ld
    JOIN loan_applications la ON ld.loan_application_id = la.id
    JOIN loan_products lp ON la.loan_product_id = lp.id
    WHERE ld.status != 'paid' AND ld.installment_date < CURDATE()
    
    UNION
    
    SELECT ld.*, la.id as loan_id, 'micro' as loan_type, lp.penalty_rate
    FROM micro_loan_details ld
    JOIN micro_loan_applications la ON ld.micro_loan_application_id = la.id
    JOIN loan_products lp ON la.loan_product_id = lp.id
    WHERE ld.status != 'paid' AND ld.installment_date < CURDATE()
    
    UNION
    
    SELECT ld.*, la.id as loan_id, 'lease' as loan_type, lp.penalty_rate
    FROM lease_details ld
    JOIN lease_applications la ON ld.lease_application_id = la.id
    JOIN loan_products lp ON la.loan_product_id = lp.id
    WHERE ld.status != 'paid' AND ld.installment_date < CURDATE()
";

$result = $conn->query($query);

while ($row = $result->fetch_assoc()) {
    $loan_id = $row['loan_id'];
    $installment_id = $row['id'];
    $loan_type = $row['loan_type'];
    $due_date = $row['installment_date'];
    
    // 2. Calculate days overdue
    $today = new DateTime();
    $due_date = new DateTime($due_date);
    $days_overdue = $today->diff($due_date)->days;
    
    // 3. Calculate daily penalty (1% per month = ~0.033% per day)
    $daily_penalty_rate = $row['penalty_rate'] / 30 / 100;
    $penalty_amount = $row['total_due'] * $daily_penalty_rate * $days_overdue;
    
    // 4. Insert penalty if not already added today
    $check_query = "SELECT id FROM penalties 
                   WHERE loan_id = ? 
                   AND installment_id = ? 
                   AND penalty_date = CURDATE()";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("si", $loan_id, $installment_id);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows == 0) {
        $insert_query = "INSERT INTO penalties 
                        (loan_id, installment_id, loan_type, penalty_date, penalty_amount)
                        VALUES (?, ?, ?, CURDATE(), ?)";
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param("siss", $loan_id, $installment_id, $loan_type, $penalty_amount);
        $stmt->execute();
        
        // 5. Update installment with cumulative penalty
        $update_query = "UPDATE " . getInstallmentTable($loan_type) . " 
                        SET penalty = penalty + ? 
                        WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("di", $penalty_amount, $installment_id);
        $stmt->execute();
    }
}

function getInstallmentTable($loan_type) {
    switch ($loan_type) {
        case 'micro': return 'micro_loan_details';
        case 'lease': return 'lease_details';
        default: return 'loan_details';
    }
}

echo "Penalties calculated successfully!";
?>