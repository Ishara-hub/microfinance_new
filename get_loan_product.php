<?php
include "db.php";

if (isset($_POST['loan_product_id'])) {
    $product_id = $_POST['loan_product_id'];
    
    $query = "SELECT interest_rate, installments, rental_value FROM loan_products WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode($row);
    } else {
        echo json_encode([]);
    }
}
?>
