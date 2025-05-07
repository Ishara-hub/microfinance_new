<?php
ob_start(); // Start output buffering at the VERY FIRST LINE
include "db.php";

// Set page title
$page_title = "Loan Products Management";
// Include header
include 'header.php';

// Handle product status toggle
if (isset($_GET['toggle_status'])) {
    $product_id = intval($_GET['id']);
    $new_status = $_GET['toggle_status'] === 'active' ? 'active' : 'inactive';
    
    $stmt = $conn->prepare("UPDATE loan_products SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $product_id);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = [
            'type' => 'success',
            'text' => 'Product status updated successfully!'
        ];
    } else {
        $_SESSION['message'] = [
            'type' => 'error',
            'text' => 'Error updating product status: ' . $stmt->error
        ];
    }
    header("Location: product.php");
    exit();
}

// Handle product deletion
if (isset($_GET['delete'])) {
    $product_id = intval($_GET['id']);
    
    $stmt = $conn->prepare("DELETE FROM loan_products WHERE id = ?");
    $stmt->bind_param("i", $product_id);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = [
            'type' => 'success',
            'text' => 'Product deleted successfully!'
        ];
    } else {
        $_SESSION['message'] = [
            'type' => 'error',
            'text' => 'Error deleting product: ' . $stmt->error
        ];
    }
    header("Location: product.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $repayment_method = $_POST['repayment_method'];
    $interest_type = $_POST['interest_type'];
    $product_type = $_POST['product_type'];
    $loan_amount = floatval($_POST['loan_amount']);
    $installments = intval($_POST['installments']);
    $interest_rate = floatval($_POST['interest_rate']);

    // Validate inputs
    if (empty($name) || $loan_amount <= 0 || $installments <= 0 || $interest_rate <= 0) {
        $_SESSION['message'] = [
            'type' => 'error',
            'text' => 'Please fill all fields with valid values!'
        ];
        header("Location: product.php");
        exit();
    }

    // Calculate effective interest rate
    $effective_rate = calculateEffectiveRate($interest_rate, $repayment_method);
    
    // Calculate total interest and rental value
    $total_interest = $loan_amount * $effective_rate * $installments;
    $rental_value = ($loan_amount + $total_interest) / $installments;

    // Insert Loan Product
    $query = "INSERT INTO loan_products (name, repayment_method, interest_type, product_type, loan_amount, installments, interest_rate, rental_value, status) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ssssddds", $name, $repayment_method, $interest_type, $product_type, $loan_amount, $installments, $interest_rate, $rental_value);

    if ($stmt->execute()) {
        $_SESSION['message'] = [
            'type' => 'success',
            'text' => 'Loan product added successfully!'
        ];
    } else {
        $_SESSION['message'] = [
            'type' => 'error',
            'text' => 'Error adding product: ' . $stmt->error
        ];
    }
    
    header("Location: product.php");
    exit();
}

