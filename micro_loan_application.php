<?php
// micro_loan_single_page.php
ob_start();

include "db.php";
require 'vendor/autoload.php';
include 'header.php';

// Initialize variables
$message = '';
$branches = $credit_officers = $cbos = $members = $loan_products = [];

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Only proceed if database connection is established
if ($conn) {
    // Get all necessary data
    $branches = $conn->query("SELECT * FROM branches WHERE status = 'active'")->fetch_all(MYSQLI_ASSOC);
    $credit_officers = $conn->query("SELECT id, username FROM users WHERE role = 'admin'")->fetch_all(MYSQLI_ASSOC);
    $loan_products = $conn->query("SELECT * FROM loan_products WHERE status = 'active'")->fetch_all(MYSQLI_ASSOC);

    // Process form submission
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $branch_id = (int)$_POST['branch_id'];
        $credit_officer_id = (int)$_POST['credit_officer_id'];
        $cbo_id = (int)$_POST['cbo_id'];
        $member_id = (int)$_POST['member_id'];
        $loan_product_id = (int)$_POST['loan_product_id'];
        $repayment_method = $conn->real_escape_string($_POST['repayment_method']);
        
        // Validate required fields
        if ($branch_id > 0 && $credit_officer_id > 0 && $cbo_id > 0 && $member_id > 0 && $loan_product_id > 0) {
            // Get loan product details
            $product = $conn->query("SELECT * FROM loan_products WHERE id = $loan_product_id")->fetch_assoc();
            
            if ($product) {
                // Calculate values
                $loan_amount = floatval($_POST['loan_amount']);
                $interest_rate = $product['interest_rate'];
                $installments = $product['installments'];
                
                // Calculate based on repayment method
                $effective_rate = $interest_rate / 100;
                if ($repayment_method === "weekly") {
                    $effective_rate /= 52;
                } elseif ($repayment_method === "monthly") {
                    $effective_rate /= 12;
                } elseif ($repayment_method === "daily") {
                    $effective_rate /= 365;
                }
                
                $total_interest = $loan_amount * $effective_rate * $installments;
                $total_repayment = $loan_amount + $total_interest;
                $rental_value = $total_repayment / $installments;

                // Generate ML loan ID
                $loan_type = 'ML'; // Fixed prefix for micro loans
                
                // Get the highest existing ML number
                $next_id_query = $conn->prepare("
                    SELECT MAX(CAST(SUBSTRING(id, 3) AS UNSIGNED)) as max_num 
                    FROM micro_loan_applications 
                    WHERE id LIKE CONCAT(?, '%')
                ");
                $next_id_query->bind_param("s", $loan_type);
                $next_id_query->execute();
                $result = $next_id_query->get_result();
                $row = $result->fetch_assoc();
                $next_num = ($row['max_num'] ?? 0) + 1;
                $loan_id = $loan_type . str_pad($next_num, 4, '0', STR_PAD_LEFT); // ML0001 format

                // Insert the application
                $query = "INSERT INTO micro_loan_applications (
                    id, branch_id, credit_officer_id, cbo_id, member_id, 
                    loan_product_id, loan_amount, interest_rate, installments, 
                    repayment_method, rental_value, total_repayment,
                    status, application_date
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', NOW())";
                
                $stmt = $conn->prepare($query);
                $stmt->bind_param(
                    "siiiiddisddd", 
                    $loan_id, $branch_id, $credit_officer_id, $cbo_id, $member_id,
                    $loan_product_id, $loan_amount, $interest_rate, $installments,
                    $repayment_method, $rental_value, $total_repayment
                );
                
                if ($stmt->execute()) {
                    $_SESSION['message'] = [
                        'type' => 'success',
                        'text' => 'Micro Loan application submitted successfully! Loan ID: ' . $loan_id
                    ];
                    header("Location: micro_loan_application.php");
                    exit();
                } else {
                    $_SESSION['message'] = [
                        'type' => 'error',
                        'text' => 'Error: ' . $conn->error
                    ];
                }
            }
        }
    }
}    
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Micro Loan Application</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .container { max-width: 1000px; }
        .form-section { 
            background-color: #f8f9fa; 
            padding: 25px; 
            border-radius: 10px; 
            margin-bottom: 20px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .section-title { 
            border-bottom: 2px solid #dee2e6; 
            padding-bottom: 10px; 
            margin-bottom: 20px;
            color: #2c3e50;
        }
        .member-card { 
            cursor: pointer; 
            transition: all 0.3s; 
            margin-bottom: 10px;
            border: 1px solid #dee2e6;
        }
        .member-card:hover { 
            transform: translateY(-3px); 
            box-shadow: 0 5px 15px rgba(0,0,0,0.1); 
            border-color: #0d6efd;
        }
        .member-card.active { 
            border: 2px solid #0d6efd; 
            background-color: #e7f1ff; 
        }
        .calculation-box { 
            background-color: #e8f4f8; 
            padding: 15px; 
            border-radius: 5px;
            border-left: 4px solid #0d6efd;
        }
        .form-label {
            font-weight: 500;
            color: #495057;
        }
        .input-group-text {
            min-width: 100px;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <h1 class="text-center mb-4"><i class="fas fa-hand-holding-usd me-2"></i>Micro Loan Application</h1>
        
        <?php if (!empty($message)) echo $message; ?>
        
        <form method="POST" id="loanApplicationForm">
            <div class="row">
                <!-- Basic Information Section -->
                <div class="col-md-5">
                    <div class="form-section">
                        <h4 class="section-title"><i class="fas fa-info-circle me-2"></i>Basic Information</h4>
                        
                        <div class="mb-3">
                            <label class="form-label">Branch</label>
                            <select name="branch_id" class="form-select" id="branchSelect" required>
                                <option value="">-- Select Branch --</option>
                                <?php foreach ($branches as $branch): ?>
                                    <option value="<?= $branch['id'] ?>"><?= htmlspecialchars($branch['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Credit Officer</label>
                            <select name="credit_officer_id" class="form-select" required>
                                <option value="">-- Select Credit Officer --</option>
                                <?php foreach ($credit_officers as $officer): ?>
                                    <option value="<?= $officer['id'] ?>"><?= htmlspecialchars($officer['username']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">CBO</label>
                            <select name="cbo_id" class="form-select" id="cboSelect" required>
                                <option value="">-- Select CBO --</option>
                                <!-- Will be populated via AJAX -->
                            </select>
                        </div>
                    </div>
                    
                    <!-- Member Selection -->
                    <div class="form-section" id="memberSection" style="display: none;">
                        <h4 class="section-title"><i class="fas fa-users me-2"></i>Select Member</h4>
                        <div id="memberList" class="row">
                            <!-- Members will be loaded here via AJAX -->
                        </div>
                        <input type="hidden" name="member_id" id="selectedMemberId">
                    </div>
                </div>
                
                <!-- Loan Details Section -->
                <div class="col-md-7">
                    <div class="form-section">
                        <h4 class="section-title"><i class="fas fa-money-bill-wave me-2"></i>Loan Details</h4>
                        <div class="row">
                            <div class="col-md-4 mb-2">
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
                                                    {$product['name']} 
                                            </option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-8 mb-4">
                                <label class="form-label">Loan Amount (Rs.)</label>
                                <div class="input-group">
                                    <span class="input-group-text">Rs.</span>
                                    <input type="number" name="loan_amount" id="loan_amount" class="form-control" min="1000" step="100" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="calculation-box mb-3">
                            <div class="row">
                                <div class="col-md-6 mb-2">
                                    <label class="form-label">Repayment Method</label>
                                    <input type="text" name="repayment_method" id="repayment_method" class="form-control" readonly>
                                </div>
                                <div class="col-md-6 mb-2">
                                    <label class="form-label">Installments</label>
                                    <input type="number" name="installments" id="installments" class="form-control" readonly>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-2">
                                    <label class="form-label">Interest Rate</label>
                                    <div class="input-group">
                                        <input type="text" name="interest_rate" id="interest_rate" class="form-control" readonly>
                                        <span class="input-group-text">%</span>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-2">
                                    <label class="form-label">Rental Value</label>
                                    <div class="input-group">
                                        <span class="input-group-text">Rs.</span>
                                        <input type="text" name="rental_value" id="rental_value" class="form-control" readonly>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-12">
                                    <label class="form-label">Total Repayment</label>
                                    <div class="input-group">
                                        <span class="input-group-text">Rs.</span>
                                        <input type="text" id="total_repayment" class="form-control" readonly>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="text-center mt-4">
                <button type="submit" class="btn btn-primary btn-lg px-5">
                    <i class="fas fa-paper-plane me-2"></i>Submit Application
                </button>
            </div>
        </form>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Load CBOs when branch is selected
            $('#branchSelect').change(function() {
                const branchId = $(this).val();
                if (branchId) {
                    $.ajax({
                        url: 'get_cbos.php',
                        type: 'POST',
                        data: { branch_id: branchId },
                        success: function(response) {
                            $('#cboSelect').html('<option value="">-- Select CBO --</option>' + response);
                            $('#memberSection').hide();
                        },
                        error: function(xhr, status, error) {
                            console.error("Error loading CBOs:", error);
                        }
                    });
                } else {
                    $('#cboSelect').html('<option value="">-- Select CBO --</option>');
                    $('#memberSection').hide();
                }
            });
            
            // Load members when CBO is selected
            $('#cboSelect').change(function() {
                const cboId = $(this).val();
                if (cboId) {
                    $.ajax({
                        url: 'get_members.php',
                        type: 'POST',
                        data: { cbo_id: cboId },
                        success: function(response) {
                            $('#memberList').html(response);
                            $('#memberSection').show();
                        },
                        error: function(xhr, status, error) {
                            console.error("Error loading members:", error);
                        }
                    });
                } else {
                    $('#memberSection').hide();
                }
            });
            
            // Member selection
            $(document).on('click', '.member-card', function() {
                $('.member-card').removeClass('active');
                $(this).addClass('active');
                $('#selectedMemberId').val($(this).data('member-id'));
            });
            
            // Loan product selection
            $("#loan_product_id").change(function() {
                let selected = $(this).find(":selected");
                let repaymentMethod = selected.data("repayment");
                let interestRate = parseFloat(selected.data("interest"));
                let installments = parseInt(selected.data("installments"));
                let defaultAmount = parseFloat(selected.data("default-amount")) || 0;
                
                // Set the fixed values
                $("#repayment_method").val(repaymentMethod);
                $("#interest_rate").val(interestRate.toFixed(2));
                $("#installments").val(installments);
                
                // Set default amount if available
                if (defaultAmount > 0) {
                    $("#loan_amount").val(defaultAmount.toFixed(2));
                }
                
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
        });
    </script>
</body>
</html>
<?php include 'footer.php'; ?>  