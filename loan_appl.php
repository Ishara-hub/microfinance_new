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
    
    // Determine loan type (regular or micro) based on amount or other criteria
    $loan_type = 'BL'; // Example threshold
    
    // Get the next ID number for this loan type
    $next_id_query = $conn->prepare("
        SELECT MAX(CAST(SUBSTRING(id, 3) AS UNSIGNED)) as max_num 
        FROM loan_applications 
        WHERE id LIKE CONCAT(?, '%')
    ");
    $next_id_query->bind_param("s", $loan_type);
    $next_id_query->execute();
    $result = $next_id_query->get_result();
    $row = $result->fetch_assoc();
    $next_num = ($row['max_num'] ?? 0) + 1;
    $loan_id = $loan_type . str_pad($next_num, 3, '0', STR_PAD_LEFT);

    // Prepare SQL statement with the generated ID
    $query = "INSERT INTO loan_applications (id, branch, credit_officer, member_id, 
              guarantor1_name, guarantor1_nic, guarantor1_mobile, 
              guarantor2_name, guarantor2_nic, guarantor2_mobile, 
              loan_product_id, loan_amount, installments, interest_rate, rental_value, status) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("siiisssssssiddds", $loan_id, $branch, $credit_officer, $member_id, 
                      $guarantor1_name, $guarantor1_nic, $guarantor1_mobile, 
                      $guarantor2_name, $guarantor2_nic, $guarantor2_mobile, 
                      $loan_product_id, $loan_amount, $installments, 
                      $interest_rate, $rental_value, $status);
    
    if ($stmt->execute()) {
        echo "<script>alert('✅ Loan Application Submitted Successfully! ID: $loan_id'); window.location.href='loan_appl.php';</script>";
    } else {
        echo "<script>alert('❌ Error: " . $stmt->error . "');</script>";
    }
}
?>

