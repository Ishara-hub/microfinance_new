<?php
include "db.php";
require 'vendor/autoload.php';

// Set page title
$page_title = "Loan Application Form";
// Include header
include 'header.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $branch = $_POST['branch_id'];
    $credit_officer = $_POST['credit_officer_id'];
    $member_id = $_POST['member_id'];

    // Guarantor 1 Details
    $guarantor1_name = $_POST['guarantor1_name'];
    $guarantor1_nic = $_POST['guarantor1_nic'];
    $guarantor1_mobile = $_POST['guarantor1_mobile'];

    // Guarantor 2 Details
    $guarantor2_name = $_POST['guarantor2_name'];
    $guarantor2_nic = $_POST['guarantor2_nic'];
    $guarantor2_mobile = $_POST['guarantor2_mobile'];

    $loan_product_id = $_POST['loan_product_id'];
    $loan_amount = $_POST['loan_amount'];
    $installments = $_POST['installments'];
    $interest_rate = $_POST['interest_rate'];
    $rental_value = $_POST['rental_value'];
    $status = "Pending"; 
    
    // Prepare SQL statement
    $query = "INSERT INTO loan_applications (branch, credit_officer, member_id, guarantor1_name, guarantor1_nic, guarantor1_mobile, 
                guarantor2_name, guarantor2_nic, guarantor2_mobile, loan_product_id, loan_amount, installments, interest_rate, rental_value, status) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iiisssssssiddds", $branch, $credit_officer, $member_id, $guarantor1_name, $guarantor1_nic, $guarantor1_mobile, 
                                    $guarantor2_name, $guarantor2_nic, $guarantor2_mobile, $loan_product_id, $loan_amount, $installments, 
                                    $interest_rate, $rental_value, $status);
    
    if ($stmt->execute()) {
        echo "<script>alert('✅ Loan Application Submitted Successfully!'); window.location.href='loan_applications.php';</script>";
    } else {
        echo "<script>alert('❌ Error: " . $stmt->error . "');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loan Application</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css1/main.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

</head>
<body>
    <div class="container mt-5">
        <h2>Loan Application</h2>
        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Branch</label>
                <select name="branch_id" class="form-control" required>
                    <option value="">-- Select Branch --</option>
                    <?php
                    $branches = $conn->query("SELECT * FROM branches WHERE status = 'active'");
                    while ($branch = $branches->fetch_assoc()) {
                        echo "<option value='{$branch['id']}'>{$branch['name']} ({$branch['location']})</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Credit Officer</label>
                <select name="credit_officer_id" class="form-control" required>
                    <option value="">-- Select Credit Officer --</option>
                    <?php
                    $users = $conn->query("SELECT id, username FROM users WHERE role = 'admin'");
                    while ($user = $users->fetch_assoc()) {
                        echo "<option value='{$user['id']}'>{$user['username']}</option>";
                    }
                    ?>
                </select>
            </div>
            <!-- Member NIC Search -->
            <div class="mb-3">
                <label class="form-label">Member NIC</label>
                <input type="text" id="search_nic" class="form-control" placeholder="Enter NIC">
                <button type="button" id="search_btn" class="btn btn-info mt-2">Search</button>
            </div>

            <div class="mb-3">
                <label class="form-label">Member Name</label>
                <input type="text" id="member_name" class="form-control" name="member_name" readonly>
            </div>

            <input type="hidden" id="member_id" name="member_id">


            <!-- Guarantor Details -->
            <div class="mb-3">
                <h4>Guarantor 1 Details</h4>
                <input type="text" name="guarantor1_name" class="form-control" placeholder="Guarantor 1 Name" required>
                <input type="text" name="guarantor1_nic" class="form-control mt-2" placeholder="Guarantor 1 NIC" required>
                <input type="text" name="guarantor1_mobile" class="form-control mt-2" placeholder="Guarantor 1 Mobile" required>
            </div>
            <!-- Guarantor Details -->
            <div class="mb-3">
                <h4>Guarantor 2 Details</h4>
                <input type="text" name="guarantor2_name" class="form-control" placeholder="Guarantor 2 Name" required>
                <input type="text" name="guarantor2_nic" class="form-control mt-2" placeholder="Guarantor 2 NIC" required>
                <input type="text" name="guarantor2_mobile" class="form-control mt-2" placeholder="Guarantor 2 Mobile" required>
            </div>
            <!-- Loan Product Details -->
            <div class="mb-3">
                <label class="form-label">Loan Product</label>
                <select name="loan_product_id" id="loan_product_id" class="form-control" required>
                    <option value="">-- Select Loan Product --</option>
                    <?php
                    $products = $conn->query("SELECT * FROM loan_products");
                    while ($product = $products->fetch_assoc()) {
                        echo "<option value='{$product['id']}' 
                                    data-interest='{$product['interest_rate']}'
                                    data-installments='{$product['installments']}'
                                    data-rental='{$product['rental_value']}'>
                                    {$product['name']} - {$product['interest_rate']}% ({$product['installments']} Installments)
                            </option>";
                    }
                    ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Loan Amount</label>
                <input type="number" name="loan_amount" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">No. of Installments</label>
                <input type="number" name="installments" id="installments" class="form-control" readonly>
            </div>
            <div class="mb-3">
                <label class="form-label">Interest Rate (%)</label>
                <input type="text" name="interest_rate" id="interest_rate" class="form-control" readonly>
            </div>
            <div class="mb-3">
                <label class="form-label">Rental Value</label>
                <input type="text" name="rental_value" id="rental_value" class="form-control" readonly>
            </div>
            
            <button type="submit" class="btn btn-success">Submit Application</button>
        </form>
    </div>
    <script>
        $(document).ready(function() {
            $("#loan_product_id").change(function() {
                let selected = $(this).find(":selected");
                $("#interest_rate").val(selected.data("interest"));
                $("#installments").val(selected.data("installments"));
                $("#rental_value").val(selected.data("rental"));
            });
        });
        $(document).ready(function () {
            $("#search_btn").click(function () {
                let nic = $("#search_nic").val().trim();

                if (nic !== "") {
                    $.ajax({
                        url: "search_member.php",
                        type: "POST",
                        data: { nic: nic },
                        dataType: "json",
                        success: function (response) {
                            if (response.status === "success") {
                                $("#member_id").val(response.member_id);
                                $("#member_name").val(response.member_name);
                            } else {
                                alert(response.message);
                                $("#member_id").val("");
                                $("#member_name").val("");
                            }
                        },
                        error: function () {
                            alert("Error processing request.");
                        }
                    });
                } else {
                    alert("Please enter a NIC number.");
                }
            });
        });

    </script>
</body>
</html>
<?php
// Include footer
include 'footer.php';
?>

