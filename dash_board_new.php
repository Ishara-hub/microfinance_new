<?php
include "db.php";
require 'vendor/autoload.php'; // For charts (using Chart.js)

// Fetch stats
$stats = [
    'active_loans' => $conn->query("SELECT COUNT(*) FROM loan_applications WHERE status = 'Approved'")->fetch_row()[0],
    'pending_loans' => $conn->query("SELECT COUNT(*) FROM loan_applications WHERE status = 'Pending'")->fetch_row()[0],
    'total_clients' => $conn->query("SELECT COUNT(*) FROM members")->fetch_row()[0],
    'overdue' => $conn->query("SELECT COUNT(*) FROM loan_details WHERE status = 'overdue'")->fetch_row()[0]
];

// Fetch recent payments
$payments = $conn->query("SELECT p.amount, p.payment_date, m.full_name 
                          FROM payments p
                          JOIN loan_applications la ON p.loan_id = la.id
                          JOIN members m ON la.member_id = m.id
                          ORDER BY p.payment_date DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);

// Fetch loan trends (last 6 months)
$loan_trends = $conn->query("
    SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, 
           COUNT(*) AS loans,
           SUM(loan_amount) AS amount
    FROM loan_applications
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY month
    ORDER BY month
")->fetch_all(MYSQLI_ASSOC);
// Include header
include 'header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Microfinance Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css1/main.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <!-- Main Content -->
    <div class="container-fluid py-4">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <button class="btn btn-dark d-md-none" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
            <h1><i class="fas fa-tachometer-alt"></i> Dashboard</h1>
            <div class="btn-group">
                <a href="loan_approval.php" class="btn btn-primary"><i class="fas fa-plus"></i> New Loan</a>
                <a href="process_payment.php" class="btn btn-success"><i class="fas fa-money-bill-wave"></i> Quick Payment</a>
            </div>
        </div>
        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card summary-card bg-primary text-white">
                    <div class="card-body">
                        <h5>Active Loans</h5>
                        <h2><?= $stats['active_loans'] ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card summary-card bg-warning text-dark">
                    <div class="card-body">
                        <h5>Pending Approvals</h5>
                        <h2><?= $stats['pending_loans'] ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card summary-card bg-success text-white">
                    <div class="card-body">
                        <h5>Total Clients</h5>
                        <h2><?= $stats['total_clients'] ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card summary-card bg-danger text-white">
                    <div class="card-body">
                        <h5>Overdue Payments</h5>
                        <h2><?= $stats['overdue'] ?></h2>
                    </div>
                </div>
            </div>
        </div>
        <!-- Recent Activity -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-dark text-white">Quick Actions</div>
                    <div class="card-body text-center">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <a href="loan_appl.php" class="btn btn-primary btn-lg w-100 py-3">
                                    <i class="fas fa-hand-holding-usd fa-2x"></i><br>
                                    New Business Loan
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="micro_loan_application.php" class="btn btn-warning btn-lg w-100 py-3">
                                    <i class="fas fa-hand-holding-usd fa-2x"></i><br>
                                    New Micro Loan
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="leasing.php" class="btn btn-danger btn-lg w-100 py-3">
                                    <i class="fas fa-hand-holding-usd fa-2x"></i><br>
                                    New Lease Loan
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="process_payment.php" class="btn btn-success btn-lg w-100 py-3">
                                    <i class="fas fa-money-bill-wave fa-2x"></i><br>
                                    Receive Payment
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="members.php" class="btn btn-info btn-lg w-100 py-3">
                                    <i class="fas fa-user-plus fa-2x"></i><br>
                                    Add Client
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="product.php" class="btn btn-info btn-lg w-100 py-3">
                                    <i class="fas fa-user-plus fa-2x"></i><br>
                                    Loan Products
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="loan_approval.php" class="btn btn-info btn-lg w-100 py-3">
                                    <i class="fas fa-user-plus fa-2x"></i><br>
                                    Loan Approvals
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Charts Row -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card chart-container">
                    <div class="card-header bg-info">Loan Disbursement (Last 6 Months)</div>
                    <div class="card-body">
                        <canvas id="loanTrendsChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card chart-container">
                    <div class="card-header bg-info">Loan Product Distribution</div>
                    <div class="card-body">
                        <canvas id="productDistributionChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart Scripts -->
    <script>
        // Loan Trends Chart
        new Chart(document.getElementById('loanTrendsChart'), {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_column($loan_trends, 'month')) ?>,
                datasets: [{
                    label: 'Number of Loans',
                    data: <?= json_encode(array_column($loan_trends, 'loans')) ?>,
                    backgroundColor: 'rgba(54, 162, 235, 0.6)'
                }, {
                    label: 'Amount (Rs)',
                    data: <?= json_encode(array_column($loan_trends, 'amount')) ?>,
                    type: 'line',
                    borderColor: 'rgba(255, 99, 132, 1)',
                    backgroundColor: 'rgba(255, 99, 132, 0.2)'
                }]
            }
        });

        // Product Distribution Chart
        new Chart(document.getElementById('productDistributionChart'), {
            type: 'doughnut',
            data: {
                labels: ['Micro Loans', 'Business Loans', 'Daily Loans'],
                datasets: [{
                    data: [65, 25, 10], // Replace with real data
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.7)',
                        'rgba(54, 162, 235, 0.7)',
                        'rgba(255, 206, 86, 0.7)'
                    ]
                }]
            }
        });
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
            document.body.classList.toggle('sidebar-active');
        });
    </script>
</body>
</html>