function calculateEffectiveRate($annualRate, $repaymentMethod) {
    $rate = $annualRate / 100;
    switch ($repaymentMethod) {
        case 'weekly': return $rate / 52;
        case 'monthly': return $rate / 12;
        case 'daily': return $rate / 365;
        default: return $rate;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title><?= $page_title ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="assets/css1/components/loan_product.css">

</head>
<body>
    <div class="container py-4">
        <!-- Display flash messages -->
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-<?= $_SESSION['message']['type'] ?> alert-dismissible fade show">
                <?= $_SESSION['message']['text'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>

        <div class="card shadow mb-4">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h2 class="mb-0"><i class="fas fa-list me-2"></i> Loan Products</h2>
                <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#addProductModal">
                    <i class="fas fa-plus me-1"></i> Add Product
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover table-bordered">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Product Name</th>
                                <th>Type</th>
                                <th>Repayment</th>
                                <th>Interest Rate</th>
                                <th>Installments</th>
                                <th>Rental Value</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $products = $conn->query("SELECT * FROM loan_products ORDER BY status DESC, name ASC");
                            while ($product = $products->fetch_assoc()):
                            ?>
                            <tr>
                                <td><?= $product['id'] ?></td>
                                <td><?= htmlspecialchars($product['name']) ?></td>
                                <td><?= ucfirst(str_replace('_', ' ', $product['product_type'])) ?></td>
                                <td><?= ucfirst($product['repayment_method']) ?></td>
                                <td><?= number_format($product['interest_rate'], 2) ?>%</td>
                                <td><?= $product['installments'] ?></td>
                                <td>Rs. <?= number_format($product['rental_value'], 2) ?></td>
                                <td>
                                    <span class="badge rounded-pill <?= $product['status'] == 'active' ? 'badge-active' : 'badge-inactive' ?>">
                                        <?= ucfirst($product['status']) ?>
                                    </span>
                                </td>
                                <td class="action-btns">
                                    <?php if ($product['status'] == 'active'): ?>
                                        <a href="?toggle_status=inactive&id=<?= $product['id'] ?>" class="btn btn-warning btn-sm" title="Deactivate">
                                            <i class="fas fa-toggle-off"></i>
                                        </a>
                                    <?php else: ?>
                                        <a href="?toggle_status=active&id=<?= $product['id'] ?>" class="btn btn-success btn-sm" title="Activate">
                                            <i class="fas fa-toggle-on"></i>
                                        </a>
                                    <?php endif; ?>
                                    <a href="edit_product.php?id=<?= $product['id'] ?>" class="btn btn-info btn-sm" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="?delete=1&id=<?= $product['id'] ?>" class="btn btn-danger btn-sm" title="Delete" onclick="return confirm('Are you sure you want to delete this product?')">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Product Modal -->
    <div class="modal fade" id="addProductModal" tabindex="-1" aria-labelledby="addProductModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="addProductModalLabel"><i class="fas fa-plus-circle me-2"></i> Add New Loan Product</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" id="loanProductForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label required">Product Name</label>
                                    <input type="text" name="name" class="form-control" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label required">Repayment Method</label>
                                    <select name="repayment_method" class="form-select" required>
                                        <option value="">-- Select Method --</option>
                                        <option value="weekly">Weekly</option>
                                        <option value="daily">Daily</option>
                                        <option value="monthly">Monthly</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label required">Interest Type</label>
                                    <select name="interest_type" class="form-select" required>
                                        <option value="">-- Select Type --</option>
                                        <option value="flat_rate">Flat Rate</option>
                                        <option value="interest_only">Interest Only</option>
                                        <option value="declining">Declining</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label required">Product Type</label>
                                    <select name="product_type" class="form-select" required>
                                        <option value="">-- Select Product --</option>
                                        <option value="micro_loan">Micro Loan</option>
                                        <option value="business_loan">Business Loan</option>
                                        <option value="daily_loan">Daily Loan</option>
                                        <option value="leasing">Leasing Loan</option>
                                        <option value="mortgage_loan">Mortgage Loan</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="calculation-card">
                                    <h5><i class="fas fa-calculator me-2"></i>Financial Parameters</h5>
                                    
                                    <div class="mb-3">
                                        <label class="form-label required">Loan Amount (Rs.)</label>
                                        <input type="number" name="loan_amount" class="form-control" min="1" step="0.01" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label required">No of Installments</label>
                                        <input type="number" name="installments" class="form-control" min="1" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label required">Interest Rate (%)</label>
                                        <input type="number" name="interest_rate" class="form-control" min="0.01" step="0.01" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Rental Value (Rs.)</label>
                                        <input type="text" name="rental_value" class="form-control" id="rental_value" readonly>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Total Interest (Rs.)</label>
                                        <input type="text" class="form-control" id="total_interest" readonly>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Total Repayment (Rs.)</label>
                                        <input type="text" class="form-control" id="total_repayment" readonly>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Save Product</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        // Calculate loan details when inputs change
        const form = document.getElementById("loanProductForm");
        const calculationInputs = form.querySelectorAll(
            "[name='loan_amount'], [name='installments'], [name='interest_rate'], [name='repayment_method']"
        );
        
        calculationInputs.forEach(input => {
            input.addEventListener("input", calculateLoanDetails);
        });
        
        function calculateLoanDetails() {
            const loanAmount = parseFloat(form.querySelector("[name='loan_amount']").value) || 0;
            const installments = parseInt(form.querySelector("[name='installments']").value) || 1;
            const interestRate = parseFloat(form.querySelector("[name='interest_rate']").value) || 0;
            const repaymentMethod = form.querySelector("[name='repayment_method']").value;
            
            if (loanAmount > 0 && interestRate > 0 && repaymentMethod) {
                // Calculate effective rate
                let effectiveRate = interestRate / 100;
                switch(repaymentMethod) {
                    case "weekly": effectiveRate /= 52; break;
                    case "monthly": effectiveRate /= 12; break;
                    case "daily": effectiveRate /= 365; break;
                }
                
                // Calculate financials
                const totalInterest = loanAmount * effectiveRate * installments;
                const totalRepayment = loanAmount + totalInterest;
                const rentalValue = totalRepayment / installments;
                
                // Update display
                document.getElementById("rental_value").value = rentalValue.toFixed(2);
                document.getElementById("total_interest").value = totalInterest.toFixed(2);
                document.getElementById("total_repayment").value = totalRepayment.toFixed(2);
            }
        }
        
        // Initialize calculation when modal opens
        $('#addProductModal').on('shown.bs.modal', function() {
            calculateLoanDetails();
        });
        
        // Auto-close alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                new bootstrap.Alert(alert).close();
            });
        }, 5000);
        
        // Form validation
        form.addEventListener("submit", function(e) {
            if (!form.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
                
                Swal.fire({
                    icon: 'error',
                    title: 'Missing Information',
                    text: 'Please fill all required fields with valid values',
                });
            }
            form.classList.add('was-validated');
        }, false);
        
        // Initialize tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });
    </script>
</body>
</html>