<?php
include "db.php";
require 'vendor/autoload.php';

$page_title = "Financial Summary Report";
include 'header.php';

// Get filter parameters
$branch_id = $_GET['branch_id'] ?? '';
$date_from = $_GET['date_from'] ?? date('Y-m-01'); // Default to start of current month
$date_to = $_GET['date_to'] ?? date('Y-m-t'); // Default to end of current month

// Validate dates
if (!empty($date_from)) {
    $date_from = date('Y-m-d', strtotime($date_from));
}
if (!empty($date_to)) {
    $date_to = date('Y-m-d', strtotime($date_to));
}

// 1. TOTAL LOAN PORTFOLIO (using payment due dates to determine delinquency)
$portfolio_query = "SELECT 
                    SUM(la.loan_amount) AS total_portfolio,
                    SUM(CASE WHEN la.status = 'Disbursed' AND (
                        SELECT COUNT(*) 
                        FROM loan_details li 
                        WHERE li.loan_application_id = la.id 
                        AND li.installment_date < CURDATE() 
                        AND li.status != 'Paid'
                    ) > 0 THEN la.loan_amount ELSE 0 END) AS delinquent_portfolio
                    FROM loan_applications la
                    WHERE la.status = 'Disbursed'";

// 2. DISBURSEMENTS
$disbursements_query = "SELECT 
                       SUM(la.loan_amount) AS total_disbursed,
                       COUNT(la.id) AS loan_count
                       FROM loan_applications la
                       WHERE la.status = 'Disbursed' 
                       AND la.created_at BETWEEN ? AND ?";

// 3. COLLECTIONS
$collections_query = "SELECT 
                     SUM(p.amount) AS total_collected,
                     COUNT(p.id) AS payment_count
                     FROM payments p
                     WHERE p.payment_date BETWEEN ? AND ?";

// 4. DELINQUENCIES (using installments to determine delinquency)
$delinquencies_query = "SELECT 
                        COUNT(DISTINCT la.id) AS delinquent_loans,
                        SUM(la.loan_amount) AS delinquent_amount
                        FROM loan_applications la
                        JOIN loan_details li ON la.id = li.loan_application_id
                        WHERE la.status = 'Disbursed'
                        AND li.installment_date < CURDATE()
                        AND li.status != 'Paid'";

// Add branch filter if selected
if (!empty($branch_id)) {
    $portfolio_query .= " AND la.branch = ?";
    $disbursements_query .= " AND la.branch = ?";
    $collections_query .= " AND p.branch_id = ?";
    $delinquencies_query .= " AND la.branch = ?";
}

// Prepare and execute queries
$queries = [
    'portfolio' => $portfolio_query,
    'disbursements' => $disbursements_query,
    'collections' => $collections_query,
    'delinquencies' => $delinquencies_query
];

$results = [];
foreach ($queries as $key => $query) {
    $stmt = $conn->prepare($query);
    
    if ($stmt) {
        // Bind parameters based on query type
        switch($key) {
            case 'disbursements':
            case 'collections':
                if (!empty($branch_id)) {
                    $stmt->bind_param('ssi', $date_from, $date_to, $branch_id);
                } else {
                    $stmt->bind_param('ss', $date_from, $date_to);
                }
                break;
            case 'portfolio':
            case 'delinquencies':
                if (!empty($branch_id)) {
                    $stmt->bind_param('i', $branch_id);
                }
        }
        
        if ($stmt->execute()) {
            $results[$key] = $stmt->get_result()->fetch_assoc();
        } else {
            // Handle query execution error
            echo "<div class='alert alert-danger'>Error executing $key query: " . $stmt->error . "</div>";
            $results[$key] = ['total_portfolio' => 0, 'delinquent_portfolio' => 0];
        }
        $stmt->close();
    } else {
        // Handle query preparation error
        echo "<div class='alert alert-danger'>Error preparing $key query: " . $conn->error . "</div>";
        $results[$key] = ['total_portfolio' => 0, 'delinquent_portfolio' => 0];
    }
}

// Calculate additional metrics
$total_portfolio = $results['portfolio']['total_portfolio'] ?? 0;
$delinquent_portfolio = $results['portfolio']['delinquent_portfolio'] ?? 0;

$collection_rate = ($results['disbursements']['total_disbursed'] ?? 0) > 0 
    ? (($results['collections']['total_collected'] ?? 0) / ($results['disbursements']['total_disbursed'] ?? 1)) * 100 
    : 0;

$delinquency_rate = $total_portfolio > 0 
    ? ($delinquent_portfolio / $total_portfolio) * 100 
    : 0;