<div class="container mt-5">
    <div class="card loan-application-card shadow">
        <div class="card-header bg-primary text-white">
            <h2 class="mb-0"><i class="fas fa-file-signature me-2"></i> Loan Application Form</h2>
        </div>
        <div class="card-body">
            <form method="POST" class="needs-validation" novalidate>
                <!-- Form Sections -->
                <div class="form-section mb-4">
                    <h4 class="section-title"><i class="fas fa-building me-2"></i>Branch Information</h4>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Branch</label>
                            <select name="branch_id" class="form-select" required>
                                <option value="">-- Select Branch --</option>
                                <?php
                                $branches = $conn->query("SELECT * FROM branches WHERE status = 'active'");
                                while ($branch = $branches->fetch_assoc()) {
                                    echo "<option value='{$branch['id']}'>{$branch['name']} ({$branch['location']})</option>";
                                }
                                ?>
                            </select>
                            <div class="invalid-feedback">Please select a branch</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Credit Officer</label>
                            <select name="credit_officer_id" class="form-select" required>
                                <option value="">-- Select Credit Officer --</option>
                                <?php
                                $users = $conn->query("SELECT id, username FROM users WHERE role = 'admin'");
                                while ($user = $users->fetch_assoc()) {
                                    echo "<option value='{$user['id']}'>{$user['username']}</option>";
                                }
                                ?>
                            </select>
                            <div class="invalid-feedback">Please select a credit officer</div>
                        </div>
                    </div>
                </div>

                <!-- Member Search Section -->
                <div class="form-section mb-4">
                    <h4 class="section-title"><i class="fas fa-user me-2"></i>Member Information</h4>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Member NIC</label>
                            <div class="input-group">
                                <input type="text" id="search_nic" class="form-control" placeholder="Enter NIC" required>
                                <button type="button" id="search_btn" class="btn btn-info">
                                    <i class="fas fa-search me-1"></i> Search
                                </button>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Member Name</label>
                            <input type="text" id="member_name" class="form-control" name="member_name" readonly>
                        </div>
                    </div>
                    <input type="hidden" id="member_id" name="member_id">
                </div>

                <!-- Guarantor Sections -->
                <div class="form-section mb-4">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card guarantor-card h-100">
                                <div class="card-header bg-secondary text-white">
                                    <h5 class="mb-0"><i class="fas fa-user-shield me-2"></i>Guarantor 1 Details</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label">Full Name</label>
                                        <input type="text" name="guarantor1_name" class="form-control" placeholder="Enter full name" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">NIC Number</label>
                                        <input type="text" name="guarantor1_nic" class="form-control" placeholder="Enter NIC" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Mobile Number</label>
                                        <input type="text" name="guarantor1_mobile" class="form-control" placeholder="Enter mobile" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card guarantor-card h-100">
                                <div class="card-header bg-secondary text-white">
                                    <h5 class="mb-0"><i class="fas fa-user-shield me-2"></i>Guarantor 2 Details</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label">Full Name</label>
                                        <input type="text" name="guarantor2_name" class="form-control" placeholder="Enter full name" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">NIC Number</label>
                                        <input type="text" name="guarantor2_nic" class="form-control" placeholder="Enter NIC" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Mobile Number</label>
                                        <input type="text" name="guarantor2_mobile" class="form-control" placeholder="Enter mobile" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                

                <!-- Loan Details Section -->
                <div class="form-section mb-4">
                    <h4 class="section-title"><i class="fas fa-money-bill-wave me-2"></i>Loan Details</h4>
                    <!-- Loan Type Selection -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Loan Type</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="loan_type" id="bl_loan" value="BL" checked>
                                <label class="form-check-label" for="bl_loan">
                                    Business Loan (BL)
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="loan_type" id="ml_loan" value="ML">
                                <label class="form-check-label" for="ml_loan">
                                    Micro Loan (ML)
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Loan Product</label>
                            <select name="loan_product_id" id="loan_product_id" class="form-select" required>
                                <option value="">-- Select Loan Product --</option>
                                <?php
                                $products = $conn->query("SELECT * FROM loan_products");
                                while ($product = $products->fetch_assoc()) {
                                    echo "<option value='{$product['id']}'
                                                data-repayment='{$product['repayment_method']}' 
                                                data-interest='{$product['interest_rate']}'
                                                data-installments='{$product['installments']}'
                                                data-rental='{$product['rental_value']}'>
                                                data-default-amount='{$product['loan_amount']}'>
                                                {$product['name']} - {$product['interest_rate']}% ({$product['repayment_method']})
                                        </option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Loan Amount (Rs.)</label>
                            <div class="input-group">
                                <span class="input-group-text">Rs.</span>
                                <input type="number" name="loan_amount" id="loan_amount" class="form-control" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Repayment Method</label>
                            <input type="text" name="repayment_method" id="repayment_method" class="form-control" readonly>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Installments</label>
                            <input type="number" name="installments" id="installments" class="form-control" readonly>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Interest Rate</label>
                            <div class="input-group">
                                <input type="text" name="interest_rate" id="interest_rate" class="form-control" readonly>
                                <span class="input-group-text">%</span>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Rental Value</label>
                            <div class="input-group">
                                <span class="input-group-text">Rs.</span>
                                <input type="text" name="rental_value" id="rental_value" class="form-control" readonly>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Total Repayment</label>
                            <div class="input-group">
                                <span class="input-group-text">Rs.</span>
                                <input type="text" id="total_repayment" class="form-control" readonly>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="form-actions text-end mt-4">
                    <button type="reset" class="btn btn-outline-secondary me-2">
                        <i class="fas fa-undo me-1"></i> Reset
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-paper-plane me-1"></i> Submit Application
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Additional Styles -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="assets/css1/components/loan_appl.css">
<!-- Include jQuery first -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- JavaScript Validation -->
<script>
    // Form validation
    (function () {
        'use strict'
        
        var forms = document.querySelectorAll('.needs-validation')
        
        Array.prototype.slice.call(forms)
            .forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    if (!form.checkValidity()) {
                        event.preventDefault()
                        event.stopPropagation()
                    }
                    
                    form.classList.add('was-validated')
                }, false)
            })
    })()
    
    // Loan product selection
    $(document).ready(function() {
    // Loan product selection
    $("#loan_product_id").change(function() {
        let selected = $(this).find(":selected");
        let repaymentMethod = selected.data("repayment");
        let interestRate = parseFloat(selected.data("interest"));
        let installments = parseInt(selected.data("installments"));
        let defaultAmount = parseFloat(selected.data("default-amount"));
        
        // Set the fixed values
        $("#repayment_method").val(repaymentMethod);
        $("#interest_rate").val(interestRate.toFixed(2));
        $("#installments").val(installments);
        $("#loan_amount").val(defaultAmount.toFixed(2));
        
        // Calculate initial values
        calculateLoanValues();
    });
    
    // Loan amount change handler
    $("#loan_amount").on('input', function() {
        calculateLoanValues();
    });
    
    // Function to calculate all loan values
    function calculateLoanValues() {
        let loanAmount = parseFloat($("#loan_amount").val()) || 0;
        let interestRate = parseFloat($("#interest_rate").val()) || 0;
        let installments = parseInt($("#installments").val()) || 1;
        let repaymentMethod = $("#repayment_method").val();
        
        if (loanAmount > 0 && interestRate > 0) {
            // Calculate effective rate based on repayment method
            let effectiveRate = interestRate / 100;
            if (repaymentMethod === "weekly") {
                effectiveRate /= 52;
            } else if (repaymentMethod === "monthly") {
                effectiveRate /= 12;
            } else if (repaymentMethod === "daily") {
                effectiveRate /= 365;
            }
            
            // Calculate total interest and rental value
            let totalInterest = loanAmount * effectiveRate * installments;
            let totalRepayment = loanAmount + totalInterest;
            let rentalValue = totalRepayment / installments;
            
            // Update the form fields
            $("#rental_value").val(rentalValue.toFixed(2));
            $("#total_repayment").val(totalRepayment.toFixed(2));
        }
    }
    
    // Initialize calculations if product is pre-selected
    if ($("#loan_product_id").val()) {
        $("#loan_product_id").trigger("change");
    }

        
        // Member search
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

<?php
// Include footer
include 'footer.php';
?>