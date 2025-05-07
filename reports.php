<?php
include "db.php";
require 'vendor/autoload.php';

// Set page title
$page_title = "Reporting Dashboard";
// Include header
include 'header.php';

?>

<div class="container mt-4">
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <h2 class="mb-0"><i class="fas fa-chart-bar me-2"></i> Microfinance Reports</h2>
        </div>
        <div class="card-body">
            <div class="row">
                <!-- Loan Reports -->
                <div class="col-md-4 mb-4">
                    <div class="card h-100">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="fas fa-file-invoice-dollar me-2"></i> Loan Reports</h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item">
                                    <a href="loan_portfolio_report.php" class="text-decoration-none">
                                        <i class="fas fa-book me-2"></i> Loan Portfolio Report
                                    </a>
                                </li>
                                <li class="list-group-item">
                                    <a href="loan_disbursement_report.php" class="text-decoration-none">
                                        <i class="fas fa-hand-holding-usd me-2"></i> Loan Disbursement Report
                                    </a>
                                </li>
                                <li class="list-group-item">
                                    <a href="loan_collection_report.php" class="text-decoration-none">
                                        <i class="fas fa-money-bill-wave me-2"></i> Loan Collection Report
                                    </a>
                                </li>
                                <li class="list-group-item">
                                    <a href="delinquency_report.php" class="text-decoration-none">
                                        <i class="fas fa-exclamation-triangle me-2"></i> Delinquency Report
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <!-- Client Reports -->
                <div class="col-md-4 mb-4">
                    <div class="card h-100">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="fas fa-users me-2"></i> Client Reports</h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item">
                                    <a href="member_report.php" class="text-decoration-none">
                                        <i class="fas fa-user me-2"></i> Member/Client Report
                                    </a>
                                </li>
                                <li class="list-group-item">
                                    <a href="guarantor_report.php" class="text-decoration-none">
                                        <i class="fas fa-user-shield me-2"></i> Guarantor Report
                                    </a>
                                </li>
                                <li class="list-group-item">
                                    <a href="cbo_report.php" class="text-decoration-none">
                                        <i class="fas fa-users-between-lines me-2"></i> CBO Report
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <!-- Financial Reports -->
                <div class="col-md-4 mb-4">
                    <div class="card h-100">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="mb-0"><i class="fas fa-calculator me-2"></i> Financial Reports</h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item">
                                    <a href="financial_summary.php" class="text-decoration-none">
                                        <i class="fas fa-chart-pie me-2"></i> Financial Summary
                                    </a>
                                </li>
                                <li class="list-group-item">
                                    <a href="income_statement.php" class="text-decoration-none">
                                        <i class="fas fa-file-invoice me-2"></i> Income Statement
                                    </a>
                                </li>
                                <li class="list-group-item">
                                    <a href="balance_sheet.php" class="text-decoration-none">
                                        <i class="fas fa-balance-scale me-2"></i> Balance Sheet
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Custom Report Generator -->
            <div class="card mt-4">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0"><i class="fas fa-cogs me-2"></i> Custom Report Generator</h5>
                </div>
                <div class="card-body">
                    <form id="customReportForm" method="POST" action="generate_custom_report.php">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Report Type</label>
                                <select name="report_type" class="form-select" required>
                                    <option value="">-- Select Report Type --</option>
                                    <option value="loan">Loan Report</option>
                                    <option value="client">Client Report</option>
                                    <option value="financial">Financial Report</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Date From</label>
                                <input type="date" name="date_from" class="form-control">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Date To</label>
                                <input type="date" name="date_to" class="form-control">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Branch</label>
                                <select name="branch_id" class="form-select">
                                    <option value="">All Branches</option>
                                    <?php
                                    $branches = $conn->query("SELECT * FROM branches WHERE status = 'active'");
                                    while ($branch = $branches->fetch_assoc()) {
                                        echo "<option value='{$branch['id']}'>{$branch['name']}</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Loan Product</label>
                                <select name="loan_product_id" class="form-select">
                                    <option value="">All Products</option>
                                    <?php
                                    $products = $conn->query("SELECT * FROM loan_products");
                                    while ($product = $products->fetch_assoc()) {
                                        echo "<option value='{$product['id']}'>{$product['name']}</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="">All Statuses</option>
                                    <option value="Pending">Pending</option>
                                    <option value="Approved">Approved</option>
                                    <option value="Rejected">Rejected</option>
                                    <option value="Disbursed">Disbursed</option>
                                    <option value="Completed">Completed</option>
                                </select>
                            </div>
                        </div>
                        <div class="text-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-file-export me-1"></i> Generate Report
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Additional Styles -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<style>
    .card {
        transition: transform 0.3s;
    }
    .card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.1);
    }
    .list-group-item:hover {
        background-color: #f8f9fa;
    }
    .section-title {
        border-bottom: 2px solid #dee2e6;
        padding-bottom: 10px;
        margin-bottom: 20px;
    }
</style>

<?php
include 'footer.php';
?>