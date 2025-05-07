<?php
include "db.php";

// Count pending loan applications
$query = "SELECT COUNT(*) as count FROM loan_applications WHERE status = 'Pending'";
$query = "SELECT COUNT(*) as count FROM micro_loan_applications WHERE status = 'Pending'";
$query = "SELECT COUNT(*) as count FROM lease_loan_applications WHERE status = 'Pending'";
$result = $conn->query($query);
$count = $result->fetch_assoc()['count'];

header('Content-Type: application/json');
echo json_encode(['count' => $count]);
?>