<?php
include "db.php";
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Payment History</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2>Payment History</h2>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Loan ID</th>
                    <th>Installment Date</th>
                    <th>Paid Amount</th>
                    <th>Paid Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $payments = $conn->query("SELECT ld.loan_id, ld.installment_date, ld.paid_amount, ld.paid_date, ld.id 
                                          FROM loan_details ld 
                                          WHERE ld.paid_amount > 0 
                                          ORDER BY ld.paid_date DESC");

                while ($row = $payments->fetch_assoc()) {
                    echo "<tr>
                            <td>{$row['loan_id']}</td>
                            <td>{$row['installment_date']}</td>
                            <td>" . number_format($row['paid_amount'], 2) . "</td>
                            <td>{$row['paid_date']}</td>
                            <td>
                                <a href='edit_payment.php?id={$row['id']}' class='btn btn-warning btn-sm'>Edit</a>
                                <a href='delete_payment.php?id={$row['id']}' class='btn btn-danger btn-sm' onclick='return confirm(\"Are you sure?\")'>Delete</a>
                            </td>
                          </tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</body>
</html>