?>


<div class="container mt-4">
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <h2 class="mb-0"><i class="fas fa-chart-line me-2"></i> Financial Summary Report</h2>
        </div>
        <div class="card-body">
            <!-- Filter Form -->
            <form method="GET" class="mb-4">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Branch</label>
                        <select name="branch_id" class="form-select">
                            <option value="">All Branches</option>
                            <?php
                            $branches = $conn->query("SELECT * FROM branches WHERE status = 'active'");
                            while ($branch = $branches->fetch_assoc()) {
                                $selected = $branch['id'] == $branch_id ? 'selected' : '';
                                echo "<option value='{$branch['id']}' $selected>{$branch['name']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Date From</label>
                        <input type="date" name="date_from" class="form-control" value="<?= $date_from ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Date To</label>
                        <input type="date" name="date_to" class="form-control" value="<?= $date_to ?>">
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-md-12 text-end">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="fas fa-filter me-1"></i> Filter
                        </button>
                        <a href="financial_summary.php" class="btn btn-outline-secondary">
                            <i class="fas fa-sync-alt me-1"></i> Reset
                        </a>
                    </div>
                </div>
            </form>

            <!-- Summary Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card text-white bg-success h-100">
                        <div class="card-body">
                            <h5 class="card-title">Total Portfolio</h5>
                            <h2 class="mb-0">Rs. <?= number_format($results['portfolio']['total_portfolio'] ?? 0, 2) ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-info h-100">
                        <div class="card-body">
                            <h5 class="card-title">Disbursements</h5>
                            <h2 class="mb-0">Rs. <?= number_format($results['disbursements']['total_disbursed'] ?? 0, 2) ?></h2>
                            <small><?= $results['disbursements']['loan_count'] ?? 0 ?> loans</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-primary h-100">
                        <div class="card-body">
                            <h5 class="card-title">Collections</h5>
                            <h2 class="mb-0">Rs. <?= number_format($results['collections']['total_collected'] ?? 0, 2) ?></h2>
                            <small><?= $results['collections']['payment_count'] ?? 0 ?> payments</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-danger h-100">
                        <div class="card-body">
                            <h5 class="card-title">Delinquencies</h5>
                            <h2 class="mb-0">Rs. <?= number_format($results['delinquencies']['delinquent_amount'] ?? 0, 2) ?></h2>
                            <small><?= $results['delinquencies']['delinquent_loans'] ?? 0 ?> loans</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Performance Metrics -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="mb-0">Collection Rate</h5>
                        </div>
                        <div class="card-body text-center">
                            <div class="display-4"><?= number_format($collection_rate, 2) ?>%</div>
                            <div class="progress mt-3">
                                <div class="progress-bar bg-success" role="progressbar" 
                                     style="width: <?= $collection_rate ?>%" 
                                     aria-valuenow="<?= $collection_rate ?>" 
                                     aria-valuemin="0" 
                                     aria-valuemax="100"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header bg-danger text-white">
                            <h5 class="mb-0">Delinquency Rate</h5>
                        </div>
                        <div class="card-body text-center">
                            <div class="display-4"><?= number_format($delinquency_rate, 2) ?>%</div>
                            <div class="progress mt-3">
                                <div class="progress-bar bg-danger" role="progressbar" 
                                     style="width: <?= $delinquency_rate ?>%" 
                                     aria-valuenow="<?= $delinquency_rate ?>" 
                                     aria-valuemin="0" 
                                     aria-valuemax="100"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detailed Reports Section -->
            <div class="card mb-4">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0">Detailed Reports</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <a href="loan_disbursement_report.php?date_from=<?= $date_from ?>&date_to=<?= $date_to ?>&branch_id=<?= $branch_id ?>" 
                               class="btn btn-outline-primary w-100">
                                <i class="fas fa-hand-holding-usd me-2"></i>Disbursement Report
                            </a>
                        </div>
                        <div class="col-md-4 mb-3">
                            <a href="loan_collection_report.php?date_from=<?= $date_from ?>&date_to=<?= $date_to ?>&branch_id=<?= $branch_id ?>" 
                               class="btn btn-outline-success w-100">
                                <i class="fas fa-money-bill-wave me-2"></i>Collection Report
                            </a>
                        </div>
                        <div class="col-md-4 mb-3">
                            <a href="delinquency_report.php?date_from=<?= $date_from ?>&date_to=<?= $date_to ?>&branch_id=<?= $branch_id ?>" 
                               class="btn btn-outline-danger w-100">
                                <i class="fas fa-exclamation-triangle me-2"></i>Delinquency Report
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>