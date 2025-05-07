<?php
include "db.php";

// Get today's interest calculations
$query = "SELECT * FROM daily_interest_accruals WHERE date = CURDATE()";
$result = $conn->query($query);

echo "<h2>Today's Interest Calculations</h2>";
echo "<p>Found: " . $result->num_rows . " records</p>";

if ($result->num_rows > 0) {
    echo "<table border='1'>";
    echo "<tr><th>Loan ID</th><th>Date</th><th>Amount</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['loan_id'] . "</td>";
        echo "<td>" . $row['date'] . "</td>";
        echo "<td>" . $row['amount'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No interest calculations found for today.</p>";
}