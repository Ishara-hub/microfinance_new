<?php
include "db.php";

if (isset($_GET['id'])) {
    $payment_id = $_GET['id'];
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // 1. Get payment details first (for reversal)
        $query = "SELECT * FROM payments WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $payment_id);
        $stmt->execute();
        $payment = $stmt->get_result()->fetch_assoc();
        
        if (!$payment) {
            throw new Exception("Payment not found");
        }
        
        // 2. Reverse the payment in loan_details
        if ($payment['installment_id']) {
            $reverse_query = "UPDATE loan_details 
                             SET paid_amount = paid_amount - ?,
                                 interest_due = interest_due + ?,
                                 capital_due = capital_due + ?,
                                 total_due = total_due + ?,
                                 status = CASE WHEN (total_due + ?) > 0 THEN 'pending' ELSE 'paid' END
                             WHERE id = ?";
            $stmt = $conn->prepare($reverse_query);
            $total_paid = $payment['interest_paid'] + $payment['capital_paid'];
            $stmt->bind_param("dddddi", 
                $total_paid,
                $payment['interest_paid'],
                $payment['capital_paid'],
                $total_paid,
                $total_paid,
                $payment['installment_id']
            );
            $stmt->execute();
        }
        
        // 3. Delete the payment record
        $delete_query = "DELETE FROM payments WHERE id = ?";
        $stmt = $conn->prepare($delete_query);
        $stmt->bind_param("i", $payment_id);
        $stmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        header("Location: manage_payments.php?success=Payment deleted successfully");
        exit();
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        header("Location: manage_payments.php?error=" . urlencode($e->getMessage()));
        exit();
    }
} else {
    header("Location: manage_payments.php");
    exit();
}
?>