<?php
include "db.php";

// Set page title
$page_title = "Loan products";
// Include header
include 'header.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $repayment_method = $_POST['repayment_method'];
    $interest_type = $_POST['interest_type'];
    $product_type = $_POST['product_type'];
    $loan_amount = $_POST['loan_amount'];
    $installments = $_POST['installments'];
    $interest_rate = $_POST['interest_rate'];

    // 🔹 Effective Interest Rate Calculation (IRR Method)
    if ($repayment_method == 'weekly') {
        $effective_rate = ($interest_rate / 100) / 52;
    } elseif ($repayment_method == 'monthly') {
        $effective_rate = ($interest_rate / 100) / 12;
    } elseif ($repayment_method == 'daily') {
        $effective_rate = ($interest_rate / 100) / 365;
    } else {
        die("Invalid repayment method!");
    }

    // 🔹 Interest Calculation
    $total_interest = $loan_amount * $effective_rate * $installments;

    // 🔹 Rental Calculation
    $rental_value = ($loan_amount + $total_interest) / $installments;

    // 🔹 Insert Loan Product
    $query = "INSERT INTO loan_products (name, repayment_method, interest_type, product_type, loan_amount, installments, interest_rate, rental_value) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ssssddds", $name, $repayment_method, $interest_type, $product_type, $loan_amount, $installments, $interest_rate, $rental_value);

    if ($stmt->execute()) {
        echo "<script>alert('✅ Loan Product Added Successfully!'); window.location.href='loan_products.php';</script>";
    } else {
        echo "<script>alert('❌ Error: " . $stmt->error . "');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Add Loan Product</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2>Add Loan Product</h2>
        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Product Name</label>
                <input type="text" name="name" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Repayment Method</label>
                <select name="repayment_method" class="form-control">
                    <option value="weekly">Weekly</option>
                    <option value="daily">Daily</option>
                    <option value="monthly">Monthly</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Interest Type</label>
                <select name="interest_type" class="form-control">
                    <option value="flat_rate">Flat Rate</option>
                    <option value="interest_only">Interest Only</option>
                    <option value="depreciation">Depreciation</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Product Type</label>
                <select name="product_type" class="form-control">
                    <option value="micro_loan">Micro Loan</option>
                    <option value="business_loan">Business Loan</option>
                    <option value="leasing">Leasing Loan</option>
                    
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Loan Amount</label>
                <input type="number" name="loan_amount" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">No of Installments</label>
                <input type="number" name="installments" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Interest Rate (%)</label>
                <input type="number" step="0.01" name="interest_rate" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Rental Value (Auto Calculated)</label>
                <input type="number" name="rental_value" class="form-control" id="rental_value" readonly>
            </div>
            <button type="submit" class="btn btn-primary">Add Product</button>
        </form>
    </div>

    <script>
        // Auto-calculate Rental when inputs change
        document.querySelector("form").addEventListener("input", function() {
            let loanAmount = parseFloat(document.querySelector("[name='loan_amount']").value) || 0;
            let installments = parseInt(document.querySelector("[name='installments']").value) || 1;
            let interestRate = parseFloat(document.querySelector("[name='interest_rate']").value) || 0;
            let repaymentMethod = document.querySelector("[name='repayment_method']").value;

            let effectiveRate = interestRate / 100;
            if (repaymentMethod === "weekly") {
                effectiveRate /= 52;
            } else if (repaymentMethod === "monthly") {
                effectiveRate /= 12;
            } else if (repaymentMethod === "daily") {
                effectiveRate /= 365;
            }

            let totalInterest = loanAmount * effectiveRate * installments;
            let rentalValue = (loanAmount + totalInterest) / installments;

            document.getElementById("rental_value").value = rentalValue.toFixed(2);
        });
    </script>
</body>
</html>
