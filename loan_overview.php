<?php
include "db.php";
require 'vendor/autoload.php';

$page_title = "Loan_overbiew.php";
include 'header.php';

// Get loan ID from query parameter
$loanId = isset($_GET['loan_id']) ? $_GET['loan_id'] : '';

// Validate loan ID
if (empty($loanId)) {
    die("Loan ID is required");
}

// Sanitize input
$loanId = htmlspecialchars($loanId);



// Function to fetch loan details
function getLoanDetails($conn, $loanId) {
    $stmt = $conn->prepare("
        SELECT la.*, m.full_name, m.branch, m.phone, m.nic, m.address, m.dob,
               u.full_name AS credit_officer,
               la.loan_status, la.disbursement_date, la.created_date,
               la.loan_amount, la.agreed_amount, la.installment_value,
               la.total_outstanding, la.total_paid, la.default_amount,
               la.interest_rate, la.notes,
               DATEDIFF(CURDATE(), la.disbursement_date) AS installment_age,
               PERIOD_DIFF(EXTRACT(YEAR_MONTH FROM CURDATE()), 
                          EXTRACT(YEAR_MONTH FROM la.disbursement_date)) AS monthly_age
        FROM loan_applications la
        JOIN members m ON la.member_id = m.id
        JOIN users u ON la.credit_officer = u.id
        JOIN branches b ON la.branch = b.id
        WHERE la.loan_id = ?
    ");
    $stmt->bind_param("s", $loanId);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

// Function to fetch payment schedule
function getPaymentSchedule($conn, $loanId) {
    $stmt = $conn->prepare("
        SELECT * FROM loan_details 
        WHERE loan_application_id = ?
        ORDER BY installment_date ASC
    ");
    $stmt->bind_param("s", $loanId);
    $stmt->execute();
    return $stmt->get_result();
}

// Get loan data
$loanDetails = getLoanDetails($conn, $loanId);
$paymentSchedule = getPaymentSchedule($conn, $loanId);



// Check if loan exists
if (!$loanDetails) {
    die("Loan not found");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MACS MF - Loan Overview</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        <?php include 'assets/css1/components/loan_overview.css'; ?>
    </style>
</head>
<body>
    <div class="container">
        <!-- Header Section -->
        <header class="app-header">
            <div class="logo">MACS MF</div>
            <div class="loan-search">
                <form method="GET" action="loan_overview.php">
                    <input type="text" name="loan_id" placeholder="Enter Loan ID..." 
                           value="<?php echo $loanId; ?>" class="search-input">
                    <button type="submit" class="search-btn"><i class="fas fa-search"></i></button>
                </form>
            </div>
            <div class="loan-info">
                <span class="loan-id">Loan ID: <?php echo $loanId; ?></span>
                <span class="client-name">Client: <?php echo $loanDetails['client_name']; ?></span>
            </div>
        </header>

        <!-- Navigation Tabs -->
        <nav class="tabs">
            <button class="tab active">Loan Details</button>
            <button class="tab">Schedule</button>
            <button class="tab">Transactions</button>
            <button class="tab">Documents</button>
            <button class="tab">Notes</button>
            <button class="tab">Activity Log</button>
        </nav>

        <!-- Main Content Area -->
        <main class="content">
            <!-- Client Information Section -->
            <section class="info-section">
                <h3>Client Information</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <label>Client Name:</label>
                        <span><?php echo $loanDetails['full_name']; ?></span>
                    </div>
                    <div class="info-item">
                        <label>Branch:</label>
                        <span><?php echo $loanDetails['branch']; ?></span>
                    </div>
                    <div class="info-item">
                        <label>Telephone:</label>
                        <span><?php echo $loanDetails['phone']; ?></span>
                    </div>
                    <div class="info-item">
                        <label>NIC:</label>
                        <span><?php echo $loanDetails['nic']; ?></span>
                    </div>
                    <div class="info-item">
                        <label>Address:</label>
                        <span><?php echo $loanDetails['address']; ?></span>
                    </div>
                    <div class="info-item">
                        <label>Credit Officer:</label>
                        <span><?php echo $loanDetails['credit_officer']; ?></span>
                    </div>
                </div>
            </section>

            <!-- Loan Status Section -->
            <section class="status-section">
                <div class="status-header">
                    <h3>Loan Status: <span class="status-badge <?php echo strtolower($loanDetails['loan_status']); ?>">
                        <?php echo $loanDetails['loan_status']; ?>
                    </span></h3>
                    <div class="status-progress">
                        <div class="progress-step <?php echo $loanDetails['loan_status'] == 'Application' ? 'active' : 'completed'; ?>">Application</div>
                        <div class="progress-step <?php echo $loanDetails['loan_status'] == 'Approved' ? 'active' : ($loanDetails['loan_status'] == 'Application' ? '' : 'completed'); ?>">Approval</div>
                        <div class="progress-step <?php echo $loanDetails['loan_status'] == 'Disbursed' ? 'active' : ($loanDetails['loan_status'] == 'Repaying' || $loanDetails['loan_status'] == 'Completed' ? 'completed' : ''); ?>">Disbursed</div>
                        <div class="progress-step <?php echo $loanDetails['loan_status'] == 'Repaying' ? 'active' : ($loanDetails['loan_status'] == 'Completed' ? 'completed' : ''); ?>">Repayment</div>
                        <div class="progress-step <?php echo $loanDetails['loan_status'] == 'Completed' ? 'active' : ''; ?>">Completed</div>
                    </div>
                </div>
                
                <div class="status-grid">
                    <div class="status-item">
                        <label>Disbursement Date:</label>
                        <span><?php echo $loanDetails['disbursement_date']; ?></span>
                    </div>
                    <div class="status-item">
                        <label>Created Date:</label>
                        <span><?php echo $loanDetails['created_date']; ?></span>
                    </div>
                    <div class="status-item">
                        <label>Installment Age:</label>
                        <span><?php echo $loanDetails['installment_age']; ?></span>
                    </div>
                    <div class="status-item">
                        <label>Monthly Age:</label>
                        <span><?php echo $loanDetails['monthly_age']; ?></span>
                    </div>
                    <div class="status-item">
                        <label>Notes:</label>
                        <span><?php echo $loanDetails['notes']; ?></span>
                    </div>
                </div>
            </section>

            <!-- Financial Summary Section -->
            <section class="financial-section">
                <h3>Financial Summary</h3>
                <div class="financial-cards">
                    <div class="financial-card">
                        <label>Loan Amount</label>
                        <div class="amount">Rs <?php echo number_format($loanDetails['loan_amount'], 2); ?></div>
                    </div>
                    <div class="financial-card">
                        <label>Agreed Amount</label>
                        <div class="amount">Rs <?php echo number_format($loanDetails['agreed_amount'], 2); ?></div>
                    </div>
                    <div class="financial-card">
                        <label>Installment Value</label>
                        <div class="amount">Rs <?php echo number_format($loanDetails['installment_value'], 2); ?></div>
                    </div>
                    <div class="financial-card">
                        <label>Total Outstanding</label>
                        <div class="amount">Rs <?php echo number_format($loanDetails['total_outstanding'], 2); ?></div>
                    </div>
                    <div class="financial-card">
                        <label>Total Paid</label>
                        <div class="amount">Rs <?php echo number_format($loanDetails['total_paid'], 2); ?></div>
                    </div>
                    <div class="financial-card">
                        <label>Default Amount</label>
                        <div class="amount">Rs <?php echo number_format($loanDetails['default_amount'], 2); ?></div>
                    </div>
                    <div class="financial-card">
                        <label>Interest Rate</label>
                        <div class="amount"><?php echo $loanDetails['interest_rate']; ?>%</div>
                    </div>
                </div>
            </section>

            <!-- Payment Schedule Section -->
            <section class="schedule-section">
                <div class="section-header">
                    <h3>Payment Schedule</h3>
                    <div class="section-actions">
                        <button class="btn export-btn"><i class="fas fa-download"></i> Export</button>
                    </div>
                </div>
                
                <div class="table-container">
                    <table class="schedule-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Installment Date</th>
                                <th>Periodic Payment</th>
                                <th>Paid Amount</th>
                                <th>Capital Paid</th>
                                <th>Interest Paid</th>
                                <th>Capital Due</th>
                                <th>Interest Due</th>
                                <th>Total Due</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($payment = $paymentSchedule->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $payment['installment_number']; ?></td>
                                <td><?php echo $payment['installment_date']; ?></td>
                                <td>Rs <?php echo number_format($payment['periodic_payment'], 2); ?></td>
                                <td>Rs <?php echo number_format($payment['paid_amount'], 2); ?></td>
                                <td>Rs <?php echo number_format($payment['capital_paid'], 2); ?></td>
                                <td>Rs <?php echo number_format($payment['interest_paid'], 2); ?></td>
                                <td>Rs <?php echo number_format($payment['capital_due'], 2); ?></td>
                                <td>Rs <?php echo number_format($payment['interest_due'], 2); ?></td>
                                <td>Rs <?php echo number_format($payment['total_due'], 2); ?></td>
                                <td>
                                    <span class="status-badge <?php echo strtolower($payment['status']); ?>">
                                        <?php echo $payment['status']; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>

        <!-- Action Buttons -->
        <div class="action-buttons">
            <button class="btn primary" onclick="window.print()"><i class="fas fa-print"></i> Print Agreement</button>
            <button class="btn success" onclick="showSettleModal()"><i class="fas fa-hand-holding-usd"></i> Settle Loan</button>
            <button class="btn warning" onclick="showCashRequestModal()"><i class="fas fa-money-bill-wave"></i> Request Cash</button>
            <button class="btn info" onclick="showNoteModal()"><i class="fas fa-edit"></i> Add Note</button>
            <button class="btn secondary" onclick="generateReport()"><i class="fas fa-file-alt"></i> Generate Report</button>
        </div>
    </div>

    <!-- Modal for Settle Loan -->
    <div id="settleModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('settleModal')">&times;</span>
            <h3>Settle Loan</h3>
            <form id="settleForm" method="POST" action="process_settlement.php">
                <input type="hidden" name="loan_id" value="<?php echo $loanId; ?>">
                <div class="form-group">
                    <label>Settlement Amount:</label>
                    <input type="number" name="settlement_amount" value="<?php echo $loanDetails['total_outstanding']; ?>" step="0.01" required>
                </div>
                <div class="form-group">
                    <label>Settlement Date:</label>
                    <input type="date" name="settlement_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="form-group">
                    <label>Payment Method:</label>
                    <select name="payment_method" required>
                        <option value="cash">Cash</option>
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="check">Check</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Notes:</label>
                    <textarea name="notes" rows="3"></textarea>
                </div>
                <button type="submit" class="btn success">Confirm Settlement</button>
            </form>
        </div>
    </div>

    <script>
        <?php include 'assets/js/loan_overview.js'; ?>
    </script>
</body>
</html>