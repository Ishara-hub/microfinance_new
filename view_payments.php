<?php
include "db.php"; // Database connection include karanna

// 🔹 Loan ID GET method walin gannawa
$loan_id = isset($_GET['loan_id']) ? (int)$_GET['loan_id'] : 0;

if ($loan_id > 0) {
    // 🔹 Loan ID ekata sambandha paid payments gannawa
    $query = "SELECT installment_date, paid_amount 
              FROM loan_details 
              WHERE loan_id = $loan_id AND paid_amount > 0 
              ORDER BY installment_date ASC";

    $result = $conn->query($query);

    if ($result->num_rows > 0) {
        echo "<h3>Payments for Loan ID: $loan_id</h3>";
        echo "<table border='1'>
                <tr>
                    <th>Payment Date</th>
                    <th>Paid Amount</th>
                </tr>";

        while ($row = $result->fetch_assoc()) {
            echo "<tr>
                    <td>" . $row['installment_date'] . "</td>
                    <td>" . number_format($row['paid_amount'], 2) . "</td>
                  </tr>";
        }

        echo "</table>";
    } else {
        echo "<p>No payments found for Loan ID: $loan_id</p>";
    }
} else {
    echo "<p>Please provide a valid Loan ID.</p>";
}
?>
