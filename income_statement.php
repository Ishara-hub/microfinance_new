<?php
session_start();
include "db.php"; // database connection

// Get period parameters
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-t');

// Get revenue data
$revenue = $conn->query("
    SELECT 
        coa.account_code,
        coa.account_name,
        SUM(je.credit) - SUM(je.debit) AS amount
    FROM 
        chart_of_accounts coa
    LEFT JOIN 
        journal_entries je ON coa.id = je.account_id
    LEFT JOIN 
        general_journal j ON je.journal_id = j.id
    WHERE 
        coa.category_id = (SELECT id FROM account_categories WHERE name = 'Income')
        AND j.transaction_date BETWEEN '$date_from' AND '$date_to'
    GROUP BY 
        coa.id, coa.account_code, coa.account_name
    ORDER BY 
        coa.account_code
");

// Get expenses data
$expenses = $conn->query("
    SELECT 
        coa.account_code,
        coa.account_name,
        SUM(je.debit) - SUM(je.credit) AS amount
    FROM 
        chart_of_accounts coa
    LEFT JOIN 
        journal_entries je ON coa.id = je.account_id
    LEFT JOIN 
        general_journal j ON je.journal_id = j.id
    WHERE 
        coa.category_id = (SELECT id FROM account_categories WHERE name = 'Expenses')
        AND j.transaction_date BETWEEN '$date_from' AND '$date_to'
    GROUP BY 
        coa.id, coa.account_code, coa.account_name
    ORDER BY 
        coa.account_code
");

// Calculate totals
$totalRevenue = 0;
$totalExpenses = 0;

// Include header
include 'header.php';
?>

<style>
    .income-statement-table th, .income-statement-table td {
        padding: 8px;
        vertical-align: middle;
    }
    .income-statement-header {
        font-weight: bold;
        background-color: #eee;
    }
    .account-row {
        font-weight: bold;
    }
    .sub-account {
        padding-left: 20px;
    }
    .text-end {
        text-align: right;
    }
    .total-row {
        font-weight: bold;
        background-color: #f8f9fa;
    }
    .net-income {
        font-weight: bold;
        background-color: <?= ($totalRevenue - $totalExpenses) >= 0 ? '#d4edda' : '#f8d7da' ?>;
    }
</style>

<div class="container mt-4">
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <div class="d-flex justify-content-between align-items-center">
                <h2><i class="fas fa-chart-line me-2"></i>Income Statement</h2>
                <div>
                    <form method="GET" class="d-inline">
                        <div class="input-group">
                            <input type="date" name="date_from" class="form-control form-control-sm" 
                                   value="<?= $date_from ?>">
                            <span class="input-group-text">to</span>
                            <input type="date" name="date_to" class="form-control form-control-sm" 
                                   value="<?= $date_to ?>">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fas fa-filter"></i>
                            </button>
                        </div>
                    </form>
                    <button class="btn btn-light btn-sm ms-2" onclick="window.print()">
                        <i class="fas fa-print me-1"></i> Print
                    </button>
                </div>
            </div>
        </div>
        <div class="card-body">
            <h2 class="text-center">FAF Solution (pvt)Ltd , Branch All Branches</h2>
            <h5 class="text-center">for the period <?= date('m/d/Y', strtotime($date_from)) ?> to <?= date('m/d/Y', strtotime($date_to)) ?></h5>

            <!-- Revenue Section -->
            <table class="table table-bordered income-statement-table mt-4">
                <thead class="table-light">
                    <tr>
                        <th>A/C Code</th>
                        <th>Account Description</th>
                        <th class="text-end">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="income-statement-header">
                        <td colspan="3">Revenue</td>
                    </tr>
                    
                    <?php while($row = $revenue->fetch_assoc()): 
                        $totalRevenue += $row['amount'];
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($row['account_code']) ?></td>
                        <td><?= htmlspecialchars($row['account_name']) ?></td>
                        <td class="text-end"><?= number_format($row['amount'], 2) ?></td>
                    </tr>
                    <?php endwhile; ?>
                    
                    <tr class="total-row">
                        <td colspan="2" class="text-end">Total Revenue</td>
                        <td class="text-end"><?= number_format($totalRevenue, 2) ?></td>
                    </tr>
                </tbody>
            </table>

            <!-- Expenses Section -->
            <table class="table table-bordered income-statement-table mt-4">
                <thead class="table-light">
                    <tr>
                        <th>A/C Code</th>
                        <th>Account Description</th>
                        <th class="text-end">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="income-statement-header">
                        <td colspan="3">Expenses</td>
                    </tr>
                    
                    <?php while($row = $expenses->fetch_assoc()): 
                        $totalExpenses += $row['amount'];
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($row['account_code']) ?></td>
                        <td><?= htmlspecialchars($row['account_name']) ?></td>
                        <td class="text-end"><?= number_format($row['amount'], 2) ?></td>
                    </tr>
                    <?php endwhile; ?>
                    
                    <tr class="total-row">
                        <td colspan="2" class="text-end">Total Expenses</td>
                        <td class="text-end"><?= number_format($totalExpenses, 2) ?></td>
                    </tr>
                </tbody>
            </table>

            <!-- Net Income Section -->
            <table class="table table-bordered income-statement-table mt-4">
                <tbody>
                    <tr class="net-income">
                        <td colspan="2" class="text-end">
                            <?= ($totalRevenue - $totalExpenses) >= 0 ? 'Net Income' : 'Net Loss' ?>
                        </td>
                        <td class="text-end">
                            <?= number_format(abs($totalRevenue - $totalExpenses), 2) ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
// Include footer
include 'footer.php';
?